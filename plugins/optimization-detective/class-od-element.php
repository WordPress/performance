<?php
/**
 * Optimization Detective: OD_Element class
 *
 * @package optimization-detective
 * @since n.e.x.t
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data for a single element in a URL metric.
 *
 * @phpstan-import-type ElementData from OD_URL_Metric
 * @phpstan-import-type DOMRect from OD_URL_Metric
 * @implements ArrayAccess<key-of<ElementData>, ElementData[key-of<ElementData>]>
 * @todo The above implements tag should account for additional undefined keys which can be supplied by extending the element schema. May depend on <https://github.com/phpstan/phpstan/issues/8438>.
 *
 * @since n.e.x.t
 * @access private
 */
class OD_Element implements ArrayAccess, JsonSerializable {

	/**
	 * Data.
	 *
	 * @since n.e.x.t
	 * @var ElementData
	 */
	protected $data;

	/**
	 * URL metric that this element belongs to.
	 *
	 * @since n.e.x.t
	 * @var OD_URL_Metric
	 * @readonly
	 */
	public $url_metric;

	/**
	 * Constructor.
	 *
	 * @phpstan-param ElementData $data
	 *
	 * @param array<string, mixed> $data       Element data.
	 * @param OD_URL_Metric        $url_metric URL metric.
	 */
	public function __construct( array $data, OD_URL_Metric $url_metric ) {
		$this->data       = $data;
		$this->url_metric = $url_metric;
	}

	/**
	 * Gets property value for an arbitrary key.
	 *
	 * This is particularly useful in conjunction with the `od_url_metric_schema_element_item_additional_properties` filter.
	 *
	 * @since n.e.x.t
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
	 * @since n.e.x.t
	 *
	 * @return bool Whether LCP.
	 */
	public function is_lcp(): bool {
		return $this->data['isLCP'];
	}

	/**
	 * Determines whether element was detected as an LCP candidate.
	 *
	 * @since n.e.x.t
	 *
	 * @return bool Whether LCP candidate.
	 */
	public function is_lcp_candidate(): bool {
		return $this->data['isLCPCandidate'];
	}

	/**
	 * Gets XPath for element.
	 *
	 * @since n.e.x.t
	 *
	 * @return non-empty-string XPath.
	 */
	public function get_xpath(): string {
		return $this->data['xpath'];
	}

	/**
	 * Gets intersectionRatio for element.
	 *
	 * @since n.e.x.t
	 *
	 * @return float Intersection ratio.
	 */
	public function get_intersection_ratio(): float {
		return $this->data['intersectionRatio'];
	}

	/**
	 * Gets intersectionRect for element.
	 *
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
	 * @since n.e.x.t
	 *
	 * @template T of key-of<ElementData>
	 * @phpstan-param T $offset
	 * @phpstan-return ElementData[T]|null
	 * @todo This should account for additional undefined keys which can be supplied by extending the element schema. May depend on <https://github.com/phpstan/phpstan/issues/8438>.
	 *
	 * @param mixed $offset Key.
	 * @return mixed May return any value from ElementData including possible extensions.
	 */
	public function offsetGet( $offset ) {
		return $this->data[ $offset ] ?? null;
	}

	/**
	 * Sets an offset.
	 *
	 * This is disallowed. Attempting to set a property will throw an error.
	 *
	 * @since n.e.x.t
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
	 * @since n.e.x.t
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
	 * @return ElementData Exports to be serialized by json_encode().
	 */
	public function jsonSerialize(): array {
		return $this->data;
	}
}
