<?php

namespace SwiftLetter\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use SwiftLetter\PostTypes\Article;
use SwiftLetter\Settings\Encryption;
use SwiftLetter\TTS\TTSService;

class TTSController extends \WP_REST_Controller {

	private const ALLOWED_VOICES = [
		'alloy', 'ash', 'ballad', 'cedar', 'coral',
		'echo', 'fable', 'marin', 'nova', 'onyx',
		'sage', 'shimmer', 'verse',
	];

	protected $namespace = 'swiftletter/v1';
	protected $rest_base = 'tts';

	public function register_routes(): void {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/voices', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_voices' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/preview', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'voice' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => self::ALLOWED_VOICES,
					],
					'text'  => [ 'required' => true, 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/articles/(?P<id>[\d]+)/generate-audio', [
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'generate_audio' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'voice' => [
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'enum'              => self::ALLOWED_VOICES,
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/articles/(?P<id>[\d]+)/audio', [
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'serve_audio' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function permissions_check( $request ): bool|\WP_Error {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = isset( $request['id'] ) ? absint( $request['id'] ) : 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'rest_forbidden', __( 'You cannot edit this item.', 'swiftletter' ), [ 'status' => 403 ] );
		}

		return true;
	}

	public function get_voices( $request ): \WP_REST_Response {
		$cached = get_transient( 'swl_tts_voices' );
		if ( $cached !== false ) {
			return new \WP_REST_Response( $cached, 200 );
		}

		$voices = [
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

		set_transient( 'swl_tts_voices', $voices, DAY_IN_SECONDS );

		return new \WP_REST_Response( $voices, 200 );
	}

	public function preview( $request ): \WP_REST_Response|\WP_Error {
		$voice = $request->get_param( 'voice' );
		$text  = substr( $request->get_param( 'text' ), 0, 500 );

		try {
			$tts_service = new TTSService();
			$audio_data  = $tts_service->generate_speech_data( $text, $voice );
		} catch ( \RuntimeException $e ) {
			error_log( 'SwiftLetter TTS preview error: ' . $e->getMessage() );
			return new \WP_Error( 'tts_error', __( 'Text-to-speech preview failed. Please try again.', 'swiftletter' ), [ 'status' => 500 ] );
		}

		return new \WP_REST_Response( [
			'audio' => base64_encode( $audio_data ),
			'type'  => 'audio/mpeg',
		], 200 );
	}

	public function generate_audio( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Article::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Article not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$voice = $request->get_param( 'voice' ) ?: get_option( 'swl_tts_voice', 'coral' );

		try {
			$tts_service = new TTSService();
			$file_path   = $tts_service->generate_for_article( $post, $voice );
		} catch ( \RuntimeException $e ) {
			error_log( 'SwiftLetter TTS generate error: ' . $e->getMessage() );
			return new \WP_Error( 'tts_error', __( 'Audio generation failed. Please try again.', 'swiftletter' ), [ 'status' => 500 ] );
		}

		update_post_meta( $post->ID, '_swl_audio_file_path', $file_path );

		$newsletter_id = (int) get_post_meta( $post->ID, '_swl_newsletter_id', true );
		$audit = new \SwiftLetter\Audit\AuditLog();
		$audit->log( $newsletter_id, $post->ID, 'article_audio_generated', [
			'voice' => $voice,
		] );

		return new \WP_REST_Response( [
			'success'  => true,
			'audio_url' => rest_url( $this->namespace . '/articles/' . $post->ID . '/audio' ),
		], 200 );
	}

	public function serve_audio( $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( $request['id'] );

		if ( ! $post || $post->post_type !== Article::POST_TYPE ) {
			return new \WP_Error( 'not_found', __( 'Article not found.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		$file_path = get_post_meta( $post->ID, '_swl_audio_file_path', true );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return new \WP_Error( 'no_audio', __( 'No audio file available.', 'swiftletter' ), [ 'status' => 404 ] );
		}

		// Validate the file path is within the expected audio directory to prevent path traversal.
		$upload_dir   = wp_upload_dir();
		$allowed_base = realpath( $upload_dir['basedir'] . '/swiftletter/audio' );
		$real_path    = realpath( $file_path );

		if ( ! $allowed_base || ! $real_path || ! str_starts_with( $real_path, $allowed_base . DIRECTORY_SEPARATOR ) ) {
			return new \WP_Error( 'forbidden', __( 'Invalid audio file path.', 'swiftletter' ), [ 'status' => 403 ] );
		}

		$file_size = filesize( $real_path );

		// Return audio as base64-encoded data in a REST-compatible response.
		return new \WP_REST_Response( [
			'audio' => base64_encode( file_get_contents( $real_path ) ),
			'type'  => 'audio/mpeg',
			'size'  => $file_size,
		], 200 );
	}
}
