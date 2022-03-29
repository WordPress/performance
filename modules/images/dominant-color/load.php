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
 * @since 1.0.0
 */
class wp_Dominant_Color {


	/**
	 * Class constructor.
	 */
	public function __construct() {

		add_filter( 'wp_print_scripts', array( $this, 'add_styles' ) );

		add_filter( 'wp_generate_attachment_metadata', array( $this, 'dominant_color_metadata' ), 10, 2 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'has_transparency_metadata' ), 10, 2 );
	add_filter( 'wp_content_img_tag', array( $this, 'tag_add_adjust' ), 20, 3 );
	add_filter( 'wp_dominant_color_img_tag_add_adjust', array( $this, 'tag_add_adjust' ), 10, 3 );
	$filters = array( 'the_content', 'the_excerpt', 'widget_text_content', 'widget_block_content' );
	foreach( $filters as $filter ) {
	       add_filter( $filter, array( $this, 'filter_content_tags' ), 20 );
	}	

		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'tag_add_adjust_to_image_attributes' ), 10, 2 );
	}

	/**
	 * Add the dominant color metadata to the attachment.
	 *
	 * @param array $metadata
	 * @param int   $attachment_id
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
	 * @param int   $attachment_id
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
	 * filter various image attributes to add the dominant color to the image
	 *
	 * @param $attr
	 * @param $attachment
	 *
	 * @return mixed
	 */
	public function tag_add_adjust_to_image_attributes( $attr, $attachment ) {

		$image_meta = wp_get_attachment_metadata( $attachment->ID );

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

			$extra_class .= ( $this->colorislight( $image_meta['dominant_color'] ) ) ? 'dominant-color-light' : 'dominant-color-dark';

			if ( isset( $attr['class'] ) && ! array_intersect( explode( ' ', $attr['class'] ), explode( ' ', $extra_class ) ) ) {
				$attr['class'] = $extra_class . ' ' . $attr['class'];
			} else {
				$attr['class'] = $extra_class;
			}
		}

		return $attr;
	}

	/**
	 * filter image tags in content to add the dominant color to the image.
	 *
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
				$data .= ' data-has-transparency="false"';
			} else {
				$data       .= ' data-has-transparency="true"';
				$extra_class = ' has-transparency ';
			}

			$filtered_image = str_replace( '<img ', '<img ' . $data . $style, $filtered_image );

			$extra_class   .= ( $this->colorislight( $image_meta['dominant_color'] ) ) ? 'dominant-color-light' : 'dominant-color-dark';
			$filtered_image = str_replace( 'class="', 'class="' . $extra_class . ' ', $filtered_image );
		}

		return $filtered_image;
	}


	/**
	 * Add Css needed for to show the dominant color as an image background.
	 *
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
	 * filter the content to allow us to filter the image tags
	 *
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
				 */
				$filtered_image = apply_filters( 'wp_dominant_color_img_tag_add_adjust', $filtered_image, $context, $attachment_id );

				if ( $filtered_image !== $match[0] ) {
					$content = str_replace( $match[0], $filtered_image, $content );
				}
			}
		}

		return $content;
	}

	public function set_wp_image_editors() {

		require_once 'WP_Image_Editor_GD_With_Color.php';
		require_once 'WP_Image_Editor_Imagick_With_Color.php';

		return array( 'WP_Image_Editor_GD_With_Color', 'WP_Image_Editor_Imagick_With_Color' );
	}

	/**
	 * Get dominant color of image
	 *
	 * @param integer $id
	 * @param string  $default_color
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
	 * @param integer $id
	 *
	 * @return bool
	 */
	public function get_has_transparency( $id ) {

		add_filter( 'wp_image_editors', array( $this, 'set_wp_image_editors' ) );

		$file = get_attached_file( $id );
		if ( wp_get_image_mime( $file ) === 'image/webp' ) {
			$webpinfo = $this->webpinfo( $file );

			return $webpinfo['Alpha'];
		}

		$has_transparency = wp_get_image_editor( $file )->get_has_transparency();

		remove_filter( 'wp_image_editors', array( $this, 'set_wp_image_editors' ) );

		return $has_transparency;
	}

	/**
	 * Get WebP file info.
	 *
	 * @link https://www.php.net/manual/en/function.pack.php unpack format reference.
	 * @link https://developers.google.com/speed/webp/docs/riff_container WebP document.
	 *
	 * @param string $file
	 *
	 * @return array|false Return associative array if success, return `false` for otherwise.
	 */
	private function webpinfo( $file ) {
		if ( ! is_file( $file ) ) {
			return false;
		} else {
			$file = realpath( $file );
		}

		$fp = fopen( $file, 'rb' );
		if ( ! $fp ) {
			return false;
		}

		$data = fread( $fp, 90 );

		fclose( $fp );
		unset( $fp );

		$header_format = 'A4Riff/' . // get n string
						 'I1Filesize/' . // get integer (file size but not actual size)
						 'A4Webp/' . // get n string
						 'A4Vp/' . // get n string
						 'A74Chunk';
		$header        = unpack( $header_format, $data );
		unset( $data, $header_format );

		if ( ! isset( $header['Riff'] ) || strtoupper( $header['Riff'] ) !== 'RIFF' ) {
			return false;
		}
		if ( ! isset( $header['Webp'] ) || strtoupper( $header['Webp'] ) !== 'WEBP' ) {
			return false;
		}
		if ( ! isset( $header['Vp'] ) || strpos( strtoupper( $header['Vp'] ), 'VP8' ) === false ) {
			return false;
		}

		if (
			strpos( strtoupper( $header['Chunk'] ), 'ANIM' ) !== false ||
			strpos( strtoupper( $header['Chunk'] ), 'ANMF' ) !== false
		) {
			$header['Animation'] = true;
		} else {
			$header['Animation'] = false;
		}

		if ( strpos( strtoupper( $header['Chunk'] ), 'ALPH' ) !== false ) {
			$header['Alpha'] = true;
		} else {
			if ( strpos( strtoupper( $header['Vp'] ), 'VP8L' ) !== false ) {
				// if it is VP8L, I assume that this image will be transparency
				// as described in https://developers.google.com/speed/webp/docs/riff_container#simple_file_format_lossless
				$header['Alpha'] = true;
			} else {
				$header['Alpha'] = false;
			}
		}

		unset( $header['Chunk'] );

		return $header;
	}//end webpinfo()

	/**
	 * works out if the color is dark or light
	 *
	 * @param $hex
	 *
	 * @return bool
	 */
	function colorislight( $hex ) {
		$hex       = str_replace( '#', '', $hex );
		$r         = ( hexdec( substr( $hex, 0, 2 ) ) / 255 );
		$g         = ( hexdec( substr( $hex, 2, 2 ) ) / 255 );
		$b         = ( hexdec( substr( $hex, 4, 2 ) ) / 255 );
		$lightness = round( ( ( ( max( $r, $g, $b ) + min( $r, $g, $b ) ) / 2 ) * 100 ) );

		return ( $lightness >= 50 ? true : false );
	}

}

new wp_Dominant_Color();
