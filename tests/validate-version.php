<?php
/**
 * Validate version consistency across release metadata.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/src/Version.php';

use CodeLearner\Divi5WooCommerceMCP\Version;

$root        = dirname( __DIR__ );
$plugin_file = file_get_contents( $root . '/divi-5-woocommerce-mcp.php' );
$readme      = file_get_contents( $root . '/readme.txt' );

if ( false === $plugin_file || false === $readme ) {
	fwrite( STDERR, "Unable to read release metadata files.\n" );
	exit( 1 );
}

if ( ! preg_match( '/^ \* Version:\s*([^\s]+)$/m', $plugin_file, $plugin_match ) ) {
	fwrite( STDERR, "Plugin header Version was not found.\n" );
	exit( 1 );
}

if ( ! preg_match( '/^Stable tag:\s*([^\s]+)$/m', $readme, $readme_match ) ) {
	fwrite( STDERR, "readme.txt Stable tag was not found.\n" );
	exit( 1 );
}

$versions = array(
	'Version::NUMBER' => Version::NUMBER,
	'plugin header'   => $plugin_match[1],
	'readme stable'   => $readme_match[1],
);

foreach ( $versions as $label => $version ) {
	if ( Version::NUMBER !== $version ) {
		fwrite( STDERR, sprintf( "%s version mismatch: expected %s, got %s.\n", $label, Version::NUMBER, $version ) );
		exit( 1 );
	}
}

$tag_version = getenv( 'TAG_VERSION' );

if ( false !== $tag_version && '' !== $tag_version && Version::NUMBER !== $tag_version ) {
	fwrite( STDERR, sprintf( "Git tag version mismatch: expected %s, got %s.\n", Version::NUMBER, $tag_version ) );
	exit( 1 );
}

fwrite( STDOUT, sprintf( "Version metadata is consistent at %s.\n", Version::NUMBER ) );
