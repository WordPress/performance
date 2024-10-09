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
	 * @param mixed $offset Key.
	 * @return mixed Can return all value types.
	 */
	public function offsetGet( $offset ) { // phpcs:ignore SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
		if ( ! is_scalar( $offset ) ) {
			return null;
		}
		return $this->data[ $offset ] ?? null;
	}

	/**
	 * Sets an offset.
	 *
	 * @param TKey   $offset Key.
	 * @param TValue $value  Value.
	 */
	public function offsetSet( $offset, $value ): void {
		// @todo Throw an error.
		$this->data[ $offset ] = $value;
	}

	/**
	 * Offset to unset
	 * @link https://php.net/manual/en/arrayaccess.offsetunset.php
	 *
	 * @param TKey $offset <p>
	 *                     The offset to unset.
	 *                     </p>
	 *
	 * @return void
	 */
	public function offsetUnset( $offset ){
		// @todo Throw an error since read only.
		unset( $this->data[ $offset ] );
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
