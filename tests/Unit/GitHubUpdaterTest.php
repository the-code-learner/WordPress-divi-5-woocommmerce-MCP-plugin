<?php
/**
 * GitHub updater tests.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Tests\Unit;

use CodeLearner\Divi5WooCommerceMCP\Updates\GitHubUpdater;
use PHPUnit\Framework\TestCase;

final class GitHubUpdaterTest extends TestCase {
	public function test_update_detection_is_release_only(): void {
		$release = static function (): string {
			return 'release';
		};
		$tag = static function (): string {
			return 'tag';
		};
		$branch = static function (): string {
			return 'branch';
		};

		$filtered = GitHubUpdater::filter_update_detection_strategies(
			array(
				'latest_release' => $release,
				'latest_tag'     => $tag,
				'branch'         => $branch,
			)
		);

		self::assertSame( array( 'latest_release' => $release ), $filtered );
	}

	public function test_update_detection_has_no_fallback_without_release_strategy(): void {
		$filtered = GitHubUpdater::filter_update_detection_strategies(
			array(
				'latest_tag' => static function (): string {
					return 'tag';
				},
				'branch'     => static function (): string {
					return 'branch';
				},
			)
		);

		self::assertSame( array(), $filtered );
	}

	public function test_only_exact_distribution_asset_name_is_accepted(): void {
		self::assertTrue(
			GitHubUpdater::is_release_asset_url(
				'https://github.com/the-code-learner/WordPress-divi-5-woocommmerce-MCP-plugin/releases/download/v0.1.3/mcp-bridge-for-divi-woocommerce.zip'
			)
		);
		self::assertFalse(
			GitHubUpdater::is_release_asset_url(
				'https://example.test/releases/plugin.zip'
			)
		);
		self::assertFalse(
			GitHubUpdater::is_release_asset_url(
				'https://example.test/releases/mcp-bridge-for-divi-woocommerce-beta.zip'
			)
		);
	}
}
