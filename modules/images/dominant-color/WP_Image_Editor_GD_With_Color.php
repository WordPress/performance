<?php

class WP_Image_Editor_GD_With_Color extends WP_Image_Editor_GD {

	/**
	 * @param $default_color
	 *
	 * @return string
	 */
	public function get_dominant_color( $default_color = 'eee' ) {

		if ( $this->image ) {
			$shortend_image = imagecreatetruecolor( 1, 1 );
			imagecopyresampled( $shortend_image, $this->image, 0, 0, 0, 0, 1, 1, imagesx( $this->image ), imagesy( $this->image ) );

			return dechex( imagecolorat( $shortend_image, 0, 0 ) );
		} else {
			return $default_color;
		}
	}


	/**
	 * @param $file
	 *
	 * @return bool
	 */
	public function get_has_transparency() {

		if ( $this->image  ) {
//			var_dump( imageistruecolor( $this->image ) );
//			var_dump( imagecolortransparent( $this->image ) );
			if( imageistruecolor( $this->image ) ) {
				return imagecolortransparent( $this->image ) > 0 ? true : false;
			} else {
				// walk through the pixels
				$w = imagesx( $this->image );
				$h = imagesy( $this->image );
				for ( $x = 0; $x < $w; $x++ ) {
					for ( $y = 0; $y < $h; $y++ ) {
						$rgb = imagecolorat( $this->image, $x, $y );
						$rgba = imagecolorsforindex( $this->image, $rgb );
						if ( $rgba['alpha'] > 0 ) {
							return true;
						}
					}
				}
				return false;
			}

//
//			return imageistruecolor( $this->image ) ? imagecolortransparent( $this->image ) > 0 : imagecolorsforindex( $this->image, imagecolortransparent( $this->image ) )['alpha'] > 0;
		} else {
			return false;
		}

	}
}
