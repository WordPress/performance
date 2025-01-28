<?php
/**
 * Image Prioritizer: IP_Image_Tag_Visitor class
 *
 * @package image-prioritizer
 * @since 0.1.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Tag visitor that optimizes image tags.
 *
 * @phpstan-type NormalizedAttributeNames 'fetchpriority'|'loading'|'crossorigin'|'preload'|'referrerpolicy'|'type'
 *
 * @since 0.1.0
 * @access private
 */
abstract class Image_Prioritizer_Tag_Visitor {

	/**
	 * Visits a tag.
	 *
	 * @param OD_Tag_Visitor_Context $context Tag visitor context.
	 * @return bool Whether the tag should be tracked in URL Metrics.
	 */
	abstract public function __invoke( OD_Tag_Visitor_Context $context ): bool;

	/**
	 * Determines if the provided URL is a data: URL.
	 *
	 * @param string $url URL.
	 * @return bool Whether data URL.
	 */
	protected function is_data_url( string $url ): bool {
		return str_starts_with( strtolower( $url ), 'data:' );
	}

	/**
	 * Gets attribute value for select attributes.
	 *
	 * @since 0.2.0
	 * @todo Move this into the OD_HTML_Tag_Processor/OD_HTML_Processor class eventually.
	 * @todo It would be nice if PHPStan could know that if you pass 'crossorigin' as $attribute_name that you will get back null|'anonymous'|'use-credentials'.
	 *
	 * @phpstan-param NormalizedAttributeNames $attribute_name
	 *
	 * @param OD_HTML_Tag_Processor $processor      Processor.
	 * @param string                $attribute_name Attribute name.
	 * @return string|true|null Normalized attribute value.
	 */
	protected function get_attribute_value( OD_HTML_Tag_Processor $processor, string $attribute_name ) {
		$value = $processor->get_attribute( $attribute_name );
		if ( null === $value ) {
			return null;
		}

		if ( is_string( $value ) ) {
			$value = strtolower( trim( $value, " \t\f\r\n" ) );
		}
		if ( 'crossorigin' === $attribute_name && 'use-credentials' !== $value ) {
			$value = 'anonymous';
		}
		return $value;
	}
}
