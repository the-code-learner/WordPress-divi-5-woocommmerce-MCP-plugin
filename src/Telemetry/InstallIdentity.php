<?php
/**
 * Local telemetry installation identity.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Telemetry;

final class InstallIdentity {
	private const OPTION_INSTALL_ID = 'mcp_bridge_telemetry_install_id';

	public static function get(): string {
		$stored = get_option( self::OPTION_INSTALL_ID, '' );

		if ( is_string( $stored ) && self::is_valid( $stored ) ) {
			return $stored;
		}

		$generated = self::generate();

		if ( add_option( self::OPTION_INSTALL_ID, $generated, '', false ) ) {
			return $generated;
		}

		$stored = get_option( self::OPTION_INSTALL_ID, '' );

		if ( is_string( $stored ) && self::is_valid( $stored ) ) {
			return $stored;
		}

		update_option( self::OPTION_INSTALL_ID, $generated, false );

		return $generated;
	}

	public static function generate(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	public static function is_valid( string $install_id ): bool {
		return 1 === preg_match( '/^[a-f0-9]{32}$/', $install_id );
	}

	private function __construct() {
	}
}
