<?php
/**
 * ChatGPT private_key_jwt tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\OAuth\ChatGPTPrivateKeyJwt;
use PHPUnit\Framework\TestCase;

final class ChatGPTPrivateKeyJwtTest extends TestCase {
	private const CLIENT_ID = 'https://chatgpt.com/oauth/client.json';
	private const AUDIENCE  = 'https://example.com/oauth/token';
	private const KID       = 'test-rsa-key';

	public function test_valid_rs256_assertion_is_accepted(): void {
		$fixture   = $this->rsa_fixture();
		$assertion = $this->signed_assertion( $fixture['private_key'], time() );

		self::assertTrue(
			ChatGPTPrivateKeyJwt::verify_assertion(
				$assertion,
				self::CLIENT_ID,
				self::AUDIENCE,
				$fixture['jwks'],
				time()
			)
		);
		self::assertSame( self::CLIENT_ID, ChatGPTPrivateKeyJwt::unverified_client_id( $assertion ) );
	}

	public function test_wrong_audience_is_rejected(): void {
		$fixture   = $this->rsa_fixture();
		$now       = time();
		$assertion = $this->signed_assertion( $fixture['private_key'], $now );

		self::assertFalse(
			ChatGPTPrivateKeyJwt::verify_assertion(
				$assertion,
				self::CLIENT_ID,
				'https://example.com/not-token',
				$fixture['jwks'],
				$now
			)
		);
	}

	public function test_expired_assertion_is_rejected(): void {
		$fixture = $this->rsa_fixture();
		$now     = time();
		$payload = array(
			'iss' => self::CLIENT_ID,
			'sub' => self::CLIENT_ID,
			'aud' => self::AUDIENCE,
			'iat' => $now - 600,
			'exp' => $now - 120,
		);
		$assertion = $this->sign_jwt( $fixture['private_key'], $payload );

		self::assertFalse( ChatGPTPrivateKeyJwt::verify_assertion( $assertion, self::CLIENT_ID, self::AUDIENCE, $fixture['jwks'], $now ) );
	}

	public function test_signature_from_unregistered_key_is_rejected(): void {
		$trusted   = $this->rsa_fixture();
		$attacker  = $this->rsa_fixture();
		$now       = time();
		$assertion = $this->signed_assertion( $attacker['private_key'], $now );

		self::assertFalse( ChatGPTPrivateKeyJwt::verify_assertion( $assertion, self::CLIENT_ID, self::AUDIENCE, $trusted['jwks'], $now ) );
	}

	/**
	 * @return array{private_key: mixed, jwks: array<string, mixed>}
	 */
	private function rsa_fixture(): array {
		$key = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		self::assertNotFalse( $key );

		$details = openssl_pkey_get_details( $key );
		self::assertIsArray( $details );
		self::assertArrayHasKey( 'rsa', $details );

		return array(
			'private_key' => $key,
			'jwks'        => array(
				'keys' => array(
					array(
						'kty' => 'RSA',
						'kid' => self::KID,
						'use' => 'sig',
						'alg' => 'RS256',
						'n'   => $this->base64url_encode( $details['rsa']['n'] ),
						'e'   => $this->base64url_encode( $details['rsa']['e'] ),
					),
				),
			),
		);
	}

	/**
	 * @param mixed $private_key OpenSSL private-key handle/object.
	 */
	private function signed_assertion( $private_key, int $now ): string {
		return $this->sign_jwt(
			$private_key,
			array(
				'iss' => self::CLIENT_ID,
				'sub' => self::CLIENT_ID,
				'aud' => self::AUDIENCE,
				'iat' => $now,
				'exp' => $now + 300,
			)
		);
	}

	/**
	 * @param mixed                $private_key OpenSSL private-key handle/object.
	 * @param array<string, mixed> $payload     JWT payload.
	 */
	private function sign_jwt( $private_key, array $payload ): string {
		$header = array(
			'alg' => 'RS256',
			'kid' => self::KID,
			'typ' => 'JWT',
		);

		$encoded_header  = $this->base64url_encode( (string) json_encode( $header ) );
		$encoded_payload = $this->base64url_encode( (string) json_encode( $payload ) );
		$signing_input   = $encoded_header . '.' . $encoded_payload;
		$signature       = '';

		self::assertTrue( openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) );

		return $signing_input . '.' . $this->base64url_encode( $signature );
	}

	private function base64url_encode( string $value ): string {
		return rtrim( strtr( base64_encode( $value ), '+/', '-_' ), '=' );
	}
}
