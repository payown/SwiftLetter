<?php

namespace SwiftLetter\TTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\Settings\Encryption;

class TTSService {

	private TTSProviderInterface $provider;

	public function __construct() {
		$encrypted_key = get_option( 'swl_openai_key', '' );
		if ( empty( $encrypted_key ) ) {
			throw new \RuntimeException(
				__( 'No OpenAI API key configured. Please add one in SwiftLetter Settings.', 'swiftletter' )
			);
		}

		$api_key = Encryption::decrypt( $encrypted_key );
		if ( $api_key === false ) {
			throw new \RuntimeException(
				__( 'Failed to decrypt OpenAI API key. Please re-enter it in Settings.', 'swiftletter' )
			);
		}

		$this->provider = new OpenAITTSProvider( $api_key );
	}

	public function list_voices(): array {
		return $this->provider->list_voices();
	}

	/**
	 * Generate audio for an article and save it.
	 */
	public function generate_for_article( \WP_Post $article, string $voice ): string {
		$text = $this->extract_plain_text( $article );

		$upload_dir = wp_upload_dir();
		$output_dir = $upload_dir['basedir'] . '/swiftletter/audio';
		if ( ! is_dir( $output_dir ) ) {
			wp_mkdir_p( $output_dir );
		}

		$file_path = $output_dir . '/article-' . $article->ID . '.mp3';

		$this->provider->generate_speech( $text, $voice, $file_path );

		return $file_path;
	}

	/**
	 * Generate combined audio for all articles in a newsletter and save it.
	 *
	 * @param \WP_Post   $newsletter
	 * @param \WP_Post[] $articles   Ordered array of article posts.
	 * @param string     $voice
	 * @return string Saved file path.
	 */
	public function generate_for_newsletter( \WP_Post $newsletter, array $articles, string $voice ): string {
		$parts = [ $newsletter->post_title . '.' ];
		foreach ( $articles as $article ) {
			$parts[] = 'Article: ' . $this->extract_plain_text( $article );
		}
		$text = implode( ' ', $parts );

		$upload_dir = wp_upload_dir();
		$output_dir = $upload_dir['basedir'] . '/swiftletter/audio';
		if ( ! is_dir( $output_dir ) ) {
			wp_mkdir_p( $output_dir );
		}

		$file_path = $output_dir . '/newsletter-' . $newsletter->ID . '.mp3';

		$this->provider->generate_speech( $text, $voice, $file_path );

		return $file_path;
	}

	/**
	 * Generate speech and return raw audio data (for preview).
	 */
	public function generate_speech_data( string $text, string $voice ): string {
		return $this->provider->generate_speech_data( $text, $voice );
	}

	/**
	 * Extract plain text from block content for TTS.
	 */
	public function extract_plain_text( \WP_Post $post ): string {
		$content = $post->post_content;

		// Render blocks to HTML.
		$html = do_blocks( $content );
		$html = do_shortcode( $html );

		// Strip HTML tags, keeping text.
		$text = wp_strip_all_tags( $html );

		// Clean up whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text );

		// Prepend article title.
		$text = $post->post_title . '. ' . $text;

		return $text;
	}
}
