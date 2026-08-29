<?php
/**
 * Security capability helpers.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

namespace CodeLearner\Divi5WooCommerceMCP\Security;

final class Capabilities {
	public static function can_read(): bool {
		return current_user_can( 'read' );
	}

	public static function can_edit_posts(): bool {
		return current_user_can( 'edit_posts' );
	}

	public static function can_publish_posts(): bool {
		return current_user_can( 'publish_posts' );
	}

	private function __construct() {
	}
}
