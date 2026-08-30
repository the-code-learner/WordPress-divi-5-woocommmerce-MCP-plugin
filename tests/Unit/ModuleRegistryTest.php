<?php
/**
 * Divi runtime module registry tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Divi\ModuleRegistry;
use PHPUnit\Framework\TestCase;

final class ModuleRegistryTest extends TestCase {
	public function test_enforces_core_structural_relationships(): void {
		self::assertTrue( ModuleRegistry::allows_child( 'divi/placeholder', 'divi/section' ) );
		self::assertTrue( ModuleRegistry::allows_child( 'divi/section', 'divi/row' ) );
		self::assertTrue( ModuleRegistry::allows_child( 'divi/row', 'divi/column' ) );
		self::assertTrue( ModuleRegistry::allows_child( 'divi/column', 'divi/text' ) );
		self::assertFalse( ModuleRegistry::allows_child( 'divi/section', 'divi/text' ) );
		self::assertFalse( ModuleRegistry::allows_child( 'divi/column', 'divi/row' ) );
	}

	public function test_enforces_verified_nested_module_relationships(): void {
		self::assertTrue( ModuleRegistry::allows_child( 'divi/accordion', 'divi/accordion-item' ) );
		self::assertTrue( ModuleRegistry::allows_child( 'divi/contact-form', 'divi/contact-field' ) );
		self::assertTrue( ModuleRegistry::allows_child( 'divi/pricing-tables', 'divi/pricing-table' ) );
		self::assertFalse( ModuleRegistry::allows_child( 'divi/accordion', 'divi/contact-field' ) );
	}
}
