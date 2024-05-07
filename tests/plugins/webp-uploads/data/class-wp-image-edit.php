<?php
/**
 * A WP_Image_Edit class to avoid abstraction to handle image edits.
 *
 * @package webp-uploads
 */

/**
 * Class WP_Image_Edit to handle the image edits that take place from the image editor
 * from within the admin dashboard to remove complexity around changes for an image.
 *
 * @since 1.0.0
 */
class WP_Image_Edit {
	protected $changes       = array();
	protected $target        = 'all';
	protected $attachment_id = 0;
	protected $result;

	/**
	 * Constructor
	 *
	 * @param int $attachment_id ID of the attachment for the image.
	 */
	public function __construct( int $attachment_id ) {
		$this->attachment_id = $attachment_id;
	}

	/**
	 * Once the object is removed make sure that `history` and `target` are
	 * removed from the $_REQUEST.
	 */
	public function __destruct() {
		unset( $_REQUEST['history'], $_REQUEST['target'] );
	}

	/**
	 * Register a change to rotate an image to the right.
	 *
	 * @return $this
	 */
	public function rotate_right() {
		$this->changes[] = array( 'r' => -90 );

		return $this;
	}

	/**
	 * Register a new change to rotate an image to the left.
	 *
	 * @return $this
	 */
	public function rotate_left() {
		$this->changes[] = array( 'r' => 90 );

		return $this;
	}

	/**
	 * Add a new change, to flip an image vertically.
	 *
	 * @return $this
	 */
	public function flip_vertical() {
		$this->changes[] = array( 'f' => 1 );

		return $this;
	}

	/**
	 * Add a new change to the image to flip it right.
	 *
	 * @return $this
	 */
	public function flip_right() {
		$this->changes[] = array( 'f' => 2 );

		return $this;
	}

	/**
	 * Store a crop change for an image.
	 *
	 * @param int $width  The width of the crop.
	 * @param int $height The height of the crop.
	 * @param int $x      The X position on the axis where the image would be cropped.
	 * @param int $y      The Y position on the axis where the image would be cropped.
	 *
	 * @return $this
	 */
	public function crop( int $width, int $height, int $x, int $y ) {
		$this->changes[] = array(
			'c' => array(
				'x' => (int) $x,
				'y' => (int) $y,
				'w' => (int) $width,
				'h' => (int) $height,
			),
		);

		return $this;
	}

	/**
	 * Set the target of the edits to all the image sizes.
	 *
	 * @return $this
	 */
	public function all() {
		$this->target = 'all';

		return $this;
	}

	/**
	 * Set the target of the edit only to the thumbnail image.
	 *
	 * @return $this
	 */
	public function only_thumbnail() {
		$this->target = 'thumbnail';

		return $this;
	}

	/**
	 * Set the target to all image sizes except the thumbnail.
	 *
	 * @return $this
	 */
	public function all_except_thumbnail() {
		$this->target = 'nothumb';

		return $this;
	}

	/**
	 * Setup the $_REQUEST global so `wp_save_image` can process the image with the same editions
	 * performed into an image as it was performed from the editor.
	 *
	 * @see wp_save_image
	 *
	 * @return stdClass The operation resulted from calling `wp_save_image`
	 */
	public function save(): stdClass {
		$_REQUEST['target']  = $this->target;
		$_REQUEST['history'] = wp_slash( wp_json_encode( $this->changes ) );

		if ( ! function_exists( 'wp_save_image' ) ) {
			include_once ABSPATH . 'wp-admin/includes/image-edit.php';
		}

		$this->result = wp_save_image( $this->attachment_id );

		return $this->result;
	}

	/**
	 * Determine if the last operation executed to edit the image was successfully or not.
	 *
	 * @return bool whether the operation to save the image was successfully or not.
	 */
	public function success(): bool {
		if ( ! is_object( $this->result ) ) {
			return false;
		}

		$valid_target = true;
		// The thumbnail property is only set in `all` and `thumbnail` target.
		if ( 'all' === $this->target || 'thumbnail' === $this->target ) {
			$valid_target = property_exists( $this->result, 'thumbnail' );
		}

		return property_exists( $this->result, 'msg' ) && $valid_target && 'Image saved' === $this->result->msg;
	}
}
