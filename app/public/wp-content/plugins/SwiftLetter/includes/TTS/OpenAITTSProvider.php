<?php

namespace SwiftLetter\TTS;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\Settings\Encryption;

class OpenAITTSProvider implements TTSProviderInterface {

	private string $api_key;

	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	public function list_voices(): array {
		return [
			[ 'id' => 'alloy',   'name' => 'Alloy' ],
			[ 'id' => 'ash',     'name' => 'Ash' ],
			[ 'id' => 'ballad',  'name' => 'Ballad' ],
			[ 'id' => 'cedar',   'name' => 'Cedar' ],
			[ 'id' => 'coral',   'name' => 'Coral' ],
			[ 'id' => 'echo',    'name' => 'Echo' ],
			[ 'id' => 'fable',   'name' => 'Fable' ],
			[ 'id' => 'marin',   'name' => 'Marin' ],
			[ 'id' => 'nova',    'name' => 'Nova' ],
			[ 'id' => 'onyx',    'name' => 'Onyx' ],
			[ 'id' => 'sage',    'name' => 'Sage' ],
			[ 'id' => 'shimmer', 'name' => 'Shimmer' ],
			[ 'id' => 'verse',   'name' => 'Verse' ],
		];
	}

	public function generate_speech( string $text, string $voice, string $output_path ): bool {
		$data = $this->call_api( $text, $voice );

		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$result = $wp_filesystem->put_contents( $output_path, $data, FS_CHMOD_FILE );
		if ( ! $result ) {
			throw new \RuntimeException( __( 'Failed to save audio file.', 'swiftletter' ) );
		}

		return true;
	}

	public function generate_speech_data( string $text, string $voice ): string {
		return $this->call_api( $text, $voice );
	}

	private function call_api( string $text, string $voice ): string {
		$body = wp_json_encode( [
			'model'           => 'gpt-4o-mini-tts',
			'input'           => $text,
			'voice'           => $voice,
			'response_format' => 'mp3',
		] );

		$response = wp_remote_post( 'https://api.openai.com/v1/audio/speech', [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => $body,
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'TTS API request failed: %s', 'swiftletter' ),
					$response->get_error_message()
				)
			);
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status !== 200 ) {
			$error_data = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_msg  = $error_data['error']['message'] ?? __( 'Unknown TTS error', 'swiftletter' );
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: error message */
					__( 'TTS API error: %s', 'swiftletter' ),
					$error_msg
				)
			);
		}

		$audio = wp_remote_retrieve_body( $response );

		if ( empty( $audio ) ) {
			throw new \RuntimeException( __( 'TTS API returned empty audio.', 'swiftletter' ) );
		}

		return $audio;
	}
}
