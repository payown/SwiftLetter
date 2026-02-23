<?php

namespace SwiftLetter\AI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AIProviderInterface {

	/**
	 * Refine content using AI.
	 *
	 * @param string $content The content to refine (block editor HTML).
	 * @param string $context Context about the content (e.g., article title).
	 * @return string The refined content.
	 * @throws \RuntimeException On API failure.
	 */
	public function refine( string $content, string $context ): string;
}
