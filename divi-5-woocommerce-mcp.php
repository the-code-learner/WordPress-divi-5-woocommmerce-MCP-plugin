<?php
/**
 * Plugin Name: MCP Bridge for Divi 5 and WooCommerce
 * Plugin URI: https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin
 * Description: Secure MCP foundations for WordPress, Divi 5, WooCommerce, preview, and publishing workflows.
 * Version: 0.2.1
 * Update URI: https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: The Code Learner
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mcp-bridge-for-divi-woocommerce
 * Domain Path: /languages
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/src/Version.php';

define( 'DIVI5_WC_MCP_VERSION', \CodeLearner\Divi5WooCommerceMCP\Version::NUMBER );
define( 'DIVI5_WC_MCP_FILE', __FILE__ );
define( 'DIVI5_WC_MCP_DIR', plugin_dir_path( __FILE__ ) );

$divi5_wc_mcp_autoloader = __DIR__ . '/vendor/autoload_packages.php';

if ( ! file_exists( $divi5_wc_mcp_autoloader ) ) {
	$divi5_wc_mcp_autoloader = __DIR__ . '/vendor/autoload.php';
}

if ( file_exists( $divi5_wc_mcp_autoloader ) ) {
	require_once $divi5_wc_mcp_autoloader;
} else {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html__( 'MCP Bridge for Divi 5 and WooCommerce is missing its Composer dependencies. Install a production build or run Composer before activating the development checkout.', 'mcp-bridge-for-divi-woocommerce' )
			);
		}
	);

	return;
}

add_action(
	'plugins_loaded',
	static function (): void {
		\CodeLearner\Divi5WooCommerceMCP\Plugin::boot();
	},
	20
);
