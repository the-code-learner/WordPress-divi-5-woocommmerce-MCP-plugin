<?php
/**
 * Validate the OAuth dependency revision and provenance from composer.lock.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

use Composer\InstalledVersions;

$root_dir  = dirname( __DIR__ );
$lock_file = $root_dir . '/composer.lock';
$package   = 'wp-media/mcp-oauth';

if ( ! is_readable( $lock_file ) ) {
	fwrite( STDERR, "composer.lock is missing or unreadable.\n" );
	exit( 1 );
}

$lock_contents = file_get_contents( $lock_file );
if ( false === $lock_contents ) {
	fwrite( STDERR, "Unable to read composer.lock.\n" );
	exit( 1 );
}

$lock_data = json_decode( $lock_contents, true );
if ( ! is_array( $lock_data ) || JSON_ERROR_NONE !== json_last_error() ) {
	fwrite( STDERR, "composer.lock is not valid JSON.\n" );
	exit( 1 );
}

$locked_package = null;
$package_groups = array( 'packages', 'packages-dev' );

foreach ( $package_groups as $package_group ) {
	$packages = isset( $lock_data[ $package_group ] ) && is_array( $lock_data[ $package_group ] )
		? $lock_data[ $package_group ]
		: array();

	foreach ( $packages as $locked_candidate ) {
		if ( is_array( $locked_candidate ) && $package === ( $locked_candidate['name'] ?? null ) ) {
			$locked_package = $locked_candidate;
			break 2;
		}
	}
}

if ( null === $locked_package ) {
	fwrite( STDERR, "OAuth dependency is not present in composer.lock.\n" );
	exit( 1 );
}

$source = isset( $locked_package['source'] ) && is_array( $locked_package['source'] )
	? $locked_package['source']
	: array();
$dist   = isset( $locked_package['dist'] ) && is_array( $locked_package['dist'] )
	? $locked_package['dist']
	: array();

$locked_source_type = $source['type'] ?? null;
$locked_source_url  = $source['url'] ?? null;
$locked_reference   = $source['reference'] ?? null;
$dist_reference     = $dist['reference'] ?? null;

if ( 'git' !== $locked_source_type || 'https://github.com/wp-media/mcp-oauth.git' !== $locked_source_url ) {
	fwrite( STDERR, "OAuth dependency source in composer.lock is not the trusted wp-media/mcp-oauth repository.\n" );
	exit( 1 );
}

if ( ! is_string( $locked_reference ) || 1 !== preg_match( '/^[0-9a-f]{40}$/i', $locked_reference ) ) {
	fwrite( STDERR, "OAuth dependency does not have a valid locked Git commit reference.\n" );
	exit( 1 );
}

if ( null !== $dist_reference && $locked_reference !== $dist_reference ) {
	fwrite( STDERR, "OAuth dependency source and dist references differ in composer.lock.\n" );
	exit( 1 );
}

require_once $root_dir . '/vendor/autoload.php';

if ( ! InstalledVersions::isInstalled( $package ) ) {
	fwrite( STDERR, "OAuth dependency is not installed.\n" );
	exit( 1 );
}

$actual_reference = InstalledVersions::getReference( $package );

if ( $locked_reference !== $actual_reference ) {
	fwrite(
		STDERR,
		sprintf(
			"Installed OAuth dependency does not match composer.lock. Locked %s, installed %s.\n",
			$locked_reference,
			is_string( $actual_reference ) ? $actual_reference : '(none)'
		)
	);
	exit( 1 );
}

printf( "OAuth dependency verified from composer.lock: %s\n", $locked_reference );
