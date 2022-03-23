<?php
/**
 * Module Name: Dominant Color
 * Description: Adds support to store dominant-color for an image and create a placeholder background for that color.
 * Experimental: Yes
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Add Dominant Color preload support to WordPress.
 *
 * TODO: Add tests
 */
class wp_Dominant_Color {


	public function __construct() {

		add_filter( 'wp_print_scripts', [ $this, 'add_styles' ] );

		add_filter( 'wp_generate_attachment_metadata', [ $this, 'dominant_color_metadata' ], 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', [ $this, 'has_transparency_metadata' ], 10, 2 );

		// do we have the new filter or are duplicating core the functions?
		if ( has_filter( 'wp_img_tag_add_adjust' ) ) {
			add_filter( 'wp_img_tag_add_adjust', [ $this, 'tag_add_adjust' ], 20, 3 );
		} else {
			add_filter( 'the_content', [ $this, 'filter_content_tags' ], 20 );
			add_filter( 'wp_dominant_color_img_tag_add_adjust', [ $this, 'tag_add_adjust' ], 10, 3 );
		}

		add_filter( 'attachment_fields_to_edit', [ $this, 'add_dominant_color_media_setting' ], 10, 2 );
		add_filter( 'attachment_fields_to_save', [ $this, 'save_dominant_color_media_setting' ], 10, 2 );
	}

	/**
	 * Add the dominant color metadata to the attachment.
	 *
	 * @param array $metadata
	 * @param int $attachment_id
	 *
	 * @return array $metadata
	 */
	public function dominant_color_metadata( $metadata, $attachment_id ) {

		$dominant_color = $this->get_dominant_color( $attachment_id );

		if ( ! empty( $dominant_color ) ) {
			$metadata['dominant_color'] = $dominant_color;
		}

		return $metadata;
	}

	/**
	 * Add the dominant color metadata to the attachment.
	 *
	 * @param array $metadata
	 * @param int $attachment_id
	 *
	 * @return array $metadata
	 */
	public function has_transparency_metadata( $metadata, $attachment_id ) {

		$has_transparency = $this->get_has_transparency( $attachment_id );

		if ( ! empty( $has_transparency ) ) {
			$metadata['has_transparency'] = $has_transparency;
		}

		return $metadata;
	}

	/**
	 * @param $filtered_image
	 * @param $context
	 * @param $attachment_id
	 *
	 * @return string image tag
	 */
	public function tag_add_adjust( $filtered_image, $context, $attachment_id ) {

		$image_meta = wp_get_attachment_metadata( $attachment_id );

		if ( isset( $image_meta['dominant_color'] ) ) {
			$data  = sprintf( 'data-dominantColor="%s"', $image_meta['dominant_color'] );
			$style = '';
			if ( strpos( $filtered_image, 'loading="lazy"' ) !== false && ( isset( $image_meta['show_dominant_color'] ) && 1 === (int) $image_meta['show_dominant_color'] ) ) {
				$style = ' style="--dominant-color: #' . $image_meta['dominant_color'] . '; " ';
			}

			$extra_class = '';

			if ( ! isset( $image_meta['has_transparency'] ) ) {
				$data        .= ' data-has-transparency="false"';
			} else {
				$data .= ' data-has-transparency="true"';
				$extra_class = ' has-transparency ';
			}

			$filtered_image = str_replace( '<img ', '<img ' . $data . $style, $filtered_image );

			$extra_class    .= ( $this->colorislight( $image_meta['dominant_color'] ) ) ? 'dominant-color-light' : 'dominant-color-dark';
			$filtered_image = str_replace( 'class="', 'class="' . $extra_class . ' ', $filtered_image );
		}

		return $filtered_image;
	}


	/**
	 * @return void
	 */
	public function add_styles() {
		?>
		<style>
			img[data-dominantcolor]:not(.has-transparency) {
				background-color: var(--dominant-color);
				background-clip: content-box, padding-box;
			}
		</style>
		<?php
	}


	/**
	 * @param $content
	 * @param $context
	 *
	 * @return string content
	 */
	public function filter_content_tags( $content, $context = null ) {
		if ( null === $context ) {
			$context = current_filter();
		}

		if ( ! preg_match_all( '/<(img)\s[^>]+>/', $content, $matches, PREG_SET_ORDER ) ) {
			return $content;
		}

		// List of the unique `img` tags found in $content.
		$images = array();

		foreach ( $matches as $match ) {
			list( $tag, $tag_name ) = $match;

			switch ( $tag_name ) {
				case 'img':
					if ( preg_match( '/wp-image-([0-9]+)/i', $tag, $class_id ) ) {
						$attachment_id = absint( $class_id[1] );

						if ( $attachment_id ) {
							// If exactly the same image tag is used more than once, overwrite it.
							// All identical tags will be replaced later with 'str_replace()'.
							$images[ $tag ] = $attachment_id;
							break;
						}
					}
					$images[ $tag ] = 0;
					break;
			}
		}

		// Reduce the array to unique attachment IDs.
		$attachment_ids = array_unique( array_filter( array_values( $images ) ) );

		if ( count( $attachment_ids ) > 1 ) {
			/*
			 * Warm the object cache with post and meta information for all found
			 * images to avoid making individual database calls.
			 */
			_prime_post_caches( $attachment_ids, false, true );
		}

		// Iterate through the matches in order of occurrence as it is relevant for whether or not to lazy-load.
		foreach ( $matches as $match ) {
			// Filter an image match.
			if ( isset( $images[ $match[0] ] ) ) {
				$filtered_image = $match[0];
				$attachment_id  = $images[ $match[0] ];
				/**
				 * Filters img tag that will be injected into the content.
				 *
				 * @param string $filtered_image the img tag with attributes being created that will
				 *                                    replace the source img tag in the content.
				 * @param string $context Optional. Additional context to pass to the filters.
				 *                        Defaults to `current_filter()` when not set.
				 * @param int $attachment_id the ID of the image attachment.
				 *
				 * @since 1.0.0
				 *
				 */
				$filtered_image = apply_filters( 'wp_dominant_color_img_tag_add_adjust', $filtered_image, $context, $attachment_id );

				if ( $filtered_image !== $match[0] ) {
					$content = str_replace( $match[0], $filtered_image, $content );
				}
			}
		}

		return $content;
	}

	/**
	 * Add checkbox setting to enable/disable dominant color for a given media.
	 *
	 * @param array $form_fields
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public function add_dominant_color_media_setting( array $form_fields, WP_Post $post ) {
		$image_meta     = wp_get_attachment_metadata( $post->ID );
		$checked_text   = isset( $image_meta['show_dominant_color'] ) ? 'checked' : '';
		$dominant_color = isset( $image_meta['dominant_color'] ) ? $image_meta['dominant_color'] : '';
		$contrast_color = $this->getContrastYIQ( $dominant_color );
		$html_input     = "<input type='checkbox' $checked_text value='1'
			name='attachments[{$post->ID}][show_dominant_color]' id='attachments[{$post->ID}][show_dominant_color]'/>
			<span style='background-color: #{$dominant_color}; color: {$contrast_color}; padding: 3px 6px; margin-top: 3px;vertical-align: middle;display: inline-block; '>#{$dominant_color}</span>";

		$form_fields['show_dominant_color'] = array(
			'label' => __( 'Show Dominant color as a preload placeholder', 'wp-dominant-color' ),
			'input' => 'html',
			'html'  => $html_input,
		);

		return $form_fields;
	}

	/**
	 * Save dominant color setting value for a media post.
	 *
	 * @param array $post
	 * @param array $attachment
	 *
	 * @return array
	 */
	public function save_dominant_color_media_setting( array $post, array $attachment ) {
		$attachment_id = $post['ID'];
		$image_meta    = wp_get_attachment_metadata( $attachment_id );

		if ( isset( $attachment['show_dominant_color'] ) ) {
			if ( ! isset( $image_meta['show_dominant_color'] ) ) {
				// If enabling dominant color from media setting and image meta doesn't have any, generate a new one.
				$image_meta                        = $this->dominant_color_metadata( $image_meta, $attachment_id );
				$image_meta['show_dominant_color'] = $attachment['show_dominant_color'] ? '1' : '0';
				wp_update_attachment_metadata( $attachment_id, $image_meta );
			}
		} elseif ( isset( $image_meta['dominant_color'] ) ) {
			// If disabling dominant color from media setting and dominant color set in image meta, unset it.
			unset( $image_meta['show_dominant_color'] );
			wp_update_attachment_metadata( $attachment_id, $image_meta );
		}

		return $post;
	}

	/**
	 * @param $id
	 * @param $default_color
	 *
	 * @return string
	 */
	public function get_dominant_color( $id, $default_color = 'eee' ) {
		$file         = get_attached_file( $id );
		$image_editor = _wp_image_editor_choose( $file );
		if ( $image_editor === 'WP_Image_Editor_GD' ) {
			return $this->get_dominant_color_GD( $file, $default_color );
		} elseif ( $image_editor === 'WP_Image_Editor_Imagick' ) {
			return $this->get_dominant_color_Imagick( $file, $default_color );
		}

		return $default_color;
	}

	/**
	 * @param $file
	 * @param $default_color
	 *
	 * @return string
	 */
	private function get_dominant_color_GD( $file, $default_color = 'eee' ) {

		wp_raise_memory_limit( 'image' );
		if ( $original_image = @imagecreatefromstring( @file_get_contents( $file ) ) ) {
			$shortend_image = imagecreatetruecolor( 1, 1 );
			imagecopyresampled( $shortend_image, $original_image, 0, 0, 0, 0, 1, 1, imagesx( $original_image ), imagesy( $original_image ) );

			return dechex( imagecolorat( $shortend_image, 0, 0 ) );
		} else {
			return $default_color;
		}
	}

	/**
	 * @param $file
	 * @param $default_color
	 *
	 * @return string
	 */
	private function get_dominant_color_Imagick( $file, $default_color = 'eee' ) {

		wp_raise_memory_limit( 'image' );
		try {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( $original_image = new Imagick( $file ) ) {

				$original_image->setImageColorspace( Imagick::COLORSPACE_RGB );
				$original_image->setImageFormat( 'RGB' );
				$original_image->resizeImage( 1, 1, Imagick::FILTER_LANCZOS, 1 );
				$pixel = $original_image->getImagePixelColor( 0, 0 );
				$color = $pixel->getColor();

				return dechex( $color['r'] ) . dechex( $color['g'] ) . dechex( $color['b'] );
			} else {
				return $default_color;
			}
		} catch ( Exception $e ) {
			return $default_color;
		}
	}

	/**
	 * @param $id
	 *
	 * @return bool
	 */
	public function get_has_transparency( $id ) {
		$file         = get_attached_file( $id );
		$image_editor = _wp_image_editor_choose( $file );
		if ( $image_editor === 'WP_Image_Editor_GD' ) {
			return $this->get_has_transparency_GD( $file );
		} elseif ( $image_editor === 'WP_Image_Editor_Imagick' ) {
			return $this->get_has_transparency_Imagick( $file );
		}

		return false;
	}

	/**
	 * @param $file
	 *
	 * @return bool
	 */
	function get_has_transparency_GD( $file ) {
		wp_raise_memory_limit( 'image' );
		if ( $original_image = @imagecreatefromstring( @file_get_contents( $file ) ) ) {
			return imageistruecolor( $original_image ) ? imagecolortransparent( $original_image ) > 0 : imagecolorsforindex( $original_image, imagecolortransparent( $original_image ) )['alpha'] > 0;
		} else {
			return false;
		}

	}

	/**
	 * @param $file
	 *
	 * @return bool
	 * @throws ImagickException
	 */
	function get_has_transparency_Imagick( $file ) {
		wp_raise_memory_limit( 'image' );
		if ( $original_image = new Imagick( $file ) ) {

			try {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				return (bool) @$original_image->getImageAlphaChannel() == Imagick::ALPHACHANNEL_TRANSPARENT;
			} catch ( Exception $e ) {
				return false;
			}
		} else {
			return false;
		}
	}

	function colorislight( $hex ) {
		$hex       = str_replace( '#', '', $hex );
		$r         = ( hexdec( substr( $hex, 0, 2 ) ) / 255 );
		$g         = ( hexdec( substr( $hex, 2, 2 ) ) / 255 );
		$b         = ( hexdec( substr( $hex, 4, 2 ) ) / 255 );
		$lightness = round( ( ( ( max( $r, $g, $b ) + min( $r, $g, $b ) ) / 2 ) * 100 ) );

		return ( $lightness >= 50 ? true : false );
	}

	function getContrastYIQ( $hexcolor ) {
		$r   = hexdec( substr( $hexcolor, 0, 2 ) );
		$g   = hexdec( substr( $hexcolor, 2, 2 ) );
		$b   = hexdec( substr( $hexcolor, 4, 2 ) );
		$yiq = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;

		return ( $yiq >= 128 ) ? 'black' : 'white';
	}

}

new wp_Dominant_Color();
