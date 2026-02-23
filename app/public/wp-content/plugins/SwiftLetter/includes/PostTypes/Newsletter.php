<?php

namespace SwiftLetter\PostTypes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Newsletter {

	public const POST_TYPE = 'swl_newsletter';

	public function register(): void {
		register_post_type( self::POST_TYPE, [
			'labels'              => [
				'name'          => __( 'Newsletters', 'swiftletter' ),
				'singular_name' => __( 'Newsletter', 'swiftletter' ),
			],
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => true,
			'rest_base'           => 'swl-newsletters',
			'supports'            => [ 'title' ],
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
			'has_archive'         => false,
			'rewrite'             => false,
			'show_in_menu'        => false,
		] );
	}
}
