<?php

namespace SwiftLetter\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Encryption {

	/**
	 * Encrypt a plaintext string using libsodium.
	 */
	public static function encrypt( string $plaintext ): string {
		$key   = self::derive_key();
		$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );

		$ciphertext = sodium_crypto_secretbox( $plaintext, $nonce, $key );
		sodium_memzero( $key );

		return base64_encode( $nonce . $ciphertext );
	}

	/**
	 * Decrypt an encrypted string. Returns false on failure.
	 */
	public static function decrypt( string $encoded ): string|false {
		$decoded = base64_decode( $encoded, true );
		if ( $decoded === false ) {
			return false;
		}

		$nonce_length = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
		if ( strlen( $decoded ) < $nonce_length ) {
			return false;
		}

		$nonce      = substr( $decoded, 0, $nonce_length );
		$ciphertext = substr( $decoded, $nonce_length );
		$key        = self::derive_key();

		$plaintext = sodium_crypto_secretbox_open( $ciphertext, $nonce, $key );
		sodium_memzero( $key );

		return $plaintext;
	}

	/**
	 * Derive a 32-byte encryption key from WordPress salts.
	 */
	private static function derive_key(): string {
		$material = ( defined( 'AUTH_KEY' ) ? AUTH_KEY : 'swl-default-key' )
			. ( defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'swl-default-secure' );

		return hash( 'sha256', $material, true );
	}
}
