<?php
/**
 * ChatGPT-compatible OAuth discovery metadata.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\OAuth;

use CodeLearner\Divi5WooCommerceMCP\Version;

final class Discovery {
	private const MCP_REST_PATH        = '/wp-json/mcp/mcp-oauth-server';
	private const CACHE_VERSION_OPTION = 'divi5_wc_mcp_oauth_metadata_cache_version';

	/**
	 * Mark every OAuth metadata response as non-cacheable.
	 *
	 * OAuth/MCP discovery is configuration, not page content. A stale public cache
	 * can keep an old authorization method or a previous 404 alive after the plugin
	 * has been updated, which prevents clients from discovering the active server.
	 */
	public static function maybe_disable_metadata_caching(): void {
		if ( ! Bootstrap::is_https_url( home_url() ) || ! self::is_metadata_request() ) {
			return;
		}

		// LiteSpeed Cache can otherwise override normal WordPress cache headers and
		// publicly cache these endpoints. This documented integration hook is a no-op
		// when LiteSpeed Cache is not installed.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache public integration hook.
		do_action( 'litespeed_control_set_nocache', 'MCP OAuth discovery metadata must stay fresh' );
		nocache_headers();
	}

	/**
	 * Purge stale LiteSpeed copies once per plugin release.
	 *
	 * The purge is intentionally URL-scoped and idempotent. It never flushes the
	 * site's general page cache, and does nothing on hosts without LiteSpeed Cache.
	 */
	public static function maybe_purge_metadata_cache(): void {
		if ( ! Bootstrap::is_https_url( home_url() ) ) {
			return;
		}

		if ( Version::NUMBER === (string) get_option( self::CACHE_VERSION_OPTION, '' ) ) {
			return;
		}

		$resource_url = get_rest_url( null, 'mcp/mcp-oauth-server' );

		foreach ( self::metadata_urls( home_url(), $resource_url ) as $url ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- LiteSpeed Cache public integration hook.
			do_action( 'litespeed_purge_url', $url );
		}

		update_option( self::CACHE_VERSION_OPTION, Version::NUMBER, false );
	}

	/**
	 * Serve authorization-server metadata before the upstream OAuth library handler.
	 *
	 * The upstream library already issues and rotates refresh tokens. ChatGPT also
	 * expects refresh capability to be advertised through an offline-access scope,
	 * so this response adds that capability while preserving the upstream endpoints.
	 */
	public static function maybe_serve_authorization_server_metadata(): void {
		if ( ! Bootstrap::is_https_url( home_url() ) ) {
			return;
		}

		if ( 'authorization-server' !== (string) get_query_var( 'mcp_oauth_discovery', '' ) ) {
			return;
		}

		// Pass 200 explicitly. This also makes the status deterministic if WordPress
		// classified an otherwise intercepted well-known request before this handler.
		wp_send_json( self::authorization_server_metadata( home_url(), get_rest_url( null, 'mcp/mcp-oauth-server' ) ), 200 );
	}

	/**
	 * Serve the RFC 9728 path-inserted metadata location for the MCP resource.
	 *
	 * For a resource such as https://example.com/wp-json/mcp/mcp-oauth-server,
	 * RFC 9728 section 3 requires the deterministic discovery URL to be
	 * https://example.com/.well-known/oauth-protected-resource/wp-json/mcp/mcp-oauth-server.
	 * The pinned upstream library currently serves only the host-root variant.
	 */
	public static function maybe_serve_protected_resource_metadata(): void {
		if ( ! Bootstrap::is_https_url( home_url() ) ) {
			return;
		}

		$request_uri   = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path  = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$resource_url  = get_rest_url( null, 'mcp/mcp-oauth-server' );
		$expected_path = self::protected_resource_metadata_path( $resource_url );

		if ( '' === $expected_path || $expected_path !== $request_path ) {
			return;
		}

		// This path is handled directly on template_redirect because the pinned
		// upstream package has no path-inserted rewrite rule. WordPress therefore
		// marks the request as 404 before we intercept it. An explicit 200 is
		// required; otherwise clients receive valid RFC 9728 JSON with HTTP 404.
		wp_send_json( self::protected_resource_metadata( $resource_url, home_url() ), 200 );
	}

	/**
	 * Build RFC 8414 metadata for the embedded OAuth 2.1 authorization server.
	 *
	 * The pinned upstream token endpoint is a PKCE public-client endpoint. ChatGPT
	 * supports `none` through CIMD whenever that method is in the intersection of
	 * client and server metadata, so advertise the method the endpoint actually
	 * implements rather than routing token exchange through a custom assertion
	 * layer.
	 *
	 * @return array<string, mixed>
	 */
	public static function authorization_server_metadata( string $base_url, ?string $resource_url = null ): array {
		$base_url     = rtrim( $base_url, '/' );
		$resource_url = null !== $resource_url && '' !== $resource_url ? $resource_url : $base_url . self::MCP_REST_PATH;

		return array(
			'issuer'                                => $base_url,
			'authorization_endpoint'                => $base_url . '/oauth/authorize',
			'token_endpoint'                        => $base_url . '/oauth/token',
			'revocation_endpoint'                   => $base_url . '/oauth/revoke',
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'scopes_supported'                      => array( 'mcp', 'offline_access' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
			'client_id_metadata_document_supported' => true,
			'authorization_response_iss_parameter_supported' => true,
			'protected_resources'                   => array( $resource_url ),
		);
	}

	/**
	 * Build RFC 9728 metadata for the OAuth-protected MCP resource.
	 *
	 * @return array<string, mixed>
	 */
	public static function protected_resource_metadata( string $resource_url, string $authorization_server ): array {
		return array(
			'resource'                 => $resource_url,
			'authorization_servers'    => array( rtrim( $authorization_server, '/' ) ),
			'bearer_methods_supported' => array( 'header' ),
			'scopes_supported'         => array( 'mcp' ),
		);
	}

	/**
	 * Return the RFC 9728 well-known path formed by inserting the metadata
	 * suffix between the resource URL's authority and resource path.
	 */
	public static function protected_resource_metadata_path( string $resource_url ): string {
		$path = parse_url( $resource_url, PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- pure URL path derivation is intentionally unit-testable without WordPress runtime.
		if ( ! is_string( $path ) || '' === $path || '/' === $path ) {
			return '/.well-known/oauth-protected-resource';
		}

		return '/.well-known/oauth-protected-resource/' . ltrim( $path, '/' );
	}

	/**
	 * Return every metadata URL whose public cache must be purged on upgrade.
	 *
	 * @return string[]
	 */
	public static function metadata_urls( string $base_url, string $resource_url ): array {
		$base_url = rtrim( $base_url, '/' );

		return array(
			$base_url . '/.well-known/oauth-authorization-server',
			$base_url . '/.well-known/oauth-protected-resource',
			$base_url . self::protected_resource_metadata_path( $resource_url ),
		);
	}

	/**
	 * Whether the current request is one of our OAuth discovery documents.
	 */
	private static function is_metadata_request(): bool {
		$discovery = (string) get_query_var( 'mcp_oauth_discovery', '' );
		if ( in_array( $discovery, array( 'authorization-server', 'protected-resource' ), true ) ) {
			return true;
		}

		$request_uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$request_path = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		$expected     = self::protected_resource_metadata_path( get_rest_url( null, 'mcp/mcp-oauth-server' ) );

		return '' !== $expected && $expected === $request_path;
	}

	private function __construct() {
	}
}
