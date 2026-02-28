<?php

namespace SwiftLetter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	public static function activate(): void {
		Database\Schema::create_tables();
		update_option( 'swl_db_version', SWIFTLETTER_VERSION );

		// Generate webhook secret for Auphonic.
		if ( ! get_option( 'swl_auphonic_webhook_secret' ) ) {
			update_option( 'swl_auphonic_webhook_secret', wp_generate_password( 32, false ), false );
		}

		// Set default typography.
		if ( ! get_option( 'swl_typography' ) ) {
			update_option( 'swl_typography', self::default_typography(), false );
		}

		// Set default AI provider.
		if ( ! get_option( 'swl_active_ai' ) ) {
			update_option( 'swl_active_ai', 'openai', false );
		}

		// Set default TTS voice.
		if ( ! get_option( 'swl_tts_voice' ) ) {
			update_option( 'swl_tts_voice', 'coral', false );
		}

		// Check ffmpeg availability.
		$ffmpeg_path = self::find_ffmpeg();
		update_option( 'swl_ffmpeg_available', ! empty( $ffmpeg_path ), false );
		if ( $ffmpeg_path ) {
			update_option( 'swl_ffmpeg_path', $ffmpeg_path, false );
		}

		// Create uploads directories.
		$upload_dir  = wp_upload_dir();
		$swl_dir     = $upload_dir['basedir'] . '/swiftletter/audio';
		$exports_dir = $upload_dir['basedir'] . '/swiftletter/exports';
		$docx_dir    = $upload_dir['basedir'] . '/swiftletter/docx';

		if ( ! is_dir( $swl_dir ) ) {
			wp_mkdir_p( $swl_dir );
		}
		if ( ! is_dir( $exports_dir ) ) {
			wp_mkdir_p( $exports_dir );
		}
		if ( ! is_dir( $docx_dir ) ) {
			wp_mkdir_p( $docx_dir );
		}

		// Audio directory: must be publicly accessible so audio players and download
		// links in published posts work for all readers. Remove any existing deny rule.
		$audio_htaccess = $swl_dir . '/.htaccess';
		if ( file_exists( $audio_htaccess ) ) {
			wp_delete_file( $audio_htaccess );
		}
		$audio_index = $swl_dir . '/index.php';
		if ( ! file_exists( $audio_index ) ) {
			file_put_contents( $audio_index, "<?php\n// Silence is golden.\n" );
		}

		// Exports directory: internal use only, keep protected.
		$exports_htaccess = $exports_dir . '/.htaccess';
		if ( ! file_exists( $exports_htaccess ) ) {
			file_put_contents( $exports_htaccess, "deny from all\n" );
		}
		$exports_index = $exports_dir . '/index.php';
		if ( ! file_exists( $exports_index ) ) {
			file_put_contents( $exports_index, "<?php\n// Silence is golden.\n" );
		}

		// DOCX directory: publicly accessible so readers can download Word documents.
		$docx_index = $docx_dir . '/index.php';
		if ( ! file_exists( $docx_index ) ) {
			file_put_contents( $docx_index, "<?php\n// Silence is golden.\n" );
		}

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	public static function default_typography(): array {
		return [
			'font_family'    => 'Arial',
			'text_color'     => '#000000',
			'bg_color'       => '#FFFFFF',
			'link_color'     => '#1E90FF',
			'h1_size'        => 24,
			'h2_size'        => 22,
			'h3_size'        => 20,
			'h4_size'        => 18,
			'body_size'      => 18,
		];
	}

	private static function find_ffmpeg(): string {
		if ( ! function_exists( 'proc_open' ) ) {
			return '';
		}

		$descriptors = [
			0 => [ 'pipe', 'r' ],
			1 => [ 'pipe', 'w' ],
			2 => [ 'pipe', 'w' ],
		];

		$cmd = PHP_OS_FAMILY === 'Windows' ? 'where ffmpeg' : 'which ffmpeg';
		$process = proc_open( $cmd, $descriptors, $pipes );

		if ( ! is_resource( $process ) ) {
			return '';
		}

		fclose( $pipes[0] );
		$output = trim( stream_get_contents( $pipes[1] ) );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		proc_close( $process );

		return $output;
	}
}
