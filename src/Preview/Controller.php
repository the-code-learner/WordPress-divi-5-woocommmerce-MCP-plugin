<?php
/**
 * Browser-based preview seam.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Preview;

final class Controller {
	public static function is_supported(): bool {
		return true;
	}

	private function __construct() {
	}
}
