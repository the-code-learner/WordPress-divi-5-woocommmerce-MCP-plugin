<?php
/**
 * WordPress Abilities registration.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\WordPress;

use CodeLearner\Divi5WooCommerceMCP\Divi\Detector as DiviDetector;
use CodeLearner\Divi5WooCommerceMCP\Updates\SelfUpdater;
use CodeLearner\Divi5WooCommerceMCP\Version;
use CodeLearner\Divi5WooCommerceMCP\WooCommerce\Detector as WooCommerceDetector;

final class Abilities {
	private const CATEGORY = 'divi5-woocommerce-mcp';

	public static function hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
	}

	public static function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'MCP Bridge for Divi 5 and WooCommerce', 'mcp-bridge-for-divi-woocommerce' ),
				'description' => __( 'Capabilities exposed by MCP Bridge for Divi 5 and WooCommerce.', 'mcp-bridge-for-divi-woocommerce' ),
			)
		);
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'divi5-woocommerce-mcp/get-status',
			array(
				'label'               => __( 'Get integration status', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Returns plugin, Divi 5, and WooCommerce detection status without modifying the site.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'get_status' ),
				'permission_callback' => array( self::class, 'can_get_status' ),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array( 'plugin_version', 'divi_detected', 'woocommerce_detected' ),
					'properties' => array(
						'plugin_version'       => array( 'type' => 'string' ),
						'divi_detected'        => array( 'type' => 'boolean' ),
						'woocommerce_detected' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/get-update-status',
			array(
				'label'               => __( 'Get plugin update status', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Forces a fresh check of the stable GitHub release channel and reports update status for this plugin only.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'get_update_status' ),
				'permission_callback' => array( self::class, 'can_get_status' ),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array(
						'current_version',
						'available_version',
						'update_available',
						'source',
						'release_channel',
						'checked_at',
						'github_updates_enabled',
					),
					'properties' => array(
						'current_version'        => array( 'type' => 'string' ),
						'available_version'      => array( 'type' => array( 'string', 'null' ) ),
						'update_available'       => array( 'type' => 'boolean' ),
						'source'                 => array( 'type' => 'string' ),
						'release_channel'        => array( 'type' => 'string' ),
						'checked_at'             => array( 'type' => 'string' ),
						'github_updates_enabled' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		wp_register_ability(
			'divi5-woocommerce-mcp/update-self',
			array(
				'label'               => __( 'Update this plugin', 'mcp-bridge-for-divi-woocommerce' ),
				'description'         => __( 'Updates only this plugin to the exact stable GitHub release version supplied as expected_version.', 'mcp-bridge-for-divi-woocommerce' ),
				'category'            => self::CATEGORY,
				'execute_callback'    => array( self::class, 'update_self' ),
				'permission_callback' => array( self::class, 'can_update_self' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'expected_version' ),
					'additionalProperties' => false,
					'properties'           => array(
						'expected_version' => array(
							'type'      => 'string',
							'pattern'   => '^\\d+\\.\\d+\\.\\d+$',
							'minLength' => 5,
							'maxLength' => 32,
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'required'   => array(
						'from_version',
						'target_version',
						'success',
						'installed_version',
						'error_code',
						'error_message',
					),
					'properties' => array(
						'from_version'      => array( 'type' => 'string' ),
						'target_version'    => array( 'type' => array( 'string', 'null' ) ),
						'success'           => array( 'type' => 'boolean' ),
						'installed_version' => array( 'type' => 'string' ),
						'error_code'        => array( 'type' => array( 'string', 'null' ) ),
						'error_message'     => array( 'type' => array( 'string', 'null' ) ),
					),
				),
				'meta'                => array(
					'public'       => true,
					'show_in_rest' => false,
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);
	}

	/**
	 * Return current integration status.
	 *
	 * @return array<string, bool|string>
	 */
	public static function get_status(): array {
		return array(
			'plugin_version'       => Version::NUMBER,
			'divi_detected'        => DiviDetector::is_available(),
			'woocommerce_detected' => WooCommerceDetector::is_available(),
		);
	}

	/**
	 * Return a freshly checked update status for this plugin.
	 *
	 * @return array<string, bool|string|null>
	 */
	public static function get_update_status(): array {
		return SelfUpdater::get_status();
	}

	/**
	 * Update only this plugin to the requested available version.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, bool|string|null>
	 */
	public static function update_self( array $input ): array {
		return SelfUpdater::update_self( $input );
	}

	public static function can_get_status(): bool {
		return current_user_can( 'read' );
	}

	public static function can_update_self(): bool {
		return current_user_can( 'update_plugins' );
	}

	private function __construct() {
	}
}
