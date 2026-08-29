<?php
/**
 * Telemetry client tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Telemetry\Client;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase {
	public function test_opt_out_performs_zero_http_calls(): void {
		$http_calls = 0;
		$transport  = static function () use ( &$http_calls ): void {
			++$http_calls;
		};
		$disabled = static function (): bool {
			return false;
		};
		$encoder = static function ( array $payload ): string {
			return (string) json_encode( $payload );
		};
		$client = new Client( $transport, $disabled, $disabled, $encoder );

		self::assertFalse( $client->send_heartbeat( array( 'install_id' => 'abc' ) ) );
		self::assertFalse( $client->send_error( array( 'install_id' => 'abc' ) ) );
		self::assertSame( 0, $http_calls );
	}

	public function test_payload_allowlists_drop_site_and_user_data(): void {
		$payload = array(
			'install_id'     => '0123456789abcdef0123456789abcdef',
			'plugin_version' => '0.1.2',
			'wp_version'     => '7.1',
			'php_version'    => '8.3',
			'site_url'       => 'https://example.com',
			'home_url'       => 'https://example.com',
			'admin_email'    => 'admin@example.com',
			'user_id'        => 7,
			'content'        => 'private content',
			'password'       => 'secret',
			'token'          => 'secret-token',
		);

		$filtered = Client::filter_heartbeat_payload( $payload );

		self::assertSame(
			array( 'install_id', 'plugin_version', 'wp_version', 'php_version' ),
			array_keys( $filtered )
		);
	}

	public function test_http_dispatch_is_non_blocking_and_short_timeout(): void {
		$captured = array();
		$transport = static function ( string $url, array $args ) use ( &$captured ): void {
			$captured = array( $url, $args );
		};
		$enabled = static function (): bool {
			return true;
		};
		$encoder = static function ( array $payload ): string {
			return (string) json_encode( $payload );
		};
		$client = new Client( $transport, $enabled, $enabled, $encoder );

		self::assertTrue(
			$client->send_heartbeat(
				array(
					'install_id'     => '0123456789abcdef0123456789abcdef',
					'plugin_version' => '0.1.2',
				)
			)
		);
		self::assertSame( false, $captured[1]['blocking'] );
		self::assertSame( 2, $captured[1]['timeout'] );
		self::assertSame( 0, $captured[1]['redirection'] );
	}
}
