<?php
/**
 * Real bundled Chromium smoke test used by CI.
 *
 * @package Divi5WooCommerceMCP
 */

declare(strict_types=1);

use CodeLearner\Divi5WooCommerceMCP\Screenshot\BundledChromiumRenderer;

require dirname( __DIR__ ) . '/vendor/autoload.php';

$binary = isset( $argv[1] ) ? (string) $argv[1] : '';
$url    = isset( $argv[2] ) ? (string) $argv[2] : '';

if ( '' === $binary || '' === $url ) {
	fwrite( STDERR, "Usage: php tests/chromium-smoke.php <binary> <url>\n" );
	exit( 2 );
}

$renderer = new BundledChromiumRenderer( $binary );
$status   = $renderer->status( true );

if ( true !== ( $status['ready'] ?? false ) ) {
	fwrite( STDERR, 'Renderer status failed: ' . json_encode( $status ) . "\n" );
	exit( 1 );
}

$result = $renderer->render(
	array(
		'url'              => $url,
		'width'            => 390,
		'height'           => null,
		'full_page'        => true,
		'format'           => 'png',
		'quality'          => null,
		'timeout_seconds'  => 20,
		'max_bytes'        => 26214400,
		'max_image_height' => 30000,
		'max_pixels'       => 100000000,
	)
);

if ( true !== ( $result['success'] ?? false ) || ! isset( $result['image_data'] ) || ! is_string( $result['image_data'] ) ) {
	fwrite( STDERR, 'Render failed: ' . json_encode( $result ) . "\n" );
	exit( 1 );
}

$image = $result['image_data'];
if ( strlen( $image ) < 24 || "\x89PNG\r\n\x1a\n" !== substr( $image, 0, 8 ) ) {
	fwrite( STDERR, "Renderer did not return a PNG.\n" );
	exit( 1 );
}

$width  = unpack( 'N', substr( $image, 16, 4 ) )[1];
$height = unpack( 'N', substr( $image, 20, 4 ) )[1];

if ( 390 !== $width || $height < 1000 ) {
	fwrite( STDERR, sprintf( "Unexpected screenshot dimensions: %dx%d\n", $width, $height ) );
	exit( 1 );
}

if ( empty( $result['warnings'] ) ) {
	fwrite( STDERR, "Expected the cross-origin fixture request to be blocked.\n" );
	exit( 1 );
}

printf( "Bundled Chromium smoke test passed: %dx%d\n", $width, $height );
