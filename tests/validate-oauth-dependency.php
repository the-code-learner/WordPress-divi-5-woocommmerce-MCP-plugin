<?php
/**
 * Validate the exact OAuth dependency revision used by release builds.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

use Composer\InstalledVersions;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$package            = 'wp-media/mcp-oauth';
$expected_reference = 'd6b1aa1a3b09212719b2a2e3e0979ec5e7010b93';

if ( ! InstalledVersions::isInstalled( $package ) ) {
	fwrite( STDERR, "OAuth dependency is not installed.\n" );
	exit( 1 );
}

$actual_reference = InstalledVersions::getReference( $package );

if ( $expected_reference !== $actual_reference ) {
	fwrite(
		STDERR,
		sprintf(
			"Unexpected OAuth dependency revision. Expected %s, got %s.\n",
			$expected_reference,
			is_string( $actual_reference ) ? $actual_reference : '(none)'
		)
	);
	exit( 1 );
}

printf( "OAuth dependency revision verified: %s\n", $actual_reference );
