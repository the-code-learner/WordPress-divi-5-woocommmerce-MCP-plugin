<?php
/**
 * Divi runtime detection.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Divi;

final class Detector {
	public static function is_available(): bool {
		return defined( 'ET_CORE_VERSION' )
			|| defined( 'ET_BUILDER_VERSION' )
			|| class_exists( 'ET_Builder_Plugin' );
	}

	private function __construct() {
	}
}
