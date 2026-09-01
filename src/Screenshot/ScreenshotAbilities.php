<?php
/**
 * Screenshot diagnostics abilities.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Screenshot;

final class ScreenshotAbilities {
	private const CATEGORY = 'divi5-woocommerce-mcp';

	public static function hooks(): void {
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'divi5-woocommerce-mcp/divi-screenshot-status',
			array(
				'label'               => __( 'Inspect Divi screenshot renderer status', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Probes the bundled Linux x86_64 Chrome Headless Shell renderer, including process execution, localhost CDP, temporary storage, and a minimal real PNG smoke test.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'status' ),
				'permission_callback' => array( self::class, 'can_read_status' ),
				'output_schema'       => self::output_schema(),
				'meta'                => self::mcp_meta(),
			)
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function status(): array {
		$renderer = new BundledChromiumRenderer();
		return $renderer->status( true );
	}

	public static function can_read_status(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function output_schema(): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => true,
			'properties'           => array(
				'engine'              => array( 'type' => 'string' ),
				'engine_version'      => array( 'type' => 'string' ),
				'platform'            => array( 'type' => 'string' ),
				'platform_supported'  => array( 'type' => 'boolean' ),
				'binary_present'      => array( 'type' => 'boolean' ),
				'proc_open_available' => array( 'type' => 'boolean' ),
				'binary_executable'   => array( 'type' => 'boolean' ),
				'temp_writable'       => array( 'type' => 'boolean' ),
				'cdp_available'       => array( 'type' => 'boolean' ),
				'smoke_test'          => array( 'type' => array( 'boolean', 'null' ) ),
				'ready'               => array( 'type' => 'boolean' ),
				'error_code'          => array( 'type' => array( 'string', 'null' ) ),
				'error_message'       => array( 'type' => array( 'string', 'null' ) ),
			),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function mcp_meta(): array {
		return array(
			'annotations' => array(
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			),
			'mcp'         => array(
				'public' => true,
			),
		);
	}

	private function __construct() {
	}
}
