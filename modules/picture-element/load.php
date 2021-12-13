<?php
/**
 * Module Name: Picture Element
 * Description: Replaces default image tags with <picture> elements supporting a primary and fallback image.
 * Focus: images
 * Experimental: No
 *
 * @package performance-lab
 * @since n.e.x.t
 */

 /**
  * Wrap all content images in a picture element.
  *
  * @since n.e.x.t
  *
  * @param string $content The content to be filtered.
  */
  function wrap_images( $content ) {
	$pattern = '/(<img[^>]+>)/';
	$images = preg_match_all( $pattern, $content, $matches );
	if ( $images ) {
		foreach ( $matches[0] as $image ) {
			// Wrap WordPres images where we can extract an attachment id.
			if ( preg_match( '/wp-image-([0-9]+)/i', $image, $class_id ) ) {
				$attachment_id = absint( $class_id[1] );
				$new_image = wrap_image_in_picture( $image, $attachment_id );
				if ( false !== $new_image ) {
					error_log( sprintf( "replace %s with %s", $image, $new_image ) );
					$content = str_replace( $image, $new_image, $content );
				}
 			}
		}
	}

	return $content;
  }
  add_filter( 'the_content', 'wrap_images' );

  /**
	 * Wrap an image tag in a picture element.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $image         The image tag.
	 * @param int    $attachment_id The attachment id.
	 *
	 * @return string The new image tag.
	 */
	function wrap_image_in_picture( $image, $attachment_id ) {
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		$image_src = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( false === $image_src ) {
			return false;
		}
		$image_src = $image_src[0];
		$sources = '';
		$file_ext  = strtolower( pathinfo( $image_src, PATHINFO_EXTENSION ) );
		$file_mime = get_mime_type( $file_ext );



		/**
		 * Filter the image mime types used for the <picture> element.
		 *
		 * The mime types will be used in the order they are provided.
		 * The original image will be used as the fallback.
		 */
		$mime_types = apply_filters( 'wp_picture_element_mime_types', array(
			'image/webp',
		) );

		// Don't apply if only mime type if it matches the original image.
		if ( 1 == sizeof ($mime_types) &&  $mime_types[0] === $file_mime ) {
			return false;
		}

		foreach ( $mime_types as $i => $image_mime_type ) {

			// Don't add this mime type if it matches the original image.
			if ( $image_mime_type === $file_mime ) {
				continue;
			}

			// Remap each size's mime-type and file extension.
			$mapped_meta = $image_meta;
			foreach( $mapped_meta['sizes'] as $size => $size_meta ) {
				$mapped_meta['sizes'][$size]['mime-type'] = $image_mime_type;
				error_log(str_replace(
					$file_ext,
					get_extension( $image_mime_type ),
					$size_meta['file']
				));
				$mapped_meta['sizes'][$size]['file'] = str_replace(
					$file_ext,
					get_extension( $image_mime_type ),
					$size_meta['file']
				);
			}
			error_log( json_encode( $mapped_meta, JSON_PRETTY_PRINT));

			$image_srcset = wp_get_attachment_image_srcset( $attachment_id, $mapped_meta );

			$sources .= sprintf( '<source type="%s" srcset="%s">',
				$image_mime_type,
				$image_srcset
			);
		}

		$picture = sprintf( '<picture>%s %s</picture>',
			$sources,
			$image
		);

		return $picture;
	}

	/**
	 * When saving an image, output additional image formats.
	 *
	 * @since n.e.x.t
	 */
	function save_additional_image_formats( $attachment_id ) {
		// Temporarily filter the image output format.
		add_filter( 'image_editor_output_format', array( $this->plugin, 'filter_image_editor_output_format' ) );
		wp_update_image_subsizes( $attachment_id );
		remove_filter('image_editor_output_format', array( $this->plugin, 'filter_image_editor_output_format' ) );
	}


	/**
	 * Note: this function pulled from core src/wp-includes/class-wp-image-editor.php.
	 *
	 * This can be used directly in the core patch, polyfilled here because the
	 * core implementation is not accessible.
	 *
	 * @since n.e.x.t
	 *
	 * @param string $extension
	 * @return string|false
	 */
	function get_mime_type( $extension = null ) {
		if ( ! $extension ) {
			return false;
		}

		$mime_types = wp_get_mime_types();
		$extensions = array_keys( $mime_types );

		foreach ( $extensions as $_extension ) {
			if ( preg_match( "/{$extension}/i", $_extension ) ) {
				return $mime_types[ $_extension ];
			}
		}

		return false;
	}

	/**
	 *
	 * Note: this function pulled from core src/wp-includes/class-wp-image-editor.php.
	 *
	 * Returns first matched extension from Mime-type,
	 * as mapped from wp_get_mime_types()
	 *
	 * @since 1.1.0
	 *
	 * @param string $mime_type
	 * @return string|false
	 */
	function get_extension( $mime_type = null ) {
		if ( empty( $mime_type ) ) {
			return false;
		}

		return wp_get_default_extension_for_mime_type( $mime_type );
	}