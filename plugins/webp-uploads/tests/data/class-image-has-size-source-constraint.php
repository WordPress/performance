<?php

namespace WebP_Uploads\Tests\Constraint;

// Load `Image_Has_Source_Constraint` class if it's not loaded yet.
if ( ! class_exists( Image_Has_Source_Constraint::class ) ) {
	require_once __DIR__ . '/class-image-has-source-constraint.php';
}

/**
 * A constraint that checks a certain subsize source with provided mime type.
 */
class Image_Has_Size_Source_Constraint extends Image_Has_Source_Constraint {

	/**
	 * The requested size.
	 *
	 * @var string
	 */
	protected $size;

	/**
	 * Constructor.
	 *
	 * @param string $mime_type The requested mime type.
	 * @param string $size The requested size.
	 */
	public function __construct( string $mime_type, string $size ) {
		parent::__construct( $mime_type );
		$this->size = $size;
	}

	/**
	 * Returns a string representation of the constraint.
	 *
	 * @return string String representation of the constraint.
	 */
	public function toString(): string {
		return sprintf(
			'%s the source with the "%s" mime type for the "%s" size',
			$this->is_not ? 'doesn\'t have' : 'has',
			$this->mime_type,
			$this->size
		);
	}

	/**
	 * Evaluates the constraint for the provided attachment ID.
	 *
	 * @param mixed $other Attachment ID.
	 * @return bool TRUE if the attachment has a source with the requested mime type for the subsize, otherwise FALSE.
	 */
	protected function matches( $other ): bool {
		if ( ! is_int( $other ) ) {
			return false;
		}
		$metadata = wp_get_attachment_metadata( $other );

		// Fail if there is no metadata for the provided attachment ID.
		if ( ! is_array( $metadata ) ) {
			return false;
		}

		// Fail if metadata doesn't contain the sources property.
		if (
			! isset( $metadata['sizes'][ $this->size ]['sources'] ) ||
			! is_array( $metadata['sizes'][ $this->size ]['sources'] )
		) {
			// Don't fail if we intentionally check that the image doesn't have a mime type for the size.
			return $this->is_not ? true : false;
		}

		return $this->verify_sources( $metadata['sizes'][ $this->size ]['sources'] );
	}
}
