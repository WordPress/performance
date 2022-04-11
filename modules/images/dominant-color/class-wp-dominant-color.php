<?php
/**
 *  Add Dominant Color preload support to WordPress.
 *
 * @package performance-lab
 * @since 1.0.0
 */

/**
 * Add Dominant Color preload support to WordPress.
 *
 * @since 1.0.0
 */
class WP_Dominant_Color {

	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_filter( 'wp_enqueue_scripts', array( $this, 'add_inline_style' ) );

		add_filter( 'wp_generate_attachment_metadata', array( $this, 'dominant_color_metadata' ), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'has_transparency_metadata' ), 10, 2 );
		add_filter( 'wp_content_img_tag', array( $this, 'tag_add_adjust' ), 20, 3 );
		add_filter( 'wp_dominant_color_img_tag_add_adjust', array( $this, 'tag_add_adjust' ), 10, 3 );

		$filters = array( 'the_content', 'the_excerpt', 'widget_text_content', 'widget_block_content' );
		foreach ( $filters as $filter ) {
			add_filter( $filter, array( $this, 'filter_content_tags' ), 20 );
		}

		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'tag_add_adjust_to_image_attributes' ), 10, 2 );
	}

	/**
	 * Add the dominant color metadata to the attachment.
	 *
	 * @param array $metadata The attachment metadata.
	 * @param int   $attachment_id The attachment ID.
	 *
	 * @return array $metadata
	 */
	public function dominant_color_metadata( $metadata, $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {

			return $metadata;
		}
		$dominant_color = $this->get_dominant_color( $attachment_id );

		if ( ! empty( $dominant_color ) ) {
			$metadata['dominant_color'] = $dominant_color;
		}

		return $metadata;
	}

	/**
	 * Add the dominant color metadata to the attachment.
	 *
	 * @param array $metadata Metadata for the attachment.
	 * @param int   $attachment_id attachement id.
	 *
	 * @return array $metadata
	 */
	public function has_transparency_metadata( $metadata, $attachment_id ) {

		if ( ! wp_attachment_is_image( $attachment_id ) ) {

			return $metadata;
		}

		$has_transparency = $this->get_has_transparency( $attachment_id );

		if ( ! empty( $has_transparency ) ) {
			$metadata['has_transparency'] = $has_transparency;
		}

		return $metadata;
	}

	/**
	 * Filter various image attributes to add the dominant color to the image
	 *
	 * @param array  $attr        Attributes for the image markup.
	 * @param object $attachment Image attachment post.
	 *
	 * @return mixed
	 */
	public function tag_add_adjust_to_image_attributes( $attr, $attachment ) {

		$image_meta = wp_get_attachment_metadata( $attachment->ID );
		if ( ! is_array( $image_meta ) ) {

			return $attr;
		}

		$has_transparency = isset( $image_meta['has_transparency'] ) ? $image_meta['has_transparency'] : false;

		$extra_class = '';
		if ( ! isset( $attr['style'] ) ) {
			$attr['style'] = ' --has-transparency: ' . $has_transparency . '; ';
		} else {
			$attr['style'] .= ' --has-transparency: ' . $has_transparency . '; ';
			$extra_class    = ' has-transparency ';
		}

		if ( isset( $image_meta['dominant_color'] ) ) {
			if ( ! isset( $attr['style'] ) ) {
				$attr['style'] = '--dominant-color: #' . $image_meta['dominant_color'] . ';';
			} else {
				$attr['style'] .= '--dominant-color: #' . $image_meta['dominant_color'] . ';';
			}
			$attr['data-dominant-color'] = $image_meta['dominant_color'];

			$extra_class .= ( $this->color_is_light( $image_meta['dominant_color'] ) ) ? 'dominant-color-light' : 'dominant-color-dark';

			if ( isset( $attr['class'] ) && ! array_intersect( explode( ' ', $attr['class'] ), explode( ' ', $extra_class ) ) ) {
				$attr['class'] = $extra_class . ' ' . $attr['class'];
			} else {
				$attr['class'] = $extra_class;
			}
		}

		return $attr;
	}

	/**
	 * Filter image tags in content to add the dominant color to the image.
	 *
	 * @param string $filtered_image The filtered image.
	 * @param string $context        The context of the image.
	 * @param int    $attachment_id  The attachment ID.
	 *
	 * @return string image tag
	 */
	public function tag_add_adjust( $filtered_image, $context, $attachment_id ) {

		$image_meta = wp_get_attachment_metadata( $attachment_id );
		/**
		 * Allow developers to filter the contol the adding of dominant color to the image.
		 * set to false inorder disable adding the dominant color to the image.
		 *
		 * @param bool $add_dominant_color_to_image
		 * @param int  $attachment_id
		 * @param array $image_meta
		 * @param string $filtered_image
		 * @param string $context
		 */
		if ( apply_filters( 'enable_dominant_color_for_image', isset( $image_meta['dominant_color'] ), $attachment_id, $image_meta, $filtered_image, $context ) ) {
			$data  = sprintf( 'data-dominantColor="%s"', $image_meta['dominant_color'] );
			$style = '';
			if ( strpos( $filtered_image, 'loading="lazy"' ) !== false ) {
				$style = ' style="--dominant-color: #' . $image_meta['dominant_color'] . ';" ';
			}

			$extra_class = '';

			if ( ! isset( $image_meta['has_transparency'] ) ) {
				$data .= ' data-has-transparency="false"';
			} else {
				$data       .= ' data-has-transparency="true"';
				$extra_class = ' has-transparency ';
			}

			$filtered_image = str_replace( '<img ', '<img ' . $data . $style, $filtered_image );

			$extra_class   .= ( $this->color_is_light( $image_meta['dominant_color'] ) ) ? 'dominant-color-light' : 'dominant-color-dark';
			$filtered_image = str_replace( 'class="', 'class="' . $extra_class . ' ', $filtered_image );
		}

		return $filtered_image;
	}


	/**
	 * Add CSS needed for to show the dominant color as an image background.
	 *
	 * @return void
	 */
	public function add_inline_style() {
		$handle = 'dominant-color-styles';
		wp_register_style( $handle, false );
		wp_enqueue_style( $handle );
		$custom_css = 'img[data-dominantcolor]:not(.has-transparency) { background-color: var(--dominant-color); background-clip: content-box, padding-box; }';
		wp_add_inline_style( $handle, $custom_css );
	}


	/**
	 * Filter the content to allow us to filter the image tags.
	 *
	 * @param string $content the content to filter.
	 * @param string $context the context of the content.
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
			 * Warm the object cache with post and meta information for all found.
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
	 * Overloads wp_image_editors() to load the extended classes.
	 *
	 * @return string[]
	 */
	public function set_wp_image_editors() {

		require_once 'class-wp-image-editor-gd-with-color.php';
		require_once 'class-wp-image-editor-imagick-with-color.php';

		return array( 'WP_Image_Editor_GD_With_Color', 'WP_Image_Editor_Imagick_With_Color' );
	}

	/**
	 * Get dominant color of image
	 *
	 * @param integer $id the image id.
	 * @param string  $default_color default color.
	 *
	 * @return string
	 */
	public function get_dominant_color( $id, $default_color = 'eee' ) {

		add_filter( 'wp_image_editors', array( $this, 'set_wp_image_editors' ) );

		$file = get_attached_file( $id );

		$dominant_color = wp_get_image_editor( $file )->get_dominant_color( $default_color );

		remove_filter( 'wp_image_editors', array( $this, 'set_wp_image_editors' ) );

		return $dominant_color;

	}


	/**
	 * Works out if color has transparency
	 *
	 * @param integer $id the attachment id.
	 *
	 * @return bool
	 */
	public function get_has_transparency( $id ) {

		add_filter( 'wp_image_editors', array( $this, 'set_wp_image_editors' ) );

		$file = get_attached_file( $id );

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {

			return true; // safer to set to trans than not.
		}
		$has_transparency = wp_get_image_editor( $file )->get_has_transparency();

		remove_filter( 'wp_image_editors', array( $this, 'set_wp_image_editors' ) );

		return $has_transparency;
	}


	/**
	 * Works out if the color is dark or light.
	 *
	 * @param string $hex color in hex.
	 *
	 * @return bool
	 */
	function color_is_light( $hex ) {
		$hex       = str_replace( '#', '', $hex );
		$r         = ( hexdec( substr( $hex, 0, 2 ) ) / 255 );
		$g         = ( hexdec( substr( $hex, 2, 2 ) ) / 255 );
		$b         = ( hexdec( substr( $hex, 4, 2 ) ) / 255 );
		$lightness = round( ( ( ( max( $r, $g, $b ) + min( $r, $g, $b ) ) / 2 ) * 100 ) );

		return ( $lightness >= 50 ? true : false );
	}

}
