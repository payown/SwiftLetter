<?php

namespace SwiftLetter\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = [];

		$sql[] = "CREATE TABLE {$wpdb->prefix}swl_audit_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			newsletter_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			article_id BIGINT UNSIGNED DEFAULT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event_type VARCHAR(100) NOT NULL,
			event_data LONGTEXT,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_newsletter (newsletter_id),
			KEY idx_article (article_id),
			KEY idx_event_type (event_type),
			KEY idx_created_at (created_at)
		) $charset_collate;";

		$sql[] = "CREATE TABLE {$wpdb->prefix}swl_article_versions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			article_id BIGINT UNSIGNED NOT NULL,
			version_type VARCHAR(50) NOT NULL,
			block_content LONGTEXT NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_article (article_id),
			KEY idx_version_type (version_type)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}
}
