<?php

namespace SwiftLetter\TTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface TTSProviderInterface {

	/**
	 * List available voices.
	 *
	 * @return array<array{id: string, name: string}>
	 */
	public function list_voices(): array;

	/**
	 * Generate speech audio and save to file.
	 *
	 * @param string $text       The text to speak.
	 * @param string $voice      The voice ID.
	 * @param string $output_path Full filesystem path for the output file.
	 * @return bool True on success.
	 * @throws \RuntimeException On failure.
	 */
	public function generate_speech( string $text, string $voice, string $output_path ): bool;

	/**
	 * Generate speech audio and return raw bytes.
	 *
	 * @param string $text  The text to speak.
	 * @param string $voice The voice ID.
	 * @return string Raw audio data.
	 * @throws \RuntimeException On failure.
	 */
	public function generate_speech_data( string $text, string $voice ): string;
}
