<?php

namespace SwiftLetter\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ClaudeProvider implements AIProviderInterface {

	private const SYSTEM_PROMPT = <<<'PROMPT'
You are an editorial assistant for an accessibility-focused newsletter. Your task is to refine the provided article content according to these strict rules:

1. PRESERVE the original meaning, intent, and facts exactly. Never introduce new information.
2. CORRECT grammar, spelling, and punctuation errors.
3. IMPROVE readability by simplifying overly complex sentences.
4. SHORTEN sentences that are excessively long while keeping their meaning.
5. IMPROVE navigation clarity — ensure headings and structure are logical.
6. DO NOT rewrite creatively or change the author's voice.
7. DO NOT alter the substance or intent of any statement.
8. DO NOT remove or add HTML tags — preserve the existing block structure.
9. Return ONLY the refined content with the same HTML/block markup structure. Do not add explanations.
PROMPT;

	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function refine( string $content, string $context ): string {
		$body = wp_json_encode( [
			'model'      => 'claude-sonnet-4-20250514',
			'max_tokens' => 4096,
			'system'     => self::SYSTEM_PROMPT,
			'messages'   => [
				[
					'role'    => 'user',
					'content' => sprintf(
						"Article title: %s\n\nArticle content:\n%s",
						$context,
						$content
					),
				],
			],
		] );

		$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $this->api_key,
				'anthropic-version' => '2023-06-01',
			],
			'body'    => $body,
			'timeout' => 60,
		] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Claude API request failed: %s', 'swiftletter' ),
					$response->get_error_message()
				)
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		$data   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status !== 200 ) {
			$error_msg = $data['error']['message'] ?? __( 'Unknown API error', 'swiftletter' );
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'Claude API error: %s', 'swiftletter' ),
					$error_msg
				)
			);
		}

		$refined = '';
		foreach ( ( $data['content'] ?? [] ) as $block ) {
			if ( ( $block['type'] ?? '' ) === 'text' ) {
				$refined .= $block['text'];
			}
		}

		if ( empty( $refined ) ) {
			throw new \RuntimeException(
				__( 'AI returned empty response. Please try again.', 'swiftletter' )
			);
		}

		return $refined;
	}
}
