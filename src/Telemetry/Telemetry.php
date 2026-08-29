<?php
/**
 * Telemetry orchestration, scheduling, privacy disclosure, and fatal error reporting.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Telemetry;

use CodeLearner\Divi5WooCommerceMCP\Admin\Settings;

final class Telemetry {
	public const HEARTBEAT_HOOK = 'mcp_bridge_telemetry_heartbeat';

	private const DAY_IN_SECONDS = 86400;

	private const WEEK_IN_SECONDS = 604800;

	private const FIRST_MAX_DELAY = 259200;

	private const WEEKLY_JITTER = 43200;

	private static ?Client $client = null;

	public static function boot(): void {
		self::$client = new Client();

		add_action( 'init', array( self::class, 'ensure_heartbeat_scheduled' ) );
		add_action( self::HEARTBEAT_HOOK, array( self::class, 'run_heartbeat' ) );
		add_action( 'admin_init', array( self::class, 'add_privacy_policy_content' ) );
		add_action(
			'update_option_' . Settings::OPTION_USAGE_TELEMETRY,
			array( self::class, 'handle_usage_setting_change' ),
			10,
			2
		);

		register_shutdown_function( array( self::class, 'report_fatal_shutdown' ) );
	}

	public static function ensure_heartbeat_scheduled(): void {
		if ( ! Settings::is_usage_telemetry_enabled() ) {
			wp_clear_scheduled_hook( self::HEARTBEAT_HOOK );
			return;
		}

		if ( false !== wp_next_scheduled( self::HEARTBEAT_HOOK ) ) {
			return;
		}

		$delay = self::first_delay_seconds( wp_rand( self::DAY_IN_SECONDS, self::FIRST_MAX_DELAY ) );
		wp_schedule_single_event( time() + $delay, self::HEARTBEAT_HOOK );
	}

	public static function run_heartbeat(): void {
		if ( ! Settings::is_usage_telemetry_enabled() ) {
			wp_clear_scheduled_hook( self::HEARTBEAT_HOOK );
			return;
		}

		if ( self::$client instanceof Client ) {
			self::$client->send_heartbeat( Payload::heartbeat() );
		}

		$random_jitter = wp_rand( 0, self::WEEKLY_JITTER * 2 ) - self::WEEKLY_JITTER;
		$delay         = self::next_delay_seconds( $random_jitter );
		wp_schedule_single_event( time() + $delay, self::HEARTBEAT_HOOK );
	}

	/**
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 */
	public static function handle_usage_setting_change( $old_value, $new_value ): void {
		unset( $old_value );

		if ( empty( $new_value ) ) {
			wp_clear_scheduled_hook( self::HEARTBEAT_HOOK );
			return;
		}

		self::ensure_heartbeat_scheduled();
	}

	public static function first_delay_seconds( int $random_delay ): int {
		return max( self::DAY_IN_SECONDS, min( self::FIRST_MAX_DELAY, $random_delay ) );
	}

	public static function next_delay_seconds( int $jitter ): int {
		$jitter = max( -self::WEEKLY_JITTER, min( self::WEEKLY_JITTER, $jitter ) );

		return self::WEEK_IN_SECONDS + $jitter;
	}

	public static function report_fatal_shutdown(): void {
		if ( ! ( self::$client instanceof Client ) || ! defined( 'DIVI5_WC_MCP_DIR' ) ) {
			return;
		}

		$error = error_get_last();

		if ( ! is_array( $error ) || ! isset( $error['type'], $error['file'] ) || ! is_string( $error['file'] ) ) {
			return;
		}

		if ( ! self::is_fatal_type( (int) $error['type'] ) || ! self::is_plugin_owned_file( $error['file'], DIVI5_WC_MCP_DIR ) ) {
			return;
		}

		self::$client->send_error( Payload::fatal_error( $error ) );
	}

	public static function is_plugin_owned_file( string $file, string $plugin_dir ): bool {
		$file        = str_replace( '\\', '/', $file );
		$plugin_root = rtrim( str_replace( '\\', '/', $plugin_dir ), '/' ) . '/';

		return 0 === strpos( $file, $plugin_root );
	}

	public static function add_privacy_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content  = '<p>' . esc_html__( 'In current pre-WordPress.org GitHub builds, anonymous usage telemetry and automatic plugin error reporting are enabled by default and can each be disabled under Settings > MCP Bridge.', 'mcp-bridge-for-divi-woocommerce' ) . '</p>';
		$content .= '<p>' . esc_html__( 'Usage telemetry sends a random local installation identifier, plugin version, WordPress version, PHP major/minor version, and whether Divi and WooCommerce are detected. Error reports send the same version fields plus a sanitized plugin-owned error class, code, message, up to ten plugin-owned stack frames, and a fingerprint.', 'mcp-bridge-for-divi-woocommerce' ) . '</p>';
		$content .= '<p>' . esc_html__( 'The plugin does not intentionally send the site URL or domain, administrator email, usernames or user IDs, post/page/product/order/customer content, database values, cookies, request bodies, secrets, tokens, or arbitrary plugin/theme lists. Telemetry is sent to the project telemetry service hosted on Cloudflare Workers. Before WordPress.org submission these controls must be changed to explicit opt-in.', 'mcp-bridge-for-divi-woocommerce' ) . '</p>';

		wp_add_privacy_policy_content(
			__( 'MCP Bridge for Divi 5 and WooCommerce', 'mcp-bridge-for-divi-woocommerce' ),
			$content
		);
	}

	private static function is_fatal_type( int $type ): bool {
		return in_array( $type, array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true );
	}

	private function __construct() {
	}
}
