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

		// Generate combined newsletter DOCX before building the post content.
		$this->generate_newsletter_docx( $newsletter, $articles );

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

		// Store the published post ID so audio generation can update it later.
		update_post_meta( $newsletter->ID, '_swl_published_post_id', $post_id );

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
	 *
	 * Structure:
	 *   [Available Formats] — newsletter DOCX download + audio player + audio download
	 *   [Table of Contents]
	 *   [Article sections — heading + content only, no per-article format blocks]
	 */
	private function build_post_content( \WP_Post $newsletter, array $articles ): string {
		$blocks = '';

		// "Available Formats" section — newsletter-level downloads and audio.
		// Shown when the newsletter has a DOCX or audio file.
		$newsletter_audio_path = get_post_meta( $newsletter->ID, '_swl_newsletter_audio_file_path', true );
		$newsletter_docx_path  = get_post_meta( $newsletter->ID, '_swl_newsletter_docx_file_path', true );
		$newsletter_audio_url  = ( ! empty( $newsletter_audio_path ) && file_exists( $newsletter_audio_path ) )
			? $this->get_audio_url( $newsletter_audio_path )
			: null;
		$newsletter_docx_url   = ( ! empty( $newsletter_docx_path ) && file_exists( $newsletter_docx_path ) )
			? $this->get_docx_url( $newsletter_docx_path )
			: null;

		if ( $newsletter_audio_url || $newsletter_docx_url ) {
			$blocks .= "<!-- wp:heading {\"level\":2} -->\n<h2>Available Formats</h2>\n<!-- /wp:heading -->\n\n";

			if ( $newsletter_docx_url ) {
				$blocks .= sprintf(
					"<!-- wp:paragraph -->\n<p><a href=\"%s\" download>Download Newsletter as Word Document</a></p>\n<!-- /wp:paragraph -->\n\n",
					esc_url( $newsletter_docx_url )
				);
			}

			if ( $newsletter_audio_url ) {
				$blocks .= sprintf(
					"<!-- wp:audio -->\n<figure class=\"wp-block-audio\"><audio controls src=\"%s\"></audio></figure>\n<!-- /wp:audio -->\n\n",
					esc_url( $newsletter_audio_url )
				);
				$blocks .= sprintf(
					"<!-- wp:paragraph -->\n<p><a href=\"%s\" download>Download Newsletter Audio (MP3)</a></p>\n<!-- /wp:paragraph -->\n\n",
					esc_url( $newsletter_audio_url )
				);
			}

			$blocks .= "<!-- wp:separator -->\n<hr class=\"wp-block-separator has-alpha-channel-opacity\"/>\n<!-- /wp:separator -->\n\n";
		}

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

	/**
	 * Generate a single Word (.docx) file containing all articles in the newsletter.
	 * Saves to the public docx directory and stores the path in newsletter post meta.
	 *
	 * @param \WP_Post   $newsletter
	 * @param \WP_Post[] $articles   Ordered array of article posts.
	 * @return string|null File path on success, null on failure.
	 */
	private function generate_newsletter_docx( \WP_Post $newsletter, array $articles ): ?string {
		if ( ! class_exists( '\PhpOffice\PhpWord\PhpWord' ) ) {
			return null;
		}

		try {
			$phpword = new \PhpOffice\PhpWord\PhpWord();
			$section = $phpword->addSection();

			// Newsletter title as the document's top-level heading.
			$section->addTitle( $newsletter->post_title, 1 );

			foreach ( $articles as $article ) {
				// Article title as a second-level heading.
				$section->addTitle( $article->post_title, 2 );

				// Render Gutenberg blocks to HTML and parse into PhpWord elements.
				$html = apply_filters( 'the_content', do_blocks( $article->post_content ) );
				\PhpOffice\PhpWord\Shared\Html::addHtml( $section, '<div>' . $html . '</div>', false, false );
			}

			$upload_dir = wp_upload_dir();
			$docx_dir   = $upload_dir['basedir'] . '/swiftletter/docx';
			if ( ! is_dir( $docx_dir ) ) {
				wp_mkdir_p( $docx_dir );
			}

			$file_path = $docx_dir . '/newsletter-' . $newsletter->ID . '.docx';

			$writer = \PhpOffice\PhpWord\IOFactory::createWriter( $phpword, 'Word2007' );
			$writer->save( $file_path );

			update_post_meta( $newsletter->ID, '_swl_newsletter_docx_file_path', $file_path );

			return $file_path;
		} catch ( \Throwable $e ) {
			error_log( 'SwiftLetter DOCX generation error for newsletter ' . $newsletter->ID . ': ' . $e->getMessage() );
			return null;
		}
	}

	/**
	 * Convert an audio file path (inside the uploads directory) to its public URL.
	 */
	private function get_audio_url( string $file_path ): ?string {
		$upload_dir = wp_upload_dir();
		$base_dir   = wp_normalize_path( $upload_dir['basedir'] );
		$base_url   = $upload_dir['baseurl'];
		$file_path  = wp_normalize_path( $file_path );

		if ( str_starts_with( $file_path, $base_dir ) ) {
			return $base_url . substr( $file_path, strlen( $base_dir ) );
		}

		return null;
	}

	/**
	 * Convert a DOCX file path (inside the uploads directory) to its public URL.
	 */
	private function get_docx_url( string $file_path ): ?string {
		$upload_dir = wp_upload_dir();
		$base_dir   = wp_normalize_path( $upload_dir['basedir'] );
		$base_url   = $upload_dir['baseurl'];
		$file_path  = wp_normalize_path( $file_path );

		if ( str_starts_with( $file_path, $base_dir ) ) {
			return $base_url . substr( $file_path, strlen( $base_dir ) );
		}

		return null;
	}

	/**
	 * Rebuild the content of a previously published WordPress post for the given newsletter.
	 * Called automatically after audio is generated or an article is saved so changes
	 * appear in the live post without needing to re-publish.
	 */
	public static function rebuild_published_post( int $newsletter_id ): void {
		$published_post_id = (int) get_post_meta( $newsletter_id, '_swl_published_post_id', true );
		if ( ! $published_post_id ) {
			return;
		}

		$published_post = get_post( $published_post_id );
		if ( ! $published_post || $published_post->post_status === 'trash' ) {
			return;
		}

		$newsletter = get_post( $newsletter_id );
		if ( ! $newsletter ) {
			return;
		}

		$instance = new self();
		$articles = $instance->get_ordered_articles( $newsletter_id );

		if ( empty( $articles ) ) {
			return;
		}

		// Regenerate the combined newsletter DOCX so downloads reflect the latest content.
		$instance->generate_newsletter_docx( $newsletter, $articles );

		$content = $instance->build_post_content( $newsletter, $articles );

		wp_update_post( [
			'ID'           => $published_post_id,
			'post_content' => $content,
		] );
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
