<?php

namespace SwiftLetter\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OpenAIProvider implements AIProviderInterface {

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
		$client = \OpenAI::client( $this->api_key );

		$response = $client->chat()->create( [
			'model'    => 'gpt-4o',
			'messages' => [
				[
					'role'    => 'system',
					'content' => self::SYSTEM_PROMPT,
				],
				[
					'role'    => 'user',
					'content' => sprintf(
						"Article title: %s\n\nArticle content:\n%s",
						$context,
						$content
					),
				],
			],
			'temperature' => 0.3,
			'max_tokens'  => 4096,
		] );

		$refined = $response->choices[0]->message->content ?? '';

		if ( empty( $refined ) ) {
			throw new \RuntimeException(
				__( 'AI returned empty response. Please try again.', 'swiftletter' )
			);
		}

		return $refined;
	}
}
