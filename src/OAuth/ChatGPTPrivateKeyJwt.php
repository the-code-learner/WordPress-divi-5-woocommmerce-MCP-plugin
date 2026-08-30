<?php
/**
 * ChatGPT private_key_jwt token-endpoint authentication.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\OAuth;

final class ChatGPTPrivateKeyJwt {
	private const ASSERTION_TYPE          = 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer';
	private const JWKS_URL                = 'https://chatgpt.com/oauth/jwks.json';
	private const JWKS_CACHE_KEY          = 'divi5_wc_mcp_chatgpt_jwks_v1';
	private const MAX_ASSERTION_LIFETIME = 600;
	private const CLOCK_SKEW              = 60;

	/**
	 * Register a token-endpoint preflight before the upstream OAuth router.
	 */
	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'verify_token_request' ), 0 );
	}

	/**
	 * Verify a ChatGPT private_key_jwt assertion when one is supplied.
	 *
	 * Public clients using token_endpoint_auth_method=none continue directly to
	 * the upstream token endpoint. If a client assertion is present, it must be a
	 * valid ChatGPT RS256 assertion before the one-time authorization code or
	 * refresh token can be consumed upstream.
	 */
	public static function verify_token_request(): void {
		if ( 'token' !== (string) get_query_var( 'mcp_oauth_endpoint', '' ) ) {
			return;
		}

		$request_method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '';
		if ( 'POST' !== $request_method ) {
			return;
		}

		$body      = self::request_body();
		$assertion = isset( $body['client_assertion'] ) && is_string( $body['client_assertion'] ) ? $body['client_assertion'] : '';

		if ( '' === $assertion ) {
			return;
		}

		$assertion_type = isset( $body['client_assertion_type'] ) && is_string( $body['client_assertion_type'] ) ? $body['client_assertion_type'] : '';
		if ( self::ASSERTION_TYPE !== $assertion_type ) {
			self::send_invalid_client();
		}

		$client_id = self::unverified_client_id( $assertion );
		if ( null === $client_id || ! ChatGPTCimdCompatibility::is_chatgpt_cimd_url( $client_id ) ) {
			self::send_invalid_client();
		}

		$body_client_id = isset( $body['client_id'] ) && is_string( $body['client_id'] ) ? $body['client_id'] : '';
		if ( '' !== $body_client_id && ! hash_equals( $client_id, $body_client_id ) ) {
			self::send_invalid_client();
		}

		$kid = self::unverified_kid( $assertion );
		if ( null === $kid ) {
			self::send_invalid_client();
		}

		$jwks = self::chatgpt_jwks( $kid );
		if ( null === $jwks ) {
			self::send_invalid_client();
		}

		$audience = rtrim( home_url(), '/' ) . '/oauth/token';
		if ( ! self::verify_assertion( $assertion, $client_id, $audience, $jwks, time() ) ) {
			self::send_invalid_client();
		}
	}

	/**
	 * Verify an RS256 client assertion against a supplied JWKS.
	 *
	 * This method is side-effect free so the cryptographic and claim checks can
	 * be regression-tested without a WordPress runtime.
	 *
	 * @param array<string, mixed> $jwks JWKS document.
	 */
	public static function verify_assertion( string $assertion, string $client_id, string $audience, array $jwks, int $now ): bool {
		$parts = explode( '.', $assertion );
		if ( 3 !== count( $parts ) ) {
			return false;
		}

		$header_json  = self::base64url_decode( $parts[0] );
		$payload_json = self::base64url_decode( $parts[1] );
		$signature    = self::base64url_decode( $parts[2] );

		if ( null === $header_json || null === $payload_json || null === $signature ) {
			return false;
		}

		$header  = json_decode( $header_json, true );
		$payload = json_decode( $payload_json, true );
		if ( ! is_array( $header ) || ! is_array( $payload ) ) {
			return false;
		}

		if ( 'RS256' !== (string) ( $header['alg'] ?? '' ) ) {
			return false;
		}

		$kid = (string) ( $header['kid'] ?? '' );
		if ( '' === $kid ) {
			return false;
		}

		$jwk = self::find_rsa_signing_key( $jwks, $kid );
		if ( null === $jwk ) {
			return false;
		}

		$pem = self::rsa_jwk_to_pem( $jwk );
		if ( null === $pem || ! function_exists( 'openssl_verify' ) ) {
			return false;
		}

		$verified = openssl_verify( $parts[0] . '.' . $parts[1], $signature, $pem, OPENSSL_ALGO_SHA256 );
		if ( 1 !== $verified ) {
			return false;
		}

		if ( ! hash_equals( $client_id, (string) ( $payload['iss'] ?? '' ) ) || ! hash_equals( $client_id, (string) ( $payload['sub'] ?? '' ) ) ) {
			return false;
		}

		$aud = $payload['aud'] ?? null;
		if ( is_string( $aud ) ) {
			$audience_matches = hash_equals( $audience, $aud );
		} elseif ( is_array( $aud ) ) {
			$audience_matches = in_array( $audience, $aud, true );
		} else {
			$audience_matches = false;
		}

		if ( ! $audience_matches ) {
			return false;
		}

		$exp = isset( $payload['exp'] ) && is_numeric( $payload['exp'] ) ? (int) $payload['exp'] : 0;
		if ( 0 === $exp || $exp < ( $now - self::CLOCK_SKEW ) || $exp > ( $now + self::MAX_ASSERTION_LIFETIME ) ) {
			return false;
		}

		if ( isset( $payload['nbf'] ) && is_numeric( $payload['nbf'] ) && (int) $payload['nbf'] > ( $now + self::CLOCK_SKEW ) ) {
			return false;
		}

		if ( isset( $payload['iat'] ) && is_numeric( $payload['iat'] ) && (int) $payload['iat'] > ( $now + self::CLOCK_SKEW ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Extract and minimally constrain the unverified issuer used only to select
	 * the fixed ChatGPT JWKS endpoint. Trust is established only after signature
	 * verification in verify_assertion().
	 */
	public static function unverified_client_id( string $assertion ): ?string {
		$parts = explode( '.', $assertion );
		if ( 3 !== count( $parts ) ) {
			return null;
		}

		$payload_json = self::base64url_decode( $parts[1] );
		if ( null === $payload_json ) {
			return null;
		}

		$payload = json_decode( $payload_json, true );
		if ( ! is_array( $payload ) ) {
			return null;
		}

		$iss = (string) ( $payload['iss'] ?? '' );
		$sub = (string) ( $payload['sub'] ?? '' );

		return '' !== $iss && hash_equals( $iss, $sub ) ? $iss : null;
	}

	private static function unverified_kid( string $assertion ): ?string {
		$parts = explode( '.', $assertion );
		if ( 3 !== count( $parts ) ) {
			return null;
		}

		$header_json = self::base64url_decode( $parts[0] );
		if ( null === $header_json ) {
			return null;
		}

		$header = json_decode( $header_json, true );
		if ( ! is_array( $header ) || 'RS256' !== (string) ( $header['alg'] ?? '' ) ) {
			return null;
		}

		$kid = (string) ( $header['kid'] ?? '' );
		return '' !== $kid ? $kid : null;
	}

	/**
	 * Parse either standard OAuth form encoding or JSON without modifying the
	 * assertion bytes before signature verification.
	 *
	 * @return array<string, mixed>
	 */
	private static function request_body(): array {
		$content_type = isset( $_SERVER['CONTENT_TYPE'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) ) ) : '';

		if ( false !== strpos( $content_type, 'application/json' ) ) {
			$raw  = substr( (string) file_get_contents( 'php://input' ), 0, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- token endpoint request body is not a filesystem path.
			$body = json_decode( '' !== $raw ? $raw : '{}', true );
			return is_array( $body ) ? $body : array();
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- OAuth token endpoint requests use client authentication and grant credentials, not WordPress nonces.
		$body = wp_unslash( $_POST );
		return is_array( $body ) ? $body : array();
	}

	/**
	 * Retrieve ChatGPT's fixed JWKS, caching it briefly and refreshing once when
	 * the requested kid is not present to tolerate normal key rotation.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function chatgpt_jwks( string $kid ): ?array {
		$cached = get_transient( self::JWKS_CACHE_KEY );
		if ( is_array( $cached ) && null !== self::find_rsa_signing_key( $cached, $kid ) ) {
			return $cached;
		}

		$response = wp_safe_remote_get(
			self::JWKS_URL,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'headers'     => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $decoded ) || null === self::find_rsa_signing_key( $decoded, $kid ) ) {
			return null;
		}

		set_transient( self::JWKS_CACHE_KEY, $decoded, HOUR_IN_SECONDS );
		return $decoded;
	}

	/**
	 * @param array<string, mixed> $jwks JWKS document.
	 * @return array<string, mixed>|null
	 */
	private static function find_rsa_signing_key( array $jwks, string $kid ): ?array {
		$keys = $jwks['keys'] ?? array();
		if ( ! is_array( $keys ) ) {
			return null;
		}

		foreach ( $keys as $key ) {
			if ( ! is_array( $key ) || ! hash_equals( $kid, (string) ( $key['kid'] ?? '' ) ) ) {
				continue;
			}

			if ( 'RSA' !== (string) ( $key['kty'] ?? '' ) ) {
				return null;
			}

			if ( isset( $key['use'] ) && 'sig' !== (string) $key['use'] ) {
				return null;
			}

			if ( isset( $key['alg'] ) && 'RS256' !== (string) $key['alg'] ) {
				return null;
			}

			return $key;
		}

		return null;
	}

	/**
	 * Convert an RSA JWK into a SubjectPublicKeyInfo PEM for OpenSSL.
	 *
	 * @param array<string, mixed> $jwk RSA JWK.
	 */
	private static function rsa_jwk_to_pem( array $jwk ): ?string {
		$n = isset( $jwk['n'] ) && is_string( $jwk['n'] ) ? self::base64url_decode( $jwk['n'] ) : null;
		$e = isset( $jwk['e'] ) && is_string( $jwk['e'] ) ? self::base64url_decode( $jwk['e'] ) : null;

		// Require a minimum 2048-bit RSA modulus for RS256 client authentication.
		if ( null === $n || null === $e || strlen( $n ) < 256 || '' === $e ) {
			return null;
		}

		$rsa_body             = self::asn1_integer( $n ) . self::asn1_integer( $e );
		$rsa_key              = "\x30" . self::asn1_length( strlen( $rsa_body ) ) . $rsa_body;
		$algorithm_identifier = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
		$bit_string           = "\x03" . self::asn1_length( strlen( $rsa_key ) + 1 ) . "\x00" . $rsa_key;
		$spki_body            = $algorithm_identifier . $bit_string;
		$spki                 = "\x30" . self::asn1_length( strlen( $spki_body ) ) . $spki_body;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- DER public-key bytes must be base64-encoded for standard PEM serialization; no executable code is encoded.
		return "-----BEGIN PUBLIC KEY-----\n" . chunk_split( base64_encode( $spki ), 64, "\n" ) . "-----END PUBLIC KEY-----\n";
	}

	private static function asn1_integer( string $bytes ): string {
		$bytes = ltrim( $bytes, "\x00" );
		if ( '' === $bytes ) {
			$bytes = "\x00";
		}
		if ( 0 !== ( ord( $bytes[0] ) & 0x80 ) ) {
			$bytes = "\x00" . $bytes;
		}

		return "\x02" . self::asn1_length( strlen( $bytes ) ) . $bytes;
	}

	private static function asn1_length( int $length ): string {
		if ( $length <= 0x7f ) {
			return chr( $length );
		}

		$encoded = '';
		while ( $length > 0 ) {
			$encoded  = chr( $length & 0xff ) . $encoded;
			$length >>= 8;
		}

		return chr( 0x80 | strlen( $encoded ) ) . $encoded;
	}

	private static function base64url_decode( string $value ): ?string {
		$remainder = strlen( $value ) % 4;
		if ( 0 !== $remainder ) {
			$value .= str_repeat( '=', 4 - $remainder );
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- JOSE/JWK values use base64url encoding by specification; this decodes data, not executable code.
		$decoded = base64_decode( strtr( $value, '-_', '+/' ), true );
		return false === $decoded ? null : $decoded;
	}

	private static function send_invalid_client(): void {
		nocache_headers();
		wp_send_json(
			array(
				'error'             => 'invalid_client',
				'error_description' => 'Client authentication failed.',
			),
			400
		);
	}

	private function __construct() {
	}
}
