<?php

namespace SwiftLetter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function init(): void {
		$this->check_db_version();

		// Register post types.
		add_action( 'init', [ new PostTypes\Newsletter(), 'register' ] );
		add_action( 'init', [ new PostTypes\Article(), 'register' ] );

		// REST API.
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Admin.
		if ( is_admin() ) {
			$admin_page    = new Admin\AdminPage();
			$settings_page = new Settings\SettingsPage();

			add_action( 'admin_menu', [ $admin_page, 'register_menu' ] );
			add_action( 'admin_menu', [ $settings_page, 'register_menu' ] );
			add_action( 'admin_init', [ $settings_page, 'register_settings' ] );
			add_action( 'admin_enqueue_scripts', [ $admin_page, 'enqueue_assets' ] );
			add_action( 'admin_enqueue_scripts', [ $settings_page, 'enqueue_settings_assets' ] );
		}

		// Enqueue article sidebar for block editor.
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_article_sidebar' ] );

		// Reset review confirmation when article content changes.
		add_action( 'save_post_swl_article', [ $this, 'on_article_save' ], 10, 3 );

		// GitHub-based update checker.
		Updates\UpdateChecker::init();
	}

	public function enqueue_article_sidebar(): void {
		$screen = get_current_screen();
		if ( ! $screen || $screen->post_type !== 'swl_article' ) {
			return;
		}

		$asset_file = SWIFTLETTER_DIR . 'build/article-sidebar.asset.php';
		$assets     = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [],
			'version'      => SWIFTLETTER_VERSION,
		];

		wp_enqueue_script(
			'swiftletter-article-sidebar',
			SWIFTLETTER_URL . 'build/article-sidebar.js',
			$assets['dependencies'],
			$assets['version'],
			true
		);

		global $post;
		$newsletter_id = $post ? (int) get_post_meta( $post->ID, '_swl_newsletter_id', true ) : 0;

		wp_localize_script( 'swiftletter-article-sidebar', 'swiftletterData', [
			'restUrl'      => esc_url_raw( rest_url( 'swiftletter/v1/' ) ),
			'nonce'        => wp_create_nonce( 'wp_rest' ),
			'dashboardUrl' => admin_url( 'admin.php?page=swiftletter' ),
			'newsletterId' => $newsletter_id,
		] );
	}

	public function register_rest_routes(): void {
		$controllers = [
			new REST\NewslettersController(),
			new REST\ArticlesController(),
			new REST\AIController(),
			new REST\TTSController(),
			new REST\ExportController(),
			new REST\SettingsController(),
		];

		foreach ( $controllers as $controller ) {
			$controller->register_routes();
		}
	}

	public function on_article_save( int $post_id, \WP_Post $post, bool $update ): void {
		if ( ! $update ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$newsletter_id = (int) get_post_meta( $post_id, '_swl_newsletter_id', true );

		$confirmed = get_post_meta( $post_id, '_swl_review_confirmed', true );
		if ( $confirmed ) {
			update_post_meta( $post_id, '_swl_review_confirmed', false );
			update_post_meta( $post_id, '_swl_review_confirmed_at', '' );
			update_post_meta( $post_id, '_swl_review_confirmed_by', 0 );

			$audit = new Audit\AuditLog();
			$audit->log( $newsletter_id, $post_id, 'review_reset_on_edit', [
				'reason' => 'Content edited after review confirmation',
			] );
		}

		// Rebuild the published newsletter post so content changes reflect immediately.
		if ( $newsletter_id ) {
			REST\ExportController::rebuild_published_post( $newsletter_id );
		}
	}

	private function check_db_version(): void {
		$installed_version = get_option( 'swl_db_version', '0' );
		if ( version_compare( $installed_version, SWIFTLETTER_VERSION, '<' ) ) {
			Database\Schema::create_tables();
			update_option( 'swl_db_version', SWIFTLETTER_VERSION );
		}
	}
}
