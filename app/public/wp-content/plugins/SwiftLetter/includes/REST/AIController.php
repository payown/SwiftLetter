<?php

namespace SwiftLetter\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\AI\AIService;
use SwiftLetter\Audit\AuditLog;
use SwiftLetter\PostTypes\Article;

class AIController extends \WP_REST_Controller {

	protected $namespace = 'swiftletter/v1';
	protected $rest_base = 'articles';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/ai-refine', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'refine' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/confirm-review', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'confirm_review' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/attachments/(?P<id>[\d]+)/generate-alt-text', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_attachment_alt_text' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'article_title' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	public function permissions_check( $request ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot edit this item.', 'swiftletter' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function refine( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Article::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Article not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$original_content = $post->post_content;

		// Snapshot pre-AI version.
		ArticlesController::snapshot_version( $post->ID, 'pre_ai', $original_content );

		try {
			$ai_service     = new AIService();
			$refined_content = $ai_service->refine( $original_content, $post->post_title );
		} catch ( \Throwable $e ) {
			error_log( 'SwiftLetter AI refine error: ' . $e->getMessage() );
			return new \WP_Error( 'ai_error', __( 'AI refinement failed. Please try again.', 'swiftletter' ), [ 'status' => 500 ] );
		}

		// Update post content.
		wp_update_post( [
			'ID'           => $post->ID,
			'post_content' => $refined_content,
		] );

		// Snapshot post-AI version.
		ArticlesController::snapshot_version( $post->ID, 'post_ai', $refined_content );

		// Reset review confirmation.
		update_post_meta( $post->ID, '_swl_review_confirmed', false );

		// Log audit event.
		$newsletter_id = (int) get_post_meta( $post->ID, '_swl_newsletter_id', true );
		$audit         = new AuditLog();
		$audit->log( $newsletter_id, $post->ID, 'ai_processed', [
			'provider' => get_option( 'swl_active_ai', 'openai' ),
		] );

		return new \WP_REST_Response( [
			'original' => $original_content,
			'refined'  => $refined_content,
		], 200 );
	}

	public function generate_attachment_alt_text( $request ): \WP_REST_Response|\WP_Error {
		$attachment_id = absint( $request['id'] );
		$attachment    = get_post( $attachment_id );

		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return new \WP_Error( 'not_found', __( 'Attachment not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$article_title = $request->get_param( 'article_title' );

		try {
			$ai_service = new AIService();
			$alt_text   = $ai_service->generate_alt_text( $article_title );
		} catch ( \Throwable $e ) {
			error_log( 'SwiftLetter AI alt-text error: ' . $e->getMessage() );
			return new \WP_Error( 'ai_error', __( 'Alt text generation failed. Please try again.', 'swiftletter' ), [ 'status' => 500 ] );
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );

		return new \WP_REST_Response( [ 'alt_text' => $alt_text ], 200 );
	}

	public function confirm_review( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Article::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Article not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$user_id = get_current_user_id();
		$now     = current_time( 'mysql', true );

		update_post_meta( $post->ID, '_swl_review_confirmed', true );
		update_post_meta( $post->ID, '_swl_review_confirmed_at', $now );
		update_post_meta( $post->ID, '_swl_review_confirmed_by', $user_id );

		$newsletter_id = (int) get_post_meta( $post->ID, '_swl_newsletter_id', true );
		$audit         = new AuditLog();
		$audit->log( $newsletter_id, $post->ID, 'review_confirmed', [
			'user_id' => $user_id,
		] );

		return new \WP_REST_Response( [
			'review_confirmed'    => true,
			'review_confirmed_at' => $now,
			'review_confirmed_by' => $user_id,
		], 200 );
	}
}
