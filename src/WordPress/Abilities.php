<?php
/**
 * WordPress Abilities registration.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\WordPress;

use CodeLearner\Divi5WooCommerceMCP\Divi\Detector as DiviDetector;
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
				'label'       => __( 'Divi 5 + WooCommerce MCP', 'divi-5-woocommerce-mcp' ),
				'description' => __( 'Capabilities exposed by the Divi 5 + WooCommerce MCP plugin.', 'divi-5-woocommerce-mcp' ),
			)
		);
	}

	public static function register_abilities(): void {
		wp_register_ability(
			'divi5-woocommerce-mcp/get-status',
			array(
				'label'               => __( 'Get integration status', 'divi-5-woocommerce-mcp' ),
				'description'         => __( 'Returns plugin, Divi 5, and WooCommerce detection status without modifying the site.', 'divi-5-woocommerce-mcp' ),
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

	public static function can_get_status(): bool {
		return current_user_can( 'read' );
	}

	private function __construct() {
	}
}
