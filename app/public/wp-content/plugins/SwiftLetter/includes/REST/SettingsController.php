<?php

namespace SwiftLetter\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\Settings\Encryption;

class SettingsController extends \WP_REST_Controller {

	protected $namespace = 'swiftletter/v1';
	protected $rest_base = 'settings';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/test-api-key', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_api_key' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'provider' => [
						'required'          => true,
						'type'              => 'string',
						'enum'              => [ 'openai', 'claude' ],
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	public function permissions_check( $request ): bool {
		return current_user_can( 'manage_options' );
	}

	public function test_api_key( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$provider   = $request->get_param( 'provider' );
		$key_option = ( $provider === 'claude' ) ? 'swl_claude_key' : 'swl_openai_key';

		$encrypted = get_option( $key_option, '' );

		if ( empty( $encrypted ) ) {
			return rest_ensure_response( [
				'ok'      => false,
				'message' => __( 'No API key saved. Please enter and save a key first.', 'swiftletter' ),
			] );
		}

		$api_key = Encryption::decrypt( $encrypted );

		if ( $api_key === false ) {
			return rest_ensure_response( [
				'ok'      => false,
				'message' => __( 'Failed to decrypt the stored key. Please re-enter it in settings.', 'swiftletter' ),
			] );
		}

		if ( $provider === 'claude' ) {
			return $this->test_claude( $api_key );
		}

		return $this->test_openai( $api_key );
	}

	private function test_openai( string $api_key ): \WP_REST_Response {
		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			[
				'timeout' => 15,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response( [
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Connection error: %s', 'swiftletter' ),
					$response->get_error_message()
				),
			] );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 200 ) {
			return rest_ensure_response( [
				'ok'      => true,
				'message' => __( 'OpenAI key is valid.', 'swiftletter' ),
			] );
		}

		if ( $code === 401 ) {
			return rest_ensure_response( [
				'ok'      => false,
				'message' => __( 'Invalid API key. Check your OpenAI key and try again.', 'swiftletter' ),
			] );
		}

		return rest_ensure_response( [
			'ok'      => false,
			/* translators: %d: HTTP status code */
			'message' => sprintf( __( 'Unexpected response from OpenAI (HTTP %d).', 'swiftletter' ), $code ),
		] );
	}

	private function test_claude( string $api_key ): \WP_REST_Response {
		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			[
				'timeout' => 15,
				'headers' => [
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'content-type'      => 'application/json',
				],
				'body'    => wp_json_encode( [
					'model'      => 'claude-haiku-20240307',
					'max_tokens' => 1,
					'messages'   => [
						[
							'role'    => 'user',
							'content' => 'Hi',
						],
					],
				] ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return rest_ensure_response( [
				'ok'      => false,
				'message' => sprintf(
					/* translators: %s: error message */
					__( 'Connection error: %s', 'swiftletter' ),
					$response->get_error_message()
				),
			] );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 200 ) {
			return rest_ensure_response( [
				'ok'      => true,
				'message' => __( 'Claude key is valid.', 'swiftletter' ),
			] );
		}

		if ( $code === 401 ) {
			return rest_ensure_response( [
				'ok'      => false,
				'message' => __( 'Invalid API key. Check your Claude key and try again.', 'swiftletter' ),
			] );
		}

		return rest_ensure_response( [
			'ok'      => false,
			/* translators: %d: HTTP status code */
			'message' => sprintf( __( 'Unexpected response from Anthropic (HTTP %d).', 'swiftletter' ), $code ),
		] );
	}
}
