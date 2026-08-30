<?php
/**
 * OAuth compatibility tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\OAuth\Bootstrap;
use CodeLearner\Divi5WooCommerceMCP\OAuth\Discovery;
use PHPUnit\Framework\TestCase;

final class OAuthCompatibilityTest extends TestCase {
	public function test_https_site_detection(): void {
		self::assertTrue( Bootstrap::is_https_url( 'https://example.com' ) );
		self::assertTrue( Bootstrap::is_https_url( 'HTTPS://example.com/path' ) );
		self::assertFalse( Bootstrap::is_https_url( 'http://example.com' ) );
		self::assertFalse( Bootstrap::is_https_url( 'example.com' ) );
	}

	public function test_authorization_server_metadata_supports_chatgpt_refresh_flow(): void {
		$metadata = Discovery::authorization_server_metadata( 'https://example.com/' );

		self::assertSame( 'https://example.com', $metadata['issuer'] );
		self::assertSame( 'https://example.com/oauth/authorize', $metadata['authorization_endpoint'] );
		self::assertSame( 'https://example.com/oauth/token', $metadata['token_endpoint'] );
		self::assertSame( 'https://example.com/oauth/revoke', $metadata['revocation_endpoint'] );
		self::assertContains( 'authorization_code', $metadata['grant_types_supported'] );
		self::assertContains( 'refresh_token', $metadata['grant_types_supported'] );
		self::assertContains( 'S256', $metadata['code_challenge_methods_supported'] );
		self::assertContains( 'mcp', $metadata['scopes_supported'] );
		self::assertContains( 'offline_access', $metadata['scopes_supported'] );
		self::assertSame( array( 'none' ), $metadata['token_endpoint_auth_methods_supported'] );
		self::assertTrue( $metadata['client_id_metadata_document_supported'] );
		self::assertTrue( $metadata['authorization_response_iss_parameter_supported'] );
	}
}
