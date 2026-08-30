<?php
/**
 * Plugin activation state tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace {
	if ( ! class_exists( 'WP_Error' ) ) {
		final class WP_Error {
			private string $code;

			public function __construct( string $code ) {
				$this->code = $code;
			}

			public function get_error_code(): string {
				return $this->code;
			}
		}
	}

	if ( ! function_exists( 'is_multisite' ) ) {
		function is_multisite(): bool {
			return (bool) ( $GLOBALS['divi5_wc_mcp_test_multisite'] ?? false );
		}
	}

	if ( ! function_exists( 'is_plugin_active' ) ) {
		function is_plugin_active( string $plugin ): bool {
			unset( $plugin );

			return (bool) ( $GLOBALS['divi5_wc_mcp_test_plugin_active'] ?? false );
		}
	}

	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		function is_plugin_active_for_network( string $plugin ): bool {
			unset( $plugin );

			return (bool) ( $GLOBALS['divi5_wc_mcp_test_plugin_network_active'] ?? false );
		}
	}

	if ( ! function_exists( 'activate_plugin' ) ) {
		function activate_plugin( string $plugin, string $redirect = '', bool $network_wide = false, bool $silent = false ) {
			unset( $plugin, $redirect, $silent );

			++$GLOBALS['divi5_wc_mcp_test_activation_calls'];
			$GLOBALS['divi5_wc_mcp_test_activation_network_wide'] = $network_wide;

			if ( isset( $GLOBALS['divi5_wc_mcp_test_activation_error'] ) ) {
				return new WP_Error( (string) $GLOBALS['divi5_wc_mcp_test_activation_error'] );
			}

			if ( ! ( $GLOBALS['divi5_wc_mcp_test_activation_noop'] ?? false ) ) {
				$GLOBALS['divi5_wc_mcp_test_plugin_active']         = true;
				$GLOBALS['divi5_wc_mcp_test_plugin_network_active'] = $network_wide;
			}

			return null;
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $value ): bool {
			return $value instanceof WP_Error;
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( string $key ): string {
			return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) ) ?? '';
		}
	}
}

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit {
	use CodeLearner\Divi5WooCommerceMCP\Updates\PluginActivationState;
	use PHPUnit\Framework\TestCase;

	final class PluginActivationStateTest extends TestCase {
		private const PLUGIN = 'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php';

		protected function setUp(): void {
			$GLOBALS['divi5_wc_mcp_test_multisite']                 = false;
			$GLOBALS['divi5_wc_mcp_test_plugin_active']             = false;
			$GLOBALS['divi5_wc_mcp_test_plugin_network_active']     = false;
			$GLOBALS['divi5_wc_mcp_test_activation_calls']          = 0;
			$GLOBALS['divi5_wc_mcp_test_activation_network_wide']   = null;
			$GLOBALS['divi5_wc_mcp_test_activation_noop']           = false;
			unset( $GLOBALS['divi5_wc_mcp_test_activation_error'] );
		}

		public function test_restores_site_activation_after_update(): void {
			$GLOBALS['divi5_wc_mcp_test_plugin_active'] = true;
			$state = PluginActivationState::capture( self::PLUGIN );

			$GLOBALS['divi5_wc_mcp_test_plugin_active'] = false;

			self::assertSame( '', $state->restore( self::PLUGIN ) );
			self::assertSame( 1, $GLOBALS['divi5_wc_mcp_test_activation_calls'] );
			self::assertFalse( $GLOBALS['divi5_wc_mcp_test_activation_network_wide'] );
			self::assertTrue( $GLOBALS['divi5_wc_mcp_test_plugin_active'] );
		}

		public function test_restores_network_activation_after_update(): void {
			$GLOBALS['divi5_wc_mcp_test_multisite']             = true;
			$GLOBALS['divi5_wc_mcp_test_plugin_active']         = true;
			$GLOBALS['divi5_wc_mcp_test_plugin_network_active'] = true;
			$state = PluginActivationState::capture( self::PLUGIN );

			$GLOBALS['divi5_wc_mcp_test_plugin_active']         = false;
			$GLOBALS['divi5_wc_mcp_test_plugin_network_active'] = false;

			self::assertSame( '', $state->restore( self::PLUGIN ) );
			self::assertSame( 1, $GLOBALS['divi5_wc_mcp_test_activation_calls'] );
			self::assertTrue( $GLOBALS['divi5_wc_mcp_test_activation_network_wide'] );
			self::assertTrue( $GLOBALS['divi5_wc_mcp_test_plugin_network_active'] );
		}

		public function test_does_not_activate_plugin_that_was_inactive(): void {
			$state = PluginActivationState::capture( self::PLUGIN );

			self::assertSame( '', $state->restore( self::PLUGIN ) );
			self::assertSame( 0, $GLOBALS['divi5_wc_mcp_test_activation_calls'] );
		}

		public function test_does_not_reactivate_when_scope_is_unchanged(): void {
			$GLOBALS['divi5_wc_mcp_test_plugin_active'] = true;
			$state = PluginActivationState::capture( self::PLUGIN );

			self::assertSame( '', $state->restore( self::PLUGIN ) );
			self::assertSame( 0, $GLOBALS['divi5_wc_mcp_test_activation_calls'] );
		}

		public function test_returns_sanitized_activation_error(): void {
			$GLOBALS['divi5_wc_mcp_test_plugin_active'] = true;
			$state = PluginActivationState::capture( self::PLUGIN );

			$GLOBALS['divi5_wc_mcp_test_plugin_active']    = false;
			$GLOBALS['divi5_wc_mcp_test_activation_error'] = 'Plugin Invalid!';

			self::assertSame( 'plugin_reactivation_plugininvalid', $state->restore( self::PLUGIN ) );
		}

		public function test_rejects_false_success_when_plugin_remains_inactive(): void {
			$GLOBALS['divi5_wc_mcp_test_plugin_active'] = true;
			$state = PluginActivationState::capture( self::PLUGIN );

			$GLOBALS['divi5_wc_mcp_test_plugin_active']   = false;
			$GLOBALS['divi5_wc_mcp_test_activation_noop'] = true;

			self::assertSame( 'plugin_reactivation_failed', $state->restore( self::PLUGIN ) );
		}
	}
}
