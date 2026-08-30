<?php
/**
 * MCP ability exposure regression tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\Abilities as DiviAbilities;
use CodeLearner\Divi5WooCommerceMCP\WordPress\Abilities as WordPressAbilities;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class McpAbilityExposureTest extends TestCase {
	public function test_wordpress_bridge_abilities_are_explicitly_public_for_mcp_adapter(): void {
		$meta = $this->invoke_meta_factory( WordPressAbilities::class );

		self::assertTrue( $meta['public'] );
		self::assertFalse( $meta['show_in_rest'] );
		self::assertTrue( $meta['mcp']['public'] );
		self::assertSame( 'tool', $meta['mcp']['type'] );
	}

	public function test_divi_abilities_are_explicitly_public_for_mcp_adapter(): void {
		$meta = $this->invoke_meta_factory( DiviAbilities::class );

		self::assertTrue( $meta['public'] );
		self::assertFalse( $meta['show_in_rest'] );
		self::assertTrue( $meta['mcp']['public'] );
		self::assertSame( 'tool', $meta['mcp']['type'] );
	}

	/**
	 * @param class-string $class_name Ability class.
	 * @return array<string, mixed>
	 */
	private function invoke_meta_factory( string $class_name ): array {
		$method = new ReflectionMethod( $class_name, 'mcp_meta' );
		$method->setAccessible( true );

		/** @var array<string, mixed> $meta */
		$meta = $method->invoke( null, true, false, true );

		return $meta;
	}
}
