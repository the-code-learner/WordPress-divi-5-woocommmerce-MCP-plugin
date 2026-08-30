<?php
/**
 * ChatGPT Client ID Metadata Document compatibility.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\OAuth;

final class ChatGPTCimdCompatibility {
	private const STABLE_CLIENT_ID = 'https://chatgpt.com/oauth/client.json';

	/**
	 * Register compatibility hooks before the upstream OAuth server starts.
	 */
	public static function register(): void {
		add_filter( 'http_response', array( self::class, 'normalize_cimd_response' ), 10, 3 );
		add_filter( 'wpmedia_mcp_oauth_trusted_publishers', array( self::class, 'add_trusted_publisher' ) );
	}

	/**
	 * Normalize ChatGPT's preferred token authentication method to the common
	 * public-client method when its CIMD explicitly advertises both choices.
	 *
	 * ChatGPT currently publishes `private_key_jwt` as its preferred singular
	 * method while also advertising `none` in token_endpoint_auth_methods_supported.
	 * This server advertises only `none`, so the negotiated intersection is
	 * `none`. The pinned upstream OAuth package currently validates only the
	 * singular field; normalizing this one field lets its existing validation,
	 * redirect binding, PKCE, SSRF protections, and token flow remain unchanged.
	 *
	 * @param mixed  $response WordPress HTTP response.
	 * @param mixed  $args     HTTP request arguments.
	 * @param string $url      Requested CIMD URL.
	 * @return mixed
	 */
	public static function normalize_cimd_response( $response, $args, string $url ) {
		unset( $args );

		if ( ! self::is_chatgpt_cimd_url( $url ) || ! is_array( $response ) ) {
			return $response;
		}

		$status = isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
		$body   = $response['body'] ?? null;

		if ( 200 !== $status || ! is_string( $body ) || '' === $body ) {
			return $response;
		}

		$document = json_decode( $body, true );
		if ( ! is_array( $document ) ) {
			return $response;
		}

		// Preserve CIMD self-binding: never normalize metadata for another client ID.
		if ( (string) ( $document['client_id'] ?? '' ) !== $url ) {
			return $response;
		}

		$methods = $document['token_endpoint_auth_methods_supported'] ?? array();
		if ( ! is_array( $methods ) || ! in_array( 'none', $methods, true ) ) {
			return $response;
		}

		$preferred = (string) ( $document['token_endpoint_auth_method'] ?? 'none' );
		if ( 'none' === $preferred ) {
			return $response;
		}

		$document['token_endpoint_auth_method'] = 'none';
		$normalized                             = json_encode( $document, JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- deterministic JSON encoding keeps this compatibility helper independently unit-testable; the document is decoded and re-encoded without adding HTML-sensitive content.

		if ( false !== $normalized ) {
			$response['body'] = $normalized;
		}

		return $response;
	}

	/**
	 * Mark the stable ChatGPT CIMD endpoint as a verified publisher.
	 *
	 * Callback-specific ChatGPT client IDs remain subject to the upstream
	 * unverified-client consent path and all CIMD validation.
	 *
	 * @param array<string, array<string, mixed>> $publishers Trusted publishers.
	 * @return array<string, array<string, mixed>>
	 */
	public static function add_trusted_publisher( array $publishers ): array {
		$publishers['chatgpt'] = array(
			'client_ids' => array( self::STABLE_CLIENT_ID ),
			'host'       => 'chatgpt.com',
		);

		return $publishers;
	}

	/**
	 * Accept only ChatGPT-hosted CIMD paths, not arbitrary chatgpt.com requests.
	 */
	public static function is_chatgpt_cimd_url( string $url ): bool {
		$parts = parse_url( $url ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure URL shape validation is intentionally independent of WordPress runtime for unit coverage.
		if ( ! is_array( $parts ) ) {
			return false;
		}

		if ( 'https' !== strtolower( (string) ( $parts['scheme'] ?? '' ) ) ) {
			return false;
		}

		if ( 'chatgpt.com' !== strtolower( (string) ( $parts['host'] ?? '' ) ) ) {
			return false;
		}

		$path = (string) ( $parts['path'] ?? '' );

		return 1 === preg_match( '#^/oauth/(?:client|[^/]+/client)\.json$#', $path );
	}

	private function __construct() {
	}
}
