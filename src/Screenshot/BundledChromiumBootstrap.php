<?php
/**
 * Register the bundled Chromium renderer as the default screenshot engine.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Screenshot;

use CodeLearner\Divi5WooCommerceMCP\Divi\ScreenshotEngineInterface;

final class BundledChromiumBootstrap {
	public static function hooks(): void {
		add_filter(
			'divi5_woocommerce_mcp_screenshot_engine',
			array( self::class, 'default_engine' ),
			1,
			2
		);
	}

	/**
	 * Preserve explicitly registered renderers and otherwise provide the bundled engine.
	 *
	 * @param mixed                $engine  Existing engine.
	 * @param array<string, mixed> $context Screenshot context.
	 * @return ScreenshotEngineInterface
	 */
	public static function default_engine( $engine, array $context = array() ): ScreenshotEngineInterface {
		unset( $context );

		if ( $engine instanceof ScreenshotEngineInterface ) {
			return $engine;
		}

		return new BundledChromiumRenderer();
	}

	private function __construct() {
	}
}
