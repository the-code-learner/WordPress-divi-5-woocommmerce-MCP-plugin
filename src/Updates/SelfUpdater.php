<?php
/**
 * MCP-scoped self-update operations.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Updates;

use CodeLearner\Divi5WooCommerceMCP\Audit\Logger;
use CodeLearner\Divi5WooCommerceMCP\Version;

final class SelfUpdater {
	private const STATUS_ACTION = 'divi5-woocommerce-mcp/get-update-status';

	private const UPDATE_ACTION = 'divi5-woocommerce-mcp/update-self';

	private const SOURCE = 'github-release';

	private const RELEASE_CHANNEL = 'stable';

	/**
	 * Force an update check and return status for this plugin only.
	 *
	 * @return array<string, bool|string|null>
	 */
	public static function get_status(): array {
		$enabled           = GitHubUpdater::is_enabled();
		$current_version   = Version::NUMBER;
		$available_version = null;
		$plugin_basename   = plugin_basename( DIVI5_WC_MCP_FILE );

		if ( $enabled ) {
			$update = GitHubUpdater::force_check();

			if ( '' === self::validate_candidate( $update, $current_version, $plugin_basename ) ) {
				$available_version = (string) $update->version;
			}
		}

		$result = array(
			'current_version'        => $current_version,
			'available_version'      => $available_version,
			'update_available'       => null !== $available_version,
			'source'                 => self::SOURCE,
			'release_channel'        => self::RELEASE_CHANNEL,
			'checked_at'             => gmdate( 'c' ),
			'github_updates_enabled' => $enabled,
		);

		Logger::record(
			self::STATUS_ACTION,
			array(
				'user_id'                => get_current_user_id(),
				'current_version'        => $current_version,
				'available_version'      => $available_version,
				'update_available'       => null !== $available_version,
				'github_updates_enabled' => $enabled,
			)
		);

		return $result;
	}

	/**
	 * Update this plugin to the exact expected stable GitHub release.
	 *
	 * @param array<string, mixed> $input Ability input.
	 * @return array<string, bool|string|null>
	 */
	public static function update_self( array $input ): array {
		$from_version = Version::NUMBER;
		$expected     = isset( $input['expected_version'] ) && is_string( $input['expected_version'] )
			? trim( $input['expected_version'] )
			: '';

		if ( ! GitHubUpdater::is_enabled() ) {
			return self::failure( $from_version, null, 'github_updates_disabled', 'GitHub updates are disabled.' );
		}

		$plugin_basename = plugin_basename( DIVI5_WC_MCP_FILE );
		$update          = GitHubUpdater::force_check();
		$candidate_error = self::validate_candidate( $update, $from_version, $plugin_basename );

		if ( '' !== $candidate_error ) {
			return self::failure( $from_version, null, $candidate_error, self::candidate_error_message( $candidate_error ) );
		}

		$target_version = (string) $update->version;

		if ( ! self::expected_version_matches( $expected, $target_version ) ) {
			return self::failure(
				$from_version,
				$target_version,
				'expected_version_mismatch',
				'The available version does not match expected_version.'
			);
		}

		if ( ! defined( 'ABSPATH' ) ) {
			return self::failure( $from_version, $target_version, 'wordpress_runtime_unavailable', 'WordPress update runtime is unavailable.' );
		}

		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		if ( ! class_exists( '\Plugin_Upgrader' ) || ! class_exists( '\Automatic_Upgrader_Skin' ) ) {
			return self::failure( $from_version, $target_version, 'upgrader_unavailable', 'WordPress plugin upgrader is unavailable.' );
		}

		$skin     = new \Automatic_Upgrader_Skin();
		$upgrader = new \Plugin_Upgrader( $skin );
		$result   = $upgrader->upgrade(
			$plugin_basename,
			array(
				'clear_update_cache' => false,
			)
		);

		if ( is_wp_error( $result ) ) {
			$error_code = sanitize_key( (string) $result->get_error_code() );

			return self::failure(
				$from_version,
				$target_version,
				'' !== $error_code ? 'upgrade_' . $error_code : 'upgrade_failed',
				'WordPress could not update the plugin.'
			);
		}

		if ( true !== $result ) {
			return self::failure( $from_version, $target_version, 'upgrade_failed', 'WordPress could not update the plugin.' );
		}

		$installed_version = self::read_installed_version();
		$success           = $target_version === $installed_version;

		if ( ! $success ) {
			return self::failure(
				$from_version,
				$target_version,
				'installed_version_mismatch',
				'The installed plugin version could not be verified after the update.',
				$installed_version
			);
		}

		$output = array(
			'from_version'      => $from_version,
			'target_version'    => $target_version,
			'success'           => true,
			'installed_version' => $installed_version,
			'error_code'        => null,
			'error_message'     => null,
		);

		Logger::record(
			self::UPDATE_ACTION,
			array(
				'user_id'           => get_current_user_id(),
				'from_version'      => $from_version,
				'target_version'    => $target_version,
				'installed_version' => $installed_version,
				'success'           => true,
			)
		);

		return $output;
	}

	/**
	 * Validate update metadata without accepting arbitrary sources or plugin paths.
	 *
	 * @param mixed  $update          Update metadata.
	 * @param string $current_version Current plugin version.
	 * @param string $plugin_basename Current plugin basename.
	 */
	public static function validate_candidate( $update, string $current_version, string $plugin_basename ): string {
		if ( ! is_object( $update ) || ! isset( $update->version ) || ! is_string( $update->version ) ) {
			return 'no_update_available';
		}

		if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $update->version ) ) {
			return 'invalid_release_version';
		}

		if ( ! version_compare( $update->version, $current_version, '>' ) ) {
			return 'no_update_available';
		}

		if ( isset( $update->filename ) && (string) $update->filename !== $plugin_basename ) {
			return 'invalid_plugin_target';
		}

		if ( ! isset( $update->download_url ) || ! is_string( $update->download_url ) || ! GitHubUpdater::is_release_asset_url( $update->download_url ) ) {
			return 'invalid_update_source';
		}

		return '';
	}

	public static function expected_version_matches( string $expected_version, string $available_version ): bool {
		if ( '' === $expected_version || 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $expected_version ) ) {
			return false;
		}

		return hash_equals( $available_version, $expected_version );
	}

	private static function read_installed_version(): string {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$data = get_plugin_data( DIVI5_WC_MCP_FILE, false, false );

		return isset( $data['Version'] ) && is_string( $data['Version'] ) ? $data['Version'] : '';
	}

	private static function candidate_error_message( string $error_code ): string {
		$messages = array(
			'no_update_available'     => 'No newer stable release is available.',
			'invalid_release_version' => 'The detected release version is not an accepted stable version.',
			'invalid_plugin_target'   => 'The detected update does not target this plugin.',
			'invalid_update_source'   => 'The detected update package is not the allowed release asset.',
		);

		return isset( $messages[ $error_code ] ) ? $messages[ $error_code ] : 'The update candidate was rejected.';
	}

	/**
	 * Build a sanitized failure result and audit event.
	 *
	 * @return array<string, bool|string|null>
	 */
	private static function failure(
		string $from_version,
		?string $target_version,
		string $error_code,
		string $error_message,
		string $installed_version = ''
	): array {
		$output = array(
			'from_version'      => $from_version,
			'target_version'    => $target_version,
			'success'           => false,
			'installed_version' => '' !== $installed_version ? $installed_version : $from_version,
			'error_code'        => $error_code,
			'error_message'     => $error_message,
		);

		Logger::record(
			self::UPDATE_ACTION,
			array(
				'user_id'           => get_current_user_id(),
				'from_version'      => $from_version,
				'target_version'    => $target_version,
				'installed_version' => $output['installed_version'],
				'success'           => false,
				'error_code'        => $error_code,
			)
		);

		return $output;
	}

	private function __construct() {
	}
}
