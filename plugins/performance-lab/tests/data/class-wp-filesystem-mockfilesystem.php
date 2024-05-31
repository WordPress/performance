<?php
/**
 * This file needs to be in the global namespace due to how WordPress requires loading it.
 *
 * @package performance-lab
 */

// phpcs:disable SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingAnyTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingAnyTypeHint
// phpcs:disable SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint

require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';

/**
 * Simple mock filesystem, limited to working with concrete file paths.
 * No support for hierarchy or parent directories etc.
 *
 * Could be expanded in the future if needed.
 */
class WP_Filesystem_MockFilesystem extends WP_Filesystem_Base {

	/** @var array<string, string> */
	private $file_contents = array();

	public function get_contents( $file ) {
		if ( isset( $this->file_contents[ $file ] ) ) {
			return $this->file_contents[ $file ];
		}
		return false;
	}

	/** @return string[]|false */
	public function get_contents_array( $file ) {
		if ( isset( $this->file_contents[ $file ] ) ) {
			return array( $this->file_contents[ $file ] );
		}
		return false;
	}

	public function put_contents( $file, $contents, $mode = false ) {
		$this->file_contents[ $file ] = $contents;
		return true;
	}

	public function cwd() {
		return false;
	}

	public function chdir( $dir ) {
		return false;
	}

	public function chgrp( $file, $group, $recursive = false ) {
		return false;
	}

	public function chmod( $file, $mode = false, $recursive = false ) {
		return false;
	}

	public function owner( $file ) {
		return false;
	}

	public function group( $file ) {
		return false;
	}

	public function copy( $source, $destination, $overwrite = false, $mode = false ) {
		if ( ! isset( $this->file_contents[ $source ] ) ) {
			return false;
		}
		if ( ! $overwrite && isset( $this->file_contents[ $destination ] ) ) {
			return false;
		}
		$this->file_contents[ $destination ] = $this->file_contents[ $source ];
		return true;
	}

	public function move( $source, $destination, $overwrite = false ) {
		if ( $this->copy( $source, $destination, $overwrite, false ) ) {
			return $this->delete( $source );
		}
		return false;
	}

	public function delete( $file, $recursive = false, $type = false ) {
		if ( isset( $this->file_contents[ $file ] ) ) {
			unset( $this->file_contents[ $file ] );
		}
		return true;
	}

	public function exists( $path ) {
		return isset( $this->file_contents[ $path ] );
	}

	public function is_file( $file ) {
		return isset( $this->file_contents[ $file ] );
	}

	public function is_dir( $path ) {
		return false;
	}

	public function is_readable( $file ) {
		return isset( $this->file_contents[ $file ] );
	}

	public function is_writable( $path ) {
		return true;
	}

	public function atime( $file ) {
		return false;
	}

	public function mtime( $file ) {
		return false;
	}

	public function size( $file ) {
		if ( isset( $this->file_contents[ $file ] ) ) {
			return strlen( $this->file_contents[ $file ] );
		}
		return false;
	}

	public function touch( $file, $time = 0, $atime = 0 ) {
		if ( ! isset( $this->file_contents[ $file ] ) ) {
			$this->file_contents[ $file ] = '';
		}
		return true;
	}

	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ) {
		return false;
	}

	public function rmdir( $path, $recursive = false ) {
		return false;
	}

	/** @return false */
	public function dirlist( $path, $include_hidden = true, $recursive = false ) {
		return false;
	}
}
