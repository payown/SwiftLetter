<?php

namespace SwiftLetter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Deactivator {

	public static function deactivate(): void {
		// Remove scheduled cron events.
		$timestamp = wp_next_scheduled( 'swl_poll_auphonic' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'swl_poll_auphonic' );
		}

		flush_rewrite_rules();
	}
}
