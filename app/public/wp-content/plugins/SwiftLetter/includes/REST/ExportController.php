<?php

namespace SwiftLetter\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\Audit\AuditLog;
use SwiftLetter\PostTypes\Article;
use SwiftLetter\PostTypes\Newsletter;

class ExportController extends \WP_REST_Controller {

	protected $namespace = 'swiftletter/v1';
	protected $rest_base = 'newsletters';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/publish-post', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'publish_post' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check( $request ): bool|\WP_Error {
		if ( ! current_user_can( 'publish_posts' ) ) {
			return false;
		}

		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot publish this newsletter.', 'swiftletter' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function publish_post( $request ): \WP_REST_Response|\WP_Error {
		$newsletter = $this->get_newsletter( $request['id'] );
		if ( is_wp_error( $newsletter ) ) {
			return $newsletter;
		}

		$articles = $this->get_ordered_articles( $newsletter->ID );

		if ( empty( $articles ) ) {
			return new \WP_Error( 'no_articles', __( 'Newsletter has no articles.', 'swiftletter' ), [ 'status' => 422 ] );
		}

		// All articles must be review-confirmed before publishing.
		$unconfirmed = [];
		foreach ( $articles as $article ) {
			if ( ! get_post_meta( $article->ID, '_swl_review_confirmed', true ) ) {
				$unconfirmed[] = [
					'id'    => $article->ID,
					'title' => $article->post_title,
				];
			}
		}

		if ( ! empty( $unconfirmed ) ) {
			return new \WP_Error(
				'unconfirmed_articles',
				__( 'All articles must be reviewed before publishing.', 'swiftletter' ),
				[ 'status' => 422, 'articles' => $unconfirmed ]
			);
		}

		$content = $this->build_post_content( $newsletter, $articles );

		$post_id = wp_insert_post( [
			'post_type'    => 'post',
			'post_title'   => $newsletter->post_title,
			'post_content' => $content,
			'post_status'  => 'draft',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$audit = new AuditLog();
		$audit->log( $newsletter->ID, null, 'newsletter_published_as_post', [
			'post_id' => $post_id,
		] );

		return new \WP_REST_Response( [
			'post_id'  => $post_id,
			'edit_url' => get_edit_post_link( $post_id, 'raw' ),
			'view_url' => get_permalink( $post_id ),
		], 201 );
	}

	/**
	 * Build the combined Gutenberg block content for a newsletter post.
	 * Includes a clickable Table of Contents followed by each article's content.
	 */
	private function build_post_content( \WP_Post $newsletter, array $articles ): string {
		$blocks = '';

		// Table of Contents heading.
		$blocks .= "<!-- wp:heading {\"level\":2} -->\n<h2>Table of Contents</h2>\n<!-- /wp:heading -->\n\n";

		// TOC list items — each links to its article section anchor.
		$toc_items = '';
		foreach ( $articles as $article ) {
			$slug       = $this->slugify( $article->post_title );
			$toc_items .= sprintf(
				'<li><a href="#%s">%s</a></li>',
				esc_attr( $slug ),
				esc_html( $article->post_title )
			);
		}
		$blocks .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">{$toc_items}</ul>\n<!-- /wp:list -->\n\n";

		// Separator.
		$blocks .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";

		// Article sections.
		foreach ( $articles as $article ) {
			$slug = $this->slugify( $article->post_title );

			// Article heading with an anchor so TOC links work.
			$blocks .= sprintf(
				"<!-- wp:heading {\"level\":2,\"anchor\":\"%s\"} -->\n<h2 class=\"wp-block-heading\" id=\"%s\">%s</h2>\n<!-- /wp:heading -->\n\n",
				esc_attr( $slug ),
				esc_attr( $slug ),
				esc_html( $article->post_title )
			);

			// Article body blocks as-is.
			$blocks .= trim( $article->post_content ) . "\n\n";

			// Separator between articles.
			$blocks .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
		}

		return $blocks;
	}

	private function slugify( string $text ): string {
		$slug = strtolower( $text );
		$slug = preg_replace( '/[^a-z0-9\s-]/', '', $slug );
		$slug = preg_replace( '/[\s-]+/', '-', $slug );
		return trim( $slug, '-' );
	}

	private function get_newsletter( int $id ): \WP_Post|\WP_Error {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Newsletter not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}
		return $post;
	}

	private function get_ordered_articles( int $newsletter_id ): array {
		return get_posts( [
			'post_type'      => Article::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'meta_query'     => [
				'relation'         => 'AND',
				'newsletter_clause' => [
					'key'   => '_swl_newsletter_id',
					'value' => $newsletter_id,
				],
				'order_clause'      => [
					'key'  => '_swl_article_order',
					'type' => 'NUMERIC',
				],
			],
			'orderby'        => 'order_clause',
			'order'          => 'ASC',
		] );
	}
}
