<?php

namespace SwiftLetter\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AuditLog {

	/**
	 * Log an event to the audit log.
	 */
	public function log( int $newsletter_id, ?int $article_id, string $event_type, array $data = [] ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'swl_audit_log',
			[
				'newsletter_id' => $newsletter_id,
				'article_id'    => $article_id,
				'user_id'       => get_current_user_id(),
				'event_type'    => sanitize_text_field( $event_type ),
				'event_data'    => wp_json_encode( $data ),
				'created_at'    => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%d', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get audit log entries for a newsletter.
	 */
	public function get_for_newsletter( int $newsletter_id, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'swl_audit_log';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE newsletter_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$newsletter_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}

	/**
	 * Get audit log entries for an article.
	 */
	public function get_for_article( int $article_id, int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = $wpdb->prefix . 'swl_audit_log';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE article_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$article_id,
				$limit,
				$offset
			),
			ARRAY_A
		);
	}
}
