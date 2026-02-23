<?php

namespace SwiftLetter\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminPage {

	public function register_menu(): void {
		add_menu_page(
			__( 'SwiftLetter', 'swiftletter' ),
			__( 'SwiftLetter', 'swiftletter' ),
			'edit_posts',
			'swiftletter',
			[ $this, 'render' ],
			'dashicons-email-alt',
			26
		);

		// Rename the auto-created first submenu from "SwiftLetter" to "Dashboard".
		add_submenu_page(
			'swiftletter',
			__( 'SwiftLetter Dashboard', 'swiftletter' ),
			__( 'Dashboard', 'swiftletter' ),
			'edit_posts',
			'swiftletter',
			[ $this, 'render' ]
		);

		// Articles submenu — links directly to the articles list screen.
		add_submenu_page(
			'swiftletter',
			__( 'Articles', 'swiftletter' ),
			__( 'Articles', 'swiftletter' ),
			'edit_posts',
			'edit.php?post_type=swl_article'
		);

		// Newsletter column in the articles list table.
		add_filter( 'manage_swl_article_posts_columns', [ $this, 'add_newsletter_column' ] );
		add_action( 'manage_swl_article_posts_custom_column', [ $this, 'render_newsletter_column' ], 10, 2 );
	}

	/**
	 * Adds a "Newsletter" column to the swl_article list table.
	 */
	public function add_newsletter_column( array $columns ): array {
		// Insert the Newsletter column right after the Title column.
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;
			if ( $key === 'title' ) {
				$new['newsletter'] = __( 'Newsletter', 'swiftletter' );
			}
		}
		return $new;
	}

	/**
	 * Renders the "Newsletter" column cell for each article row.
	 */
	public function render_newsletter_column( string $column, int $post_id ): void {
		if ( $column !== 'newsletter' ) {
			return;
		}

		$newsletter_id = (int) get_post_meta( $post_id, '_swl_newsletter_id', true );

		if ( ! $newsletter_id ) {
			echo '—';
			return;
		}

		$title = get_the_title( $newsletter_id );
		if ( ! $title ) {
			echo '—';
			return;
		}

		$url = add_query_arg(
			[ 'page' => 'swiftletter', 'newsletter_id' => $newsletter_id ],
			admin_url( 'admin.php' )
		);

		printf(
			'<a href="%s">%s</a>',
			esc_url( $url ),
			esc_html( $title )
		);
	}

	public function render(): void {
		echo '<div id="swiftletter-dashboard" class="wrap"></div>';
	}

	public function enqueue_assets( string $hook ): void {
		if ( $hook !== 'toplevel_page_swiftletter' ) {
			return;
		}

		$asset_file = SWIFTLETTER_DIR . 'build/dashboard.asset.php';
		$assets     = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [],
			'version'      => SWIFTLETTER_VERSION,
		];

		wp_enqueue_script(
			'swiftletter-dashboard',
			SWIFTLETTER_URL . 'build/dashboard.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		wp_enqueue_style(
			'swiftletter-dashboard',
			SWIFTLETTER_URL . 'build/style-dashboard.css',
			[ 'wp-components' ],
			$assets['version']
		);

		wp_enqueue_style(
			'swiftletter-admin',
			SWIFTLETTER_URL . 'admin/css/admin.css',
			[],
			SWIFTLETTER_VERSION
		);

		wp_localize_script( 'swiftletter-dashboard', 'swiftletterData', [
			'restUrl'   => esc_url_raw( rest_url( 'swiftletter/v1/' ) ),
			'nonce'     => wp_create_nonce( 'wp_rest' ),
			'adminUrl'  => admin_url(),
			'editUrl'   => admin_url( 'post.php?action=edit&post=' ),
			'newArticleUrl' => admin_url( 'post-new.php?post_type=swl_article' ),
		] );
	}
}
