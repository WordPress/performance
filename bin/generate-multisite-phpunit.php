#!/usr/bin/env php
<?php
/**
 * Generate phpunit-multisite.xml based on the current phpunit.xml or phpunit.xml.dist.
 *
 * @package performance
 */

// phpcs:disable WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

if ( file_exists( __DIR__ . '/../phpunit.xml' ) ) {
	$phpunit_xml_file = 'phpunit.xml';
} else {
	$phpunit_xml_file = 'phpunit.xml.dist';
}
$phpunit_xml_path = __DIR__ . '/../' . $phpunit_xml_file;

$xml = file_get_contents( $phpunit_xml_path );
if ( ! $xml ) {
	fwrite( STDERR, "Unable to load $phpunit_xml_path.\n" );
	exit( 1 );
}

$dom = new DOMDocument();
if ( ! $dom->loadXML( $xml ) ) {
	fwrite( STDERR, "Unable to parse $phpunit_xml_path.\n" );
	exit( 1 );
}

$php   = $dom->createElement( 'php' );
$const = $dom->createElement( 'const' ); // <const name="WP_TESTS_MULTISITE" value="1" />
$const->setAttribute( 'name', 'WP_TESTS_MULTISITE' );
$const->setAttribute( 'value', '1' );
$php->appendChild( $const );
$dom->documentElement->insertBefore( $php, $dom->documentElement->firstChild );
$comment_node = $dom->createComment( sprintf( 'THIS FILE WAS AUTOMATICALLY GENERATED FROM %s BY %s.', $phpunit_xml_file, __FILE__ ) );
$dom->insertBefore( $comment_node, $dom->documentElement );

if ( ! file_put_contents( __DIR__ . '/../phpunit-multisite.xml', $dom->saveXML() ) ) {
	fwrite( STDERR, "Unable to write $phpunit_xml_path.\n" );
	exit( 1 );
}
