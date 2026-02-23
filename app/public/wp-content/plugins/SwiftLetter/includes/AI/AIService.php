<?php

namespace SwiftLetter\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\Settings\Encryption;

class AIService {

	public function refine( string $content, string $context ): string {
		$provider = $this->get_provider();
		return $provider->refine( $content, $context );
	}

	public function generate_alt_text( string $article_title ): string {
		$provider = $this->get_provider();
		// Pass a special instruction as the "content" so the AI returns only alt text.
		$instruction = 'Generate concise, descriptive alt text (maximum 15 words) for an image embedded in a newsletter article. Return ONLY the alt text — no quotes, no explanation, no punctuation at the end.';
		$result      = $provider->refine( $instruction, 'Article title: ' . $article_title );
		return trim( $result );
	}

	private function get_provider(): AIProviderInterface {
		$active = get_option( 'swl_active_ai', 'openai' );

		$key_option = match ( $active ) {
			'claude' => 'swl_claude_key',
			default  => 'swl_openai_key',
		};

		$encrypted_key = get_option( $key_option, '' );
		if ( empty( $encrypted_key ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: provider name */
					__( 'No API key configured for %s. Please add one in SwiftLetter Settings.', 'swiftletter' ),
					ucfirst( $active )
				)
			);
		}

		$api_key = Encryption::decrypt( $encrypted_key );
		if ( $api_key === false ) {
			throw new \RuntimeException(
				__( 'Failed to decrypt API key. Please re-enter it in SwiftLetter Settings.', 'swiftletter' )
			);
		}

		return match ( $active ) {
			'claude' => new ClaudeProvider( $api_key ),
			default  => new OpenAIProvider( $api_key ),
		};
	}
}
