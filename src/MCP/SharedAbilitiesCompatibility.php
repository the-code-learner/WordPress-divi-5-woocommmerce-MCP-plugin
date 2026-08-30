<?php
/**
 * Compatibility for the MCP Adapter shared ability registration timing.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\MCP;

use WP\MCP\Abilities\DiscoverAbilitiesAbility;
use WP\MCP\Abilities\ExecuteAbilityAbility;
use WP\MCP\Abilities\GetAbilityInfoAbility;

/**
 * Register the MCP Adapter's shared abilities inside WordPress' one-shot
 * Abilities API registration window.
 *
 * wordpress/mcp-adapter 0.6.1 wires these callbacks only when its REST-layer
 * init runs, after wp_abilities_api_init has already fired on WordPress 6.9.
 * The OAuth server depends on these three abilities as its public MCP tools.
 *
 * This compatibility layer is deliberately idempotent: if the adapter (or a
 * future dependency update) has already registered the category or an ability,
 * it leaves that registration untouched.
 */
final class SharedAbilitiesCompatibility {
	private const CATEGORY = 'mcp-adapter';

	/**
	 * Ability name => registrar class.
	 *
	 * @var array<string, class-string>
	 */
	private const ABILITY_REGISTRARS = array(
		'mcp-adapter/discover-abilities' => DiscoverAbilitiesAbility::class,
		'mcp-adapter/get-ability-info'   => GetAbilityInfoAbility::class,
		'mcp-adapter/execute-ability'    => ExecuteAbilityAbility::class,
	);

	/**
	 * Hook before the Abilities API registration actions fire.
	 */
	public static function hooks(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'ensure_category' ), 5 );
		add_action( 'wp_abilities_api_init', array( self::class, 'ensure_abilities' ), 5 );
	}

	/**
	 * Ensure the shared category exists without replacing an upstream one.
	 */
	public static function ensure_category(): void {
		if ( ! function_exists( 'wp_has_ability_category' ) || ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		if ( wp_has_ability_category( self::CATEGORY ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => 'MCP Adapter',
				'description' => 'Abilities for the MCP Adapter',
			)
		);
	}

	/**
	 * Ensure the three abilities used as tools by both MCP servers exist.
	 */
	public static function ensure_abilities(): void {
		if ( ! function_exists( 'wp_has_ability' ) ) {
			return;
		}

		foreach ( self::missing_ability_registrars( 'wp_has_ability' ) as $registrar ) {
			if ( class_exists( $registrar ) && is_callable( array( $registrar, 'register' ) ) ) {
				$registrar::register();
			}
		}
	}

	/**
	 * Return only registrars whose abilities are not present.
	 *
	 * Kept side-effect free so the upstream timing regression can be covered by
	 * unit tests without booting a full WordPress request.
	 *
	 * @param callable(string): bool $has_ability Ability-existence callback.
	 * @return array<string, class-string>
	 */
	public static function missing_ability_registrars( callable $has_ability ): array {
		$missing = array();

		foreach ( self::ABILITY_REGISTRARS as $ability_name => $registrar ) {
			if ( ! $has_ability( $ability_name ) ) {
				$missing[ $ability_name ] = $registrar;
			}
		}

		return $missing;
	}

	/**
	 * Names that the OAuth server must expose through tools/list.
	 *
	 * @return string[]
	 */
	public static function required_ability_names(): array {
		return array_keys( self::ABILITY_REGISTRARS );
	}

	private function __construct() {
	}
}
