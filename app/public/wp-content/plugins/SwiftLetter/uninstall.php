<?php
/**
 * Fired when the plugin is uninstalled.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
// Note: Table names cannot be parameterized in prepared statements.
// $wpdb->prefix is set in wp-config.php and is not user-controlled.
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}swl_audit_log" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}swl_article_versions" );

// Delete all swl_* options.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
		'swl\_%'
	)
);

// Delete all CPT posts and their meta.
$post_types = [ 'swl_newsletter', 'swl_article' ];
foreach ( $post_types as $post_type ) {
	$posts = get_posts( [
		'post_type'      => $post_type,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	] );
	foreach ( $posts as $post_id ) {
		wp_delete_post( $post_id, true );
	}
}

// Remove uploaded files.
$upload_dir = wp_upload_dir();
$swl_dir    = $upload_dir['basedir'] . '/swiftletter';
if ( is_dir( $swl_dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
	$filesystem = new WP_Filesystem_Direct( null );
	$filesystem->rmdir( $swl_dir, true );
}

// Delete transients.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'_transient_swl\_%',
		'_transient_timeout_swl\_%'
	)
);
