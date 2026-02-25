<?php

namespace SwiftLetter\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\Audit\AuditLog;
use SwiftLetter\PostTypes\Article;
use SwiftLetter\PostTypes\Newsletter;

class ArticlesController extends \WP_REST_Controller {

	protected $namespace = 'swiftletter/v1';
	protected $rest_base = 'articles';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'newsletter_id' => [ 'required' => true, 'type' => 'integer' ],
					'title'         => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'content'       => [
						'type'    => 'string',
						'default' => '',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => \WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'title' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/reorder', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reorder_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'order' => [
						'required' => true,
						'type'     => 'array',
						'items'    => [
							'type'       => 'object',
							'properties' => [
								'id'    => [ 'type' => 'integer' ],
								'order' => [ 'type' => 'integer' ],
							],
						],
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/copy', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'copy_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'target_newsletter_id' => [ 'required' => true, 'type' => 'integer' ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/versions', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_versions' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/restore/(?P<version_id>[\d]+)', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'restore_version' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/upload-docx', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload_docx' ],
				'permission_callback' => [ $this, 'permissions_check' ],
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

	public function create_item( $request ): \WP_REST_Response|\WP_Error {
		$newsletter_id = absint( $request->get_param( 'newsletter_id' ) );

		$newsletter = get_post( $newsletter_id );
		if ( ! $newsletter || $newsletter->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'invalid_newsletter', __( 'Invalid newsletter.', 'swiftletter' ), [ 'status' => 400 ] );
		}

		// Determine next order position.
		$existing = get_posts( [
			'post_type'      => Article::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_swl_newsletter_id',
			'meta_value'     => $newsletter_id,
			'orderby'        => 'meta_value_num',
			'meta_query'     => [ [ 'key' => '_swl_article_order', 'type' => 'NUMERIC' ] ],
			'order'          => 'DESC',
		] );

		$next_order = 0;
		if ( ! empty( $existing ) ) {
			$next_order = (int) get_post_meta( $existing[0]->ID, '_swl_article_order', true ) + 1;
		}

		$content = $request->get_param( 'content' );
		$content = wp_kses_post( $content );

		$post_id = wp_insert_post( [
			'post_type'    => Article::POST_TYPE,
			'post_title'   => $request->get_param( 'title' ),
			'post_content' => $content,
			'post_status'  => 'draft',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_swl_newsletter_id', $newsletter_id );
		update_post_meta( $post_id, '_swl_article_order', $next_order );

		$audit = new AuditLog();
		$audit->log( $newsletter_id, $post_id, 'article_created', [
			'title' => $request->get_param( 'title' ),
		] );

		return new \WP_REST_Response( $this->prepare_article( get_post( $post_id ) ), 201 );
	}

	public function get_item( $request ): \WP_REST_Response|\WP_Error {
		$post = $this->get_article_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		return new \WP_REST_Response( $this->prepare_article( $post ), 200 );
	}

	public function update_item( $request ): \WP_REST_Response|\WP_Error {
		$post = $this->get_article_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$update = [ 'ID' => $post->ID ];

		if ( $request->has_param( 'title' ) ) {
			$update['post_title'] = $request->get_param( 'title' );
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response( $this->prepare_article( get_post( $post->ID ) ), 200 );
	}

	public function delete_item( $request ): \WP_REST_Response|\WP_Error {
		$post = $this->get_article_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$newsletter_id = (int) get_post_meta( $post->ID, '_swl_newsletter_id', true );

		$audit = new AuditLog();
		$audit->log( $newsletter_id, $post->ID, 'article_deleted', [
			'title' => $post->post_title,
		] );

		wp_delete_post( $post->ID, true );

		return new \WP_REST_Response( null, 204 );
	}

	public function reorder_items( $request ): \WP_REST_Response|\WP_Error {
		$order = $request->get_param( 'order' );

		foreach ( $order as $item ) {
			$id    = absint( $item['id'] );
			$pos   = absint( $item['order'] );

			$post = get_post( $id );
			if ( ! $post || $post->post_type !== Article::POST_TYPE ) {
				continue;
			}

			update_post_meta( $id, '_swl_article_order', $pos );
		}

		return new \WP_REST_Response( [ 'success' => true ], 200 );
	}

	public function copy_item( $request ): \WP_REST_Response|\WP_Error {
		$source = $this->get_article_post( $request['id'] );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$target_newsletter_id = absint( $request->get_param( 'target_newsletter_id' ) );
		$target_newsletter    = get_post( $target_newsletter_id );

		if ( ! $target_newsletter || $target_newsletter->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'invalid_newsletter', __( 'Invalid target newsletter.', 'swiftletter' ), [ 'status' => 400 ] );
		}

		// Determine next order in target newsletter.
		$existing = get_posts( [
			'post_type'      => Article::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_swl_newsletter_id',
			'meta_value'     => $target_newsletter_id,
			'orderby'        => 'meta_value_num',
			'meta_query'     => [ [ 'key' => '_swl_article_order', 'type' => 'NUMERIC' ] ],
			'order'          => 'DESC',
		] );

		$next_order = 0;
		if ( ! empty( $existing ) ) {
			$next_order = (int) get_post_meta( $existing[0]->ID, '_swl_article_order', true ) + 1;
		}

		$new_post_id = wp_insert_post( [
			'post_type'    => Article::POST_TYPE,
			'post_title'   => $source->post_title,
			'post_content' => $source->post_content,
			'post_status'  => 'draft',
		], true );

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		update_post_meta( $new_post_id, '_swl_newsletter_id', $target_newsletter_id );
		update_post_meta( $new_post_id, '_swl_article_order', $next_order );

		$source_newsletter_id = (int) get_post_meta( $source->ID, '_swl_newsletter_id', true );

		$audit = new AuditLog();
		$audit->log( $target_newsletter_id, $new_post_id, 'article_reused', [
			'source_article_id'    => $source->ID,
			'source_newsletter_id' => $source_newsletter_id,
			'title'                => $source->post_title,
		] );

		return new \WP_REST_Response( $this->prepare_article( get_post( $new_post_id ) ), 201 );
	}

	public function get_versions( $request ): \WP_REST_Response|\WP_Error {
		$post = $this->get_article_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'swl_article_versions';

		$versions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, version_type, user_id, created_at FROM {$table} WHERE article_id = %d ORDER BY created_at DESC",
				$post->ID
			),
			ARRAY_A
		);

		return new \WP_REST_Response( $versions, 200 );
	}

	public function restore_version( $request ): \WP_REST_Response|\WP_Error {
		$post = $this->get_article_post( $request['id'] );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'swl_article_versions';

		$version = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d AND article_id = %d",
				absint( $request['version_id'] ),
				$post->ID
			),
			ARRAY_A
		);

