<?php

namespace SwiftLetter\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Article {

	public const POST_TYPE = 'swl_article';

	public function register(): void {
		register_post_type( self::POST_TYPE, [
			'labels'              => [
				'name'          => __( 'Articles', 'swiftletter' ),
				'singular_name' => __( 'Article', 'swiftletter' ),
				'edit_item'     => __( 'Edit Article', 'swiftletter' ),
				'add_new_item'  => __( 'Add New Article', 'swiftletter' ),
				'all_items'     => __( 'All Articles', 'swiftletter' ),
			],
			'public'              => false,
			'show_ui'             => true,
			'show_in_rest'        => true,
			'rest_base'           => 'swl-articles',
			'supports'            => [ 'title', 'editor', 'revisions' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_menu'        => false,
		] );

		$this->register_meta();
	}

	private function register_meta(): void {
		$meta_fields = [
			'_swl_newsletter_id' => [
				'type'    => 'integer',
				'default' => 0,
			],
			'_swl_article_order' => [
				'type'    => 'integer',
				'default' => 0,
			],
			'_swl_review_confirmed' => [
				'type'    => 'boolean',
				'default' => false,
			],
			'_swl_review_confirmed_at' => [
				'type'    => 'string',
				'default' => '',
			],
			'_swl_review_confirmed_by' => [
				'type'    => 'integer',
				'default' => 0,
			],
			'_swl_audio_file_path' => [
				'type'    => 'string',
				'default' => '',
			],
		];

		// Fields that must NOT be exposed or writable via REST to prevent path traversal attacks.
		$protected_fields = [ '_swl_audio_file_path' ];

		foreach ( $meta_fields as $key => $args ) {
			$is_protected = in_array( $key, $protected_fields, true );
			register_post_meta( self::POST_TYPE, $key, [
				'show_in_rest'  => ! $is_protected,
				'single'        => true,
				'type'          => $args['type'],
				'default'       => $args['default'],
				'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
					return current_user_can( 'edit_post', $post_id );
				},
			] );
		}
	}
}
