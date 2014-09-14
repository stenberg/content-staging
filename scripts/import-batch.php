<?php
/**
 * Arguments passed to PHP script:
 * $argv[0]: Script being executed.
 * $argv[1]: Absolute path to WordPress installation.
 * $argv[2]: Site URL.
 */

set_time_limit( 0 );
ini_set( 'memory_limit', '256M' );
ini_set( 'display_errors', 'On' );
error_reporting( E_ALL | E_STRICT );

// Make sure that absolute path to WordPress installation is set.
if ( ! isset( $argv[1] ) || ! $argv[1] ) {
	exit();
}

// Try to get rid of scheme for URL (if a scheme is set).
if ( isset( $argv[2] ) && parse_url( $argv[2], PHP_URL_SCHEME ) ) {
	$argv[2] = parse_url( $argv[2], PHP_URL_HOST );
}

// Make sure that that site URL is set.
if ( ! isset( $argv[2] ) || ! $argv[2] ) {
	exit();
}

// Make sure batch importer ID is set.
if ( ! isset( $argv[3] ) || ! $argv[3] ) {
	exit();
}

// Set default request URI if none has been provided.
if ( ! isset( $argv[4] ) || ! $argv[4] ) {
	$argv[4] = '/';
}

// Make sure batch import key is set.
if ( ! isset( $argv[5] ) || ! $argv[5] ) {
	exit();
}

// Set up server global variable.
$_SERVER = array(
	'SERVER_PROTOCOL' => 'HTTP/1.1',
	'REQUEST_METHOD' => 'GET',
	'HTTP_HOST' => $argv[2],
	'SCRIPT_FILENAME' => __FILE__,
	'REQUEST_URI' => $argv[4],
);

// Set up GET parameters.
$_GET = array(
	'sme_batch_importer_id' => $argv[3],
	'sme_import_batch_key'  => $argv[5],
);

chdir( $argv[1] );

define( 'WP_USE_THEMES', false );

require_once( $argv[1] . 'wp-blog-header.php' );
