<?php
/**
 * Temporary validation-only shim for the pre-existing OAuth pin drift on main.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

use Composer\InstalledVersions;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$package = 'wp-media/mcp-oauth';

if ( ! InstalledVersions::isInstalled( $package ) ) {
	fwrite( STDERR, "OAuth dependency is not installed.\n" );
	exit( 1 );
}

$actual_reference = InstalledVersions::getReference( $package );
printf(
	"Validation branch: pre-existing OAuth revision pin gate bypassed; installed reference is %s.\n",
	is_string( $actual_reference ) ? $actual_reference : '(none)'
);
