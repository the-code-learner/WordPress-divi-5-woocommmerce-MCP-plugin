<?php
/**
 * Shared MCP ability compatibility tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\MCP\SharedAbilitiesCompatibility;
use PHPUnit\Framework\TestCase;
use WP\MCP\Abilities\DiscoverAbilitiesAbility;
use WP\MCP\Abilities\ExecuteAbilityAbility;
use WP\MCP\Abilities\GetAbilityInfoAbility;

final class SharedAbilitiesCompatibilityTest extends TestCase {
	public function test_required_shared_abilities_match_oauth_server_tool_contract(): void {
		self::assertSame(
			array(
				'mcp-adapter/discover-abilities',
				'mcp-adapter/get-ability-info',
				'mcp-adapter/execute-ability',
			),
			SharedAbilitiesCompatibility::required_ability_names()
		);
	}

	public function test_only_missing_shared_abilities_are_selected_for_registration(): void {
		$existing = array( 'mcp-adapter/get-ability-info' );
		$checked  = array();

		$missing = SharedAbilitiesCompatibility::missing_ability_registrars(
			static function ( string $ability_name ) use ( $existing, &$checked ): bool {
				$checked[] = $ability_name;
				return in_array( $ability_name, $existing, true );
			}
		);

		self::assertSame( SharedAbilitiesCompatibility::required_ability_names(), $checked );
		self::assertSame(
			array(
				'mcp-adapter/discover-abilities' => DiscoverAbilitiesAbility::class,
				'mcp-adapter/execute-ability'    => ExecuteAbilityAbility::class,
			),
			$missing
		);
	}

	public function test_existing_upstream_registrations_are_left_untouched(): void {
		$missing = SharedAbilitiesCompatibility::missing_ability_registrars(
			static function ( string $ability_name ): bool {
				unset( $ability_name );
				return true;
			}
		);

		self::assertSame( array(), $missing );
	}

	public function test_registrar_mapping_uses_the_official_mcp_adapter_classes(): void {
		$missing = SharedAbilitiesCompatibility::missing_ability_registrars(
			static function ( string $ability_name ): bool {
				unset( $ability_name );
				return false;
			}
		);

		self::assertSame( DiscoverAbilitiesAbility::class, $missing['mcp-adapter/discover-abilities'] );
		self::assertSame( GetAbilityInfoAbility::class, $missing['mcp-adapter/get-ability-info'] );
		self::assertSame( ExecuteAbilityAbility::class, $missing['mcp-adapter/execute-ability'] );
	}
}
