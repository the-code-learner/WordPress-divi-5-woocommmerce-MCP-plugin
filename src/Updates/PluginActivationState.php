<?php
/**
 * Preserve plugin activation state across self-update attempts.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Updates;

final class PluginActivationState {
	private bool $active;

	private bool $network_active;

	private function __construct( bool $active, bool $network_active ) {
		$this->active         = $active;
		$this->network_active = $network_active;
	}

	/**
	 * Capture the current site and network activation scope.
	 */
	public static function capture( string $plugin_basename ): self {
		$network_active = is_multisite() && is_plugin_active_for_network( $plugin_basename );

		return new self( is_plugin_active( $plugin_basename ), $network_active );
	}

	/**
	 * Restore the original activation scope after WordPress replaces plugin files.
	 *
	 * @return string Empty on success, otherwise a sanitized error code.
	 */
	public function restore( string $plugin_basename ): string {
		if ( ! $this->active || $this->matches( $plugin_basename ) ) {
			return '';
		}

		$result = activate_plugin( $plugin_basename, '', $this->network_active, true );

		if ( is_wp_error( $result ) ) {
			$error_code = sanitize_key( (string) $result->get_error_code() );

			return '' !== $error_code ? 'plugin_reactivation_' . $error_code : 'plugin_reactivation_failed';
		}

		return $this->matches( $plugin_basename ) ? '' : 'plugin_reactivation_failed';
	}

	/**
	 * Whether the plugin was active before the update attempt.
	 */
	public function was_active(): bool {
		return $this->active;
	}

	/**
	 * Whether the plugin was network-active before the update attempt.
	 */
	public function was_network_active(): bool {
		return $this->network_active;
	}

	private function matches( string $plugin_basename ): bool {
		if ( ! is_plugin_active( $plugin_basename ) ) {
			return false;
		}

		if ( ! is_multisite() ) {
			return true;
		}

		return is_plugin_active_for_network( $plugin_basename ) === $this->network_active;
	}
}
