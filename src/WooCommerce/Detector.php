<?php
/**
 * WooCommerce runtime detection.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\WooCommerce;

final class Detector {
	public static function is_available(): bool {
		return class_exists( 'WooCommerce' ) || defined( 'WC_VERSION' );
	}

	private function __construct() {
	}
}
