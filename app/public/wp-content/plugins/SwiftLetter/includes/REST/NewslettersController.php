<?php

namespace SwiftLetter\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\Audit\AuditLog;
use SwiftLetter\PostTypes\Newsletter;

class NewslettersController extends \WP_REST_Controller {

	protected $namespace = 'swiftletter/v1';
	protected $rest_base = 'newsletters';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'get_items_permissions_check' ],
				'args'                => [
					'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
					'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
				],
			],
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'create_item_permissions_check' ],
				'args'                => [
					'title' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true ],
				],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'update_item_permissions_check' ],
				'args'                => [
					'id'    => [ 'type' => 'integer', 'required' => true ],
					'title' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'delete_item_permissions_check' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/audit-log', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_audit_log' ],
				'permission_callback' => [ $this, 'get_item_permissions_check' ],
				'args'                => [
					'id' => [ 'type' => 'integer', 'required' => true ],
				],
			],
		] );
	}

	public function get_items_permissions_check( $request ): bool {
		return current_user_can( 'edit_posts' );
	}

	public function create_item_permissions_check( $request ): bool {
		return current_user_can( 'edit_posts' );
	}

	public function get_item_permissions_check( $request ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot edit this newsletter.', 'swiftletter' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function update_item_permissions_check( $request ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot edit this newsletter.', 'swiftletter' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function delete_item_permissions_check( $request ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $post_id && ! current_user_can( 'delete_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot delete this newsletter.', 'swiftletter' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function get_items( $request ): \WP_REST_Response {
		$page     = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );

		$query = new \WP_Query( [
			'post_type'      => Newsletter::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		] );

		$items = array_map( [ $this, 'prepare_newsletter' ], $query->posts );

		$response = new \WP_REST_Response( $items, 200 );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', $query->max_num_pages );

		return $response;
	}

	public function create_item( $request ): \WP_REST_Response|\WP_Error {
		$title = $request->get_param( 'title' );

		$post_id = wp_insert_post( [
			'post_type'   => Newsletter::POST_TYPE,
			'post_title'  => $title,
			'post_status' => 'publish',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$audit = new AuditLog();
		$audit->log( $post_id, null, 'newsletter_created', [
			'title' => $title,
		] );

		$post = get_post( $post_id );
		return new \WP_REST_Response( $this->prepare_newsletter( $post ), 201 );
	}

	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Newsletter not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$data = $this->prepare_newsletter( $post );

		// Include articles.
		$articles = get_posts( [
			'post_type'      => \SwiftLetter\PostTypes\Article::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation'         => 'AND',
				'newsletter_clause' => [
					'key'   => '_swl_newsletter_id',
					'value' => $post->ID,
				],
				'order_clause'      => [
					'key'  => '_swl_article_order',
					'type' => 'NUMERIC',
				],
			],
			'orderby'        => 'order_clause',
			'order'          => 'ASC',
		] );

		$data['articles'] = array_map( function ( $article ) {
			return [
				'id'                => $article->ID,
				'title'             => $article->post_title,
				'order'             => (int) get_post_meta( $article->ID, '_swl_article_order', true ),
				'review_confirmed'  => (bool) get_post_meta( $article->ID, '_swl_review_confirmed', true ),
				'has_audio'         => ! empty( get_post_meta( $article->ID, '_swl_audio_file_path', true ) ),
				'edit_url'          => get_edit_post_link( $article->ID, 'raw' ),
			];
		}, $articles );

		return new \WP_REST_Response( $data, 200 );
	}

	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Newsletter not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$result = wp_update_post( [
			'ID'         => $post->ID,
			'post_title' => $request->get_param( 'title' ),
		], true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $this->prepare_newsletter( get_post( $post->ID ) ), 200 );
	}

	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Newsletter not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		// Delete associated articles.
		$articles = get_posts( [
			'post_type'      => \SwiftLetter\PostTypes\Article::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => '_swl_newsletter_id',
			'meta_value'     => $post->ID,
			'fields'         => 'ids',
		] );

		global $wpdb;

		foreach ( $articles as $article_id ) {
			$wpdb->delete( $wpdb->prefix . 'swl_article_versions', [ 'article_id' => $article_id ], [ '%d' ] );
			$wpdb->delete( $wpdb->prefix . 'swl_audit_log', [ 'article_id' => $article_id ], [ '%d' ] );
			wp_delete_post( $article_id, true );
		}

		$wpdb->delete( $wpdb->prefix . 'swl_audit_log', [ 'newsletter_id' => $post->ID ], [ '%d' ] );

		$audit = new AuditLog();
		$audit->log( $post->ID, null, 'newsletter_deleted', [
			'title' => $post->post_title,
		] );

		wp_delete_post( $post->ID, true );

		return new \WP_REST_Response( null, 204 );
	}

	public function get_audit_log( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Newsletter not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$audit   = new AuditLog();
		$entries = $audit->get_for_newsletter( $post->ID );

		return new \WP_REST_Response( $entries, 200 );
	}

	private function prepare_newsletter( \WP_Post $post ): array {
		return [
			'id'                  => $post->ID,
			'title'               => $post->post_title,
			'date'                => $post->post_date,
			'date_gmt'            => $post->post_date_gmt,
			'modified'            => $post->post_modified,
			'modified_gmt'        => $post->post_modified_gmt,
			'article_count'       => $this->get_article_count( $post->ID ),
			'has_newsletter_audio' => ! empty( get_post_meta( $post->ID, '_swl_newsletter_audio_file_path', true ) ),
			'has_newsletter_docx'  => ! empty( get_post_meta( $post->ID, '_swl_newsletter_docx_file_path', true ) ),
		];
	}

	private function get_article_count( int $newsletter_id ): int {
		$query = new \WP_Query( [
			'post_type'      => \SwiftLetter\PostTypes\Article::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_key'       => '_swl_newsletter_id',
			'meta_value'     => $newsletter_id,
			'fields'         => 'ids',
		] );

		return $query->found_posts;
	}
}
