<?php

namespace PerformanceLab\Tests\Constraint;

use PHPUnit\Framework\Constraint\Constraint;

/**
 * A constraint that checks a certain source with provided mime type.
 */
class ImageHasSource extends Constraint {

	/**
	 * The requested mime type.
	 *
	 * @var string
	 */
	protected $mime_type;

	/**
	 * Determines whether we need to check for absence or for existence of the mime type.
	 *
	 * @var bool
	 */
	protected $is_not;

	/**
	 * Constructor.
	 *
	 * @param string $mime_type The requested mime type.
	 */
	public function __construct( $mime_type ) {
		$this->is_not    = false;
		$this->mime_type = $mime_type;
	}

	/**
	 * Tells to check for absence of the mime type.
	 */
	public function isNot() {
		$this->is_not = true;
	}

	/**
	 * Returns a string representation of the constraint.
	 *
	 * @return string String representation of the constraint.
	 */
	public function toString(): string {
		return sprintf(
			'%s the source with the "%s" mime type',
			$this->is_not ? 'doesn\'t have' : 'has',
			$this->mime_type
		);
	}

	/**
	 * Evaluates the constraint for the provided attachment ID.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool TRUE if the attachment has a source for the requested mime type, otherwise FALSE.
	 */
	protected function matches( $attachment_id ): bool {
		$metadata = wp_get_attachment_metadata( $attachment_id );

		// Fail if there is no metadata for the provided attachment ID.
		if ( ! is_array( $metadata ) ) {
			return false;
		}

		// Fail if metadata doesn't contain the sources property.
		if (
			! isset( $metadata['sources'] ) ||
			! is_array( $metadata['sources'] )
		) {
			return false;
		}

		return $this->verify_sources( $metadata['sources'] );
	}

	/**
	 * Verifies the sources to have the requested mime type.
	 *
	 * @param array $sources The sources array.
	 * @return bool TRUE if the sources array contains the correct mime type source, otherwise FALSE.
	 */
	protected function verify_sources( $sources ) {
		// Fail if the mime type is supposed not to exist, but it is set.
		if ( $this->is_not ) {
			return ! isset( $sources[ $this->mime_type ] );
		}

		// Fail if metadata doesn't have the requested mime type in the sources property.
		if (
			! isset( $sources[ $this->mime_type ] ) ||
			! is_array( $sources[ $this->mime_type ] )
		) {
			return false;
		}

		// Fail if the file property is empty or not a string.
		if (
			empty( $sources[ $this->mime_type ]['file'] ) ||
			! is_string( $sources[ $this->mime_type ]['file'] )
		) {
			return false;
		}

		// Fail if the file has wrong extension.
		$allowed_mimes = array_flip( wp_get_mime_types() );
		if ( isset( $allowed_mimes[ $this->mime_type ] ) ) {
			$extensions = explode( '|', $allowed_mimes[ $this->mime_type ] );
			$extension  = pathinfo( $sources[ $this->mime_type ]['file'], PATHINFO_EXTENSION );
			if ( ! in_array( strtolower( $extension ), $extensions, true ) ) {
				return false;
			}
		}

		// Fail if the filesize property is not set or it is not a number.
		if (
			! isset( $sources[ $this->mime_type ]['filesize'] ) ||
			! is_numeric( $sources[ $this->mime_type ]['filesize'] )
		) {
			return false;
		}

		return true;
	}

	/**
	 * Returns the description of the failure.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string The description of the failure.
	 */
	protected function failureDescription( $attachment_id ): string {
		return sprintf( 'an image %s', $this->toString() );
	}
}
