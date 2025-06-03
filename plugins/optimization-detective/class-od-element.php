<?php
/**
 * Optimization Detective: OD_Element class
 *
 * @package optimization-detective
 * @since 0.7.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Data for a single element in a URL Metric.
 *
 * @phpstan-import-type ElementData from OD_URL_Metric
 * @phpstan-import-type DOMRect from OD_URL_Metric
 * @implements ArrayAccess<key-of<ElementData>, ElementData[key-of<ElementData>]>
 * @todo The above implements tag should account for additional undefined keys which can be supplied by extending the element schema. May depend on <https://github.com/phpstan/phpstan/issues/8438>.
 *
 * @since 0.7.0
 */
class OD_Element implements ArrayAccess, JsonSerializable {

	/**
	 * Data.
	 *
	 * @since 0.7.0
	 * @var ElementData
	 */
	protected $data;

	/**
	 * URL Metric that this element belongs to.
	 *
	 * @since 0.7.0
	 * @var OD_URL_Metric
	 */
	protected $url_metric;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @phpstan-param ElementData $data
	 *
	 * @param array<string, mixed> $data       Element data.
	 * @param OD_URL_Metric        $url_metric URL Metric.
	 */
	public function __construct( array $data, OD_URL_Metric $url_metric ) {
		$this->data = $data;

		$this->url_metric = $url_metric;
	}

	/**
	 * Gets the URL Metric that this element belongs to.
	 *
	 * @since 0.7.0
	 *
	 * @return OD_URL_Metric URL Metric.
	 */
	public function get_url_metric(): OD_URL_Metric {
		return $this->url_metric;
	}

	/**
	 * Gets the group that this element's URL Metric is a part of (which may not be any).
	 *
	 * @since 0.7.0
	 *
	 * @return OD_URL_Metric_Group|null Group.
	 */
	public function get_url_metric_group(): ?OD_URL_Metric_Group {
		return $this->url_metric->get_group();
	}

	/**
	 * Gets property value for an arbitrary key.
	 *
	 * This is particularly useful in conjunction with the `od_url_metric_schema_element_item_additional_properties` filter.
	 *
	 * @since 0.7.0
	 *
	 * @param string $key Property.
	 * @return mixed|null The property value, or null if not set.
	 */
	public function get( string $key ) {
		return $this->data[ $key ] ?? null;
	}

	/**
	 * Determines whether element was detected as LCP.
	 *
	 * @since 0.7.0
	 *
	 * @return bool Whether LCP.
	 */
	public function is_lcp(): bool {
		return $this->data['isLCP'];
	}

	/**
	 * Determines whether element was detected as an LCP candidate.
	 *
	 * @since 0.7.0
	 *
	 * @return bool Whether LCP candidate.
	 */
	public function is_lcp_candidate(): bool {
		return $this->data['isLCPCandidate'];
	}

	/**
	 * Gets XPath for element.
	 *
	 * @since 0.7.0
	 *
	 * @return non-empty-string XPath.
	 */
	public function get_xpath(): string {
		return $this->data['xpath'];
	}

	/**
	 * Gets intersectionRatio for element.
	 *
	 * @since 0.7.0
	 *
	 * @return float Intersection ratio.
	 */
	public function get_intersection_ratio(): float {
		return $this->data['intersectionRatio'];
	}

	/**
	 * Gets intersectionRect for element.
	 *
	 * @since 0.7.0
	 *
	 * @phpstan-return DOMRect
	 *
	 * @return array Intersection rect.
	 */
	public function get_intersection_rect(): array {
		return $this->data['intersectionRect'];
	}

	/**
	 * Gets boundingClientRect for element.
	 *
	 * @since 0.7.0
	 *
	 * @phpstan-return DOMRect
	 *
	 * @return array Bounding client rect.
	 */
	public function get_bounding_client_rect(): array {
		return $this->data['boundingClientRect'];
	}

	/**
	 * Checks whether an offset exists.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $offset Key.
	 * @return bool Whether the offset exists.
	 */
	public function offsetExists( $offset ): bool {
		return isset( $this->data[ $offset ] );
	}

	/**
	 * Retrieves an offset.
	 *
	 * @since 0.7.0
	 *
	 * @template T of key-of<ElementData>
	 * @phpstan-param T $offset
	 * @phpstan-return ElementData[T]|null
	 * @todo This should account for additional undefined keys which can be supplied by extending the element schema. May depend on <https://github.com/phpstan/phpstan/issues/8438>.
	 *
	 * @param mixed $offset Key.
	 * @return mixed May return any value from ElementData including possible extensions.
	 */
	#[ReturnTypeWillChange]
	public function offsetGet( $offset ) {
		return $this->data[ $offset ] ?? null;
	}

	/**
	 * Sets an offset.
	 *
	 * This is disallowed. Attempting to set a property will throw an error.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $offset Key.
	 * @param mixed $value  Value.
	 *
	 * @throws Exception When attempting to set a property.
	 */
	public function offsetSet( $offset, $value ): void {
		throw new Exception( 'Element data may not be set.' );
	}

	/**
	 * Offset to unset.
	 *
	 * This is disallowed. Attempting to unset a property will throw an error.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $offset Offset.
	 *
	 * @throws Exception When attempting to unset a property.
	 */
	public function offsetUnset( $offset ): void {
		throw new Exception( 'Element data may not be unset.' );
	}

	/**
	 * Specifies data which should be serialized to JSON.
	 *
	 * @since 0.7.0
	 * @return ElementData Exports to be serialized by json_encode().
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}
}