		if ( ! $version ) {
			return new \WP_Error( 'not_found', __( 'Version not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		// Snapshot current state before restoring.
		self::snapshot_version( $post->ID, 'manual', $post->post_content );

		wp_update_post( [
			'ID'           => $post->ID,
			'post_content' => $version['block_content'],
		] );

		$newsletter_id = (int) get_post_meta( $post->ID, '_swl_newsletter_id', true );
		$audit = new AuditLog();
		$audit->log( $newsletter_id, $post->ID, 'version_restored', [
			'version_id'   => $version['id'],
			'version_type' => $version['version_type'],
		] );

		return new \WP_REST_Response( $this->prepare_article( get_post( $post->ID ) ), 200 );
	}

	public function upload_docx( $request ): \WP_REST_Response|\WP_Error {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded.', 'swiftletter' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Check for PHP file upload errors.
		if ( ! empty( $file['error'] ) && $file['error'] !== UPLOAD_ERR_OK ) {
			$upload_errors = [
				UPLOAD_ERR_INI_SIZE   => __( 'File exceeds the server upload size limit.', 'swiftletter' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds the form upload size limit.', 'swiftletter' ),
				UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'swiftletter' ),
				UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'swiftletter' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'Server missing temporary folder.', 'swiftletter' ),
				UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'swiftletter' ),
			];
			$msg = $upload_errors[ $file['error'] ] ?? __( 'Unknown upload error.', 'swiftletter' );
			return new \WP_Error( 'upload_error', $msg, [ 'status' => 400 ] );
		}

		// Validate file type.
		$filetype = wp_check_filetype( $file['name'], [ 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' ] );
		if ( empty( $filetype['type'] ) ) {
			return new \WP_Error( 'invalid_type', __( 'Only .docx files are accepted.', 'swiftletter' ), [ 'status' => 400 ] );
		}

		// Validate newsletter.
		$newsletter_id = absint( $request->get_param( 'newsletter_id' ) );
		$newsletter    = get_post( $newsletter_id );
		if ( ! $newsletter || $newsletter->post_type !== Newsletter::POST_TYPE ) {
			return new \WP_Error( 'invalid_newsletter', __( 'Invalid newsletter.', 'swiftletter' ), [ 'status' => 400 ] );
		}

		if ( ! class_exists( '\PhpOffice\PhpWord\IOFactory' ) ) {
			return new \WP_Error(
				'missing_dependency',
				__( 'PhpWord library is not installed. Run "composer install" in the plugin directory.', 'swiftletter' ),
				[ 'status' => 500 ]
			);
		}

		try {
			$content = $this->extract_docx_content( $file['tmp_name'] );
		} catch ( \Throwable $e ) {
			return new \WP_Error( 'parse_error', $e->getMessage() ?: __( 'Failed to parse DOCX file.', 'swiftletter' ), [ 'status' => 400 ] );
		}

		// Determine next order position.
		$existing = get_posts( [
			'post_type'      => Article::POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => 1,
			'meta_key'       => '_swl_newsletter_id',
			'meta_value'     => $newsletter_id,
			'orderby'        => 'meta_value_num',
			'meta_query'     => [ [ 'key' => '_swl_article_order', 'type' => 'NUMERIC' ] ],
			'order'          => 'DESC',
		] );

		$next_order = 0;
		if ( ! empty( $existing ) ) {
			$next_order = (int) get_post_meta( $existing[0]->ID, '_swl_article_order', true ) + 1;
		}

		$post_id = wp_insert_post( [
			'post_type'    => Article::POST_TYPE,
			'post_title'   => $content['title'],
			'post_content' => wp_kses_post( $content['body'] ),
			'post_status'  => 'draft',
		], true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		update_post_meta( $post_id, '_swl_newsletter_id', $newsletter_id );
		update_post_meta( $post_id, '_swl_article_order', $next_order );

		$audit = new AuditLog();
		$audit->log( $newsletter_id, $post_id, 'article_created_from_docx', [
			'title' => $content['title'],
		] );

		// Build list of uploaded images that need alt text.
		$images_needing_alt_text = [];
		foreach ( $content['images'] as $att_id ) {
			$images_needing_alt_text[] = [
				'id'       => $att_id,
				'edit_url' => get_edit_post_link( $att_id, 'raw' ),
			];
		}

		return new \WP_REST_Response(
			array_merge(
				$this->prepare_article( get_post( $post_id ) ),
				[ 'images_needing_alt_text' => $images_needing_alt_text ]
			),
			201
		);
	}

	private function extract_docx_content( string $file_path ): array {
		$phpword = \PhpOffice\PhpWord\IOFactory::load( $file_path, 'Word2007' );

		$title      = '';
		$body       = '';
		$image_ids  = [];

		foreach ( $phpword->getSections() as $section ) {
			foreach ( $section->getElements() as $element ) {
				if ( $element instanceof \PhpOffice\PhpWord\Element\Title ) {
					$depth = $element->getDepth();
					$text  = $element->getText();
					if ( is_object( $text ) && method_exists( $text, 'getText' ) ) {
						$text = $text->getText();
					}
					$text = wp_strip_all_tags( (string) $text );

					if ( $depth === 1 && empty( $title ) ) {
						$title = $text;
					}

					$level = min( (int) $depth, 6 );
					$body .= sprintf( "<!-- wp:heading {\"level\":%d} -->\n<h%d>%s</h%d>\n<!-- /wp:heading -->\n\n", $level, $level, esc_html( $text ), $level );

				} elseif ( $element instanceof \PhpOffice\PhpWord\Element\TextRun ) {
					$para_text = '';
					foreach ( $element->getElements() as $child ) {
						if ( $child instanceof \PhpOffice\PhpWord\Element\Image ) {
							// Inline image inside a paragraph — upload and emit its own block.
							$block = $this->upload_docx_image( $child, $image_ids );
							if ( $block ) {
								$body .= $block;
							}
						} elseif ( method_exists( $child, 'getText' ) ) {
							$para_text .= $child->getText();
						}
					}
					$para_text = trim( $para_text );
					if ( ! empty( $para_text ) ) {
						$body .= sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n", wp_kses_post( $para_text ) );
					}

				} elseif ( $element instanceof \PhpOffice\PhpWord\Element\Image ) {
					// Standalone image at section level.
					$block = $this->upload_docx_image( $element, $image_ids );
					if ( $block ) {
						$body .= $block;
					}

				} elseif ( $element instanceof \PhpOffice\PhpWord\Element\Text ) {
					$text = trim( $element->getText() );
					if ( ! empty( $text ) ) {
						$body .= sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n", wp_kses_post( $text ) );
					}
				}
			}
		}

		return [
			'title'  => $title ?: __( 'Untitled Article', 'swiftletter' ),
			'body'   => $body,
			'images' => $image_ids,
		];
	}

	/**
	 * Upload a PHPWord Image element to the WordPress media library.
	 *
	 * Returns a Gutenberg image block string on success, or null on failure.
	 * Appends the new attachment ID to $attachment_ids.
	 *
	 * @param \PhpOffice\PhpWord\Element\Image $image         The image element.
	 * @param int[]                            $attachment_ids Running list of uploaded attachment IDs (passed by reference).
	 * @return string|null Gutenberg block markup or null.
	 */
	private function upload_docx_image( \PhpOffice\PhpWord\Element\Image $image, array &$attachment_ids ): ?string {
		$source = $image->getSource();
		if ( empty( $source ) || ! file_exists( $source ) ) {
			return null;
		}

		$allowed_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
		$ext          = strtolower( $image->getImageExtension() ?: pathinfo( $source, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $allowed_exts, true ) ) {
			return null;
		}

		// Ensure WP media upload functions are available.
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// Copy the source to a temp file with the correct extension so WP detects the MIME type.
		$tmp_path = wp_tempnam( 'swl-docx-image' );
		if ( ! copy( $source, $tmp_path ) ) {
			if ( file_exists( $tmp_path ) ) {
				wp_delete_file( $tmp_path );
			}
			return null;
		}

		$file_array = [
			'name'     => 'docx-image-' . uniqid() . '.' . $ext,
			'tmp_name' => $tmp_path,
		];

		$attachment_id = media_handle_sideload( $file_array, 0 );

		// Temp file is consumed (moved) by media_handle_sideload; clean up only if it still exists.
		if ( file_exists( $tmp_path ) ) {
			wp_delete_file( $tmp_path );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return null;
		}

		$attachment_ids[] = $attachment_id;

		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) {
			return null;
		}

		// WordPress uses the attachment ID in wp:image blocks to generate responsive srcset at render time.
		return sprintf(
			"<!-- wp:image {\"id\":%d,\"sizeSlug\":\"large\",\"linkDestination\":\"none\"} -->\n<figure class=\"wp-block-image size-large\"><img src=\"%s\" class=\"wp-image-%d\" alt=\"\"/></figure>\n<!-- /wp:image -->\n\n",
			$attachment_id,
			esc_url( $url ),
			$attachment_id
		);
	}

	public static function snapshot_version( int $article_id, string $type, string $content ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'swl_article_versions',
			[
				'article_id'    => $article_id,
				'version_type'  => sanitize_text_field( $type ),
				'block_content' => $content,
				'user_id'       => get_current_user_id(),
				'created_at'    => current_time( 'mysql', true ),
			],
			[ '%d', '%s', '%s', '%d', '%s' ]
		);
	}

	private function get_article_post( int $id ): \WP_Post|\WP_Error {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== Article::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Article not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}
		return $post;
	}

	private function prepare_article( \WP_Post $post ): array {
		return [
			'id'                => $post->ID,
			'title'             => $post->post_title,
			'content'           => $post->post_content,
			'status'            => $post->post_status,
			'date'              => $post->post_date,
			'modified'          => $post->post_modified,
			'newsletter_id'     => (int) get_post_meta( $post->ID, '_swl_newsletter_id', true ),
			'order'             => (int) get_post_meta( $post->ID, '_swl_article_order', true ),
			'review_confirmed'  => (bool) get_post_meta( $post->ID, '_swl_review_confirmed', true ),
			'review_confirmed_at' => get_post_meta( $post->ID, '_swl_review_confirmed_at', true ),
			'has_audio'         => ! empty( get_post_meta( $post->ID, '_swl_audio_file_path', true ) ),
			'edit_url'          => get_edit_post_link( $post->ID, 'raw' ),
		];
	}
}
