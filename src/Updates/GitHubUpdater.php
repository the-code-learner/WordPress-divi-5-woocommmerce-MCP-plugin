<?php
/**
 * Temporary GitHub release updater.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Updates;

use CodeLearner\Divi5WooCommerceMCP\Admin\Settings;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

final class GitHubUpdater {
	private const REPOSITORY_URL = 'https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin/';

	private const DISTRIBUTION_SLUG = 'mcp-bridge-for-divi-woocommerce';

	private const RELEASE_ASSET_PATTERN = '/^mcp-bridge-for-divi-woocommerce\.zip$/i';

	private const LATEST_RELEASE_STRATEGY = 'latest_release';

	private const REQUIRE_RELEASE_ASSETS = 2;

	/**
	 * Keep the checker alive for the full request.
	 *
	 * @var object|null
	 */
	private static $checker = null;

	public static function boot(): void {
		if ( null !== self::$checker || ! self::is_enabled() ) {
			return;
		}

		if ( ! class_exists( PucFactory::class ) ) {
			add_action( 'admin_notices', array( self::class, 'render_missing_dependency_notice' ) );
			return;
		}

		$checker = PucFactory::buildUpdateChecker(
			self::REPOSITORY_URL,
			DIVI5_WC_MCP_FILE,
			self::DISTRIBUTION_SLUG
		);

		$checker->addFilter(
			'vcs_update_detection_strategies',
			array( self::class, 'filter_update_detection_strategies' )
		);

		$api = $checker->getVcsApi();

		if ( is_object( $api ) && method_exists( $api, 'enableReleaseAssets' ) ) {
			$api->enableReleaseAssets(
				self::RELEASE_ASSET_PATTERN,
				self::REQUIRE_RELEASE_ASSETS
			);
		}

		self::$checker = $checker;
	}

	/**
	 * Force a real remote update check through Plugin Update Checker.
	 *
	 * @return object|null Available update metadata, or null when no update is available.
	 */
	public static function force_check() {
		if ( ! self::is_enabled() ) {
			return null;
		}

		if ( null === self::$checker ) {
			self::boot();
		}

		if ( ! is_object( self::$checker ) || ! method_exists( self::$checker, 'checkForUpdates' ) ) {
			return null;
		}

		return self::$checker->checkForUpdates();
	}

	/**
	 * Return the request-scoped checker instance.
	 *
	 * @return object|null
	 */
	public static function get_checker() {
		return self::$checker;
	}

	/**
	 * Verify that an update package URL points to the one allowed release asset.
	 */
	public static function is_release_asset_url( string $url ): bool {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return false;
		}

		return 1 === preg_match( self::RELEASE_ASSET_PATTERN, basename( $path ) );
	}

	/**
	 * Restrict PUC to stable GitHub Releases only.
	 *
	 * PUC's GitHub latest-release strategy skips releases marked as prerelease.
	 * Removing the tag and branch strategies prevents fallback to non-release
	 * sources when no suitable release is available.
	 *
	 * @param array<string, callable> $strategies Update detection strategies.
	 * @return array<string, callable>
	 */
	public static function filter_update_detection_strategies( array $strategies ): array {
		if ( ! isset( $strategies[ self::LATEST_RELEASE_STRATEGY ] ) ) {
			return array();
		}

		return array(
			self::LATEST_RELEASE_STRATEGY => $strategies[ self::LATEST_RELEASE_STRATEGY ],
		);
	}

	public static function is_enabled(): bool {
		/**
		 * Filters whether the temporary GitHub release updater is enabled.
		 *
		 * The saved setting defaults to true. Returning false prevents the
		 * GitHub update checker from being initialized for the request.
		 *
		 * @param bool $enabled Whether GitHub update checks are enabled.
		 */
		return (bool) apply_filters(
			'mcp_bridge_github_updates_enabled',
			Settings::is_github_updates_enabled()
		);
	}

	public static function render_missing_dependency_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__(
				'MCP Bridge could not initialize its GitHub update checker. Install a complete production build with Composer dependencies.',
				'mcp-bridge-for-divi-woocommerce'
			)
		);
	}

	private function __construct() {
	}
}
