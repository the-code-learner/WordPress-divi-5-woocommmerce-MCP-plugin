<?php
/**
 * MCP self updater tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Updates\SelfUpdater;
use PHPUnit\Framework\TestCase;

final class SelfUpdaterTest extends TestCase {
	public function test_expected_version_is_compare_and_swap_guard(): void {
		self::assertTrue( SelfUpdater::expected_version_matches( '0.1.3', '0.1.3' ) );
		self::assertFalse( SelfUpdater::expected_version_matches( '0.1.2', '0.1.3' ) );
		self::assertFalse( SelfUpdater::expected_version_matches( '', '0.1.3' ) );
		self::assertFalse( SelfUpdater::expected_version_matches( '0.1.3-beta.1', '0.1.3' ) );
	}

	public function test_valid_candidate_targets_current_plugin_and_exact_asset(): void {
		$update = (object) array(
			'version'      => '0.1.3',
			'filename'     => 'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php',
			'download_url' => 'https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin/releases/download/v0.1.3/mcp-bridge-for-divi-woocommerce.zip',
		);

		self::assertSame(
			'',
			SelfUpdater::validate_candidate(
				$update,
				'0.1.2',
				'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php'
			)
		);
	}

	public function test_candidate_rejects_other_plugin_path(): void {
		$update = (object) array(
			'version'      => '0.1.3',
			'filename'     => 'other-plugin/other-plugin.php',
			'download_url' => 'https://github.com/example/repo/releases/download/v0.1.3/mcp-bridge-for-divi-woocommerce.zip',
		);

		self::assertSame(
			'invalid_plugin_target',
			SelfUpdater::validate_candidate(
				$update,
				'0.1.2',
				'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php'
			)
		);
	}

	public function test_candidate_rejects_prerelease_and_downgrade(): void {
		$prerelease = (object) array(
			'version'      => '0.1.3-beta.1',
			'filename'     => 'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php',
			'download_url' => 'https://github.com/example/repo/releases/download/v0.1.3-beta.1/mcp-bridge-for-divi-woocommerce.zip',
		);
		$downgrade  = (object) array(
			'version'      => '0.1.1',
			'filename'     => 'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php',
			'download_url' => 'https://github.com/example/repo/releases/download/v0.1.1/mcp-bridge-for-divi-woocommerce.zip',
		);

		self::assertSame(
			'invalid_release_version',
			SelfUpdater::validate_candidate(
				$prerelease,
				'0.1.2',
				'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php'
			)
		);
		self::assertSame(
			'no_update_available',
			SelfUpdater::validate_candidate(
				$downgrade,
				'0.1.2',
				'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php'
			)
		);
	}

	public function test_candidate_rejects_arbitrary_package_name(): void {
		$update = (object) array(
			'version'      => '0.1.3',
			'filename'     => 'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php',
			'download_url' => 'https://example.test/releases/arbitrary-package.zip',
		);

		self::assertSame(
			'invalid_update_source',
			SelfUpdater::validate_candidate(
				$update,
				'0.1.2',
				'mcp-bridge-for-divi-woocommerce/divi-5-woocommerce-mcp.php'
			)
		);
	}
}
