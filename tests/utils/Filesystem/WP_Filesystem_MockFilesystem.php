<?php
/**
 * This file needs to be in the global namespace due to how WordPress requires loading it.
 *
 * @package performance-lab
 */

/**
 * Simple mock filesystem, limited to working with concrete file paths.
 * No support for hierarchy or parent directories etc.
 *
 * Could be expanded in the future if needed.
 */
class WP_Filesystem_MockFilesystem extends WP_Filesystem_Base {

	/**
	 * @var array<string, string>
	 */
	private $file_contents = array();

	/**
	 * @return string|false
	 */
	public function get_contents( $file ) {
		if ( isset( $this->file_contents[ $file ] ) ) {
			return $this->file_contents[ $file ];
		}
		return false;
	}

	/**
	 * @return string[]|false
	 */
	public function get_contents_array( $file ) {
		if ( isset( $this->file_contents[ $file ] ) ) {
			return array( $this->file_contents[ $file ] );
		}
		return false;
	}

	public function put_contents( $file, $contents, $mode = false ): bool {
		$this->file_contents[ $file ] = $contents;
		return true;
	}

	public function cwd(): bool {
		return false;
	}

	public function chdir( $dir ): bool {
		return false;
	}

	public function chgrp( $file, $group, $recursive = false ): bool {
		return false;
	}

	public function chmod( $file, $mode = false, $recursive = false ): bool {
		return false;
	}

	public function owner( $file ): bool {
		return false;
	}

	public function group( $file ): bool {
		return false;
	}

	public function copy( $source, $destination, $overwrite = false, $mode = false ): bool {
		if ( ! isset( $this->file_contents[ $source ] ) ) {
			return false;
		}
		if ( ! $overwrite && isset( $this->file_contents[ $destination ] ) ) {
			return false;
		}
		$this->file_contents[ $destination ] = $this->file_contents[ $source ];
		return true;
	}

	public function move( $source, $destination, $overwrite = false ): bool {
		if ( $this->copy( $source, $destination, $overwrite, false ) ) {
			return $this->delete( $source );
		}
		return false;
	}

	public function delete( $file, $recursive = false, $type = false ): bool {
		if ( isset( $this->file_contents[ $file ] ) ) {
			unset( $this->file_contents[ $file ] );
		}
		return true;
	}

	public function exists( $path ): bool {
		return isset( $this->file_contents[ $path ] );
	}

	public function is_file( $file ): bool {
		return isset( $this->file_contents[ $file ] );
	}

	public function is_dir( $path ): bool {
		return false;
	}

	public function is_readable( $file ): bool {
		return isset( $this->file_contents[ $file ] );
	}

	public function is_writable( $path ): bool {
		return true;
	}

	public function atime( $file ): bool {
		return false;
	}

	public function mtime( $file ): bool {
		return false;
	}

	public function size( $file ): bool {
		if ( isset( $this->file_contents[ $file ] ) ) {
			return strlen( $this->file_contents[ $file ] );
		}
		return false;
	}

	public function touch( $file, $time = 0, $atime = 0 ): bool {
		if ( ! isset( $this->file_contents[ $file ] ) ) {
			$this->file_contents[ $file ] = '';
		}
		return true;
	}

	public function mkdir( $path, $chmod = false, $chown = false, $chgrp = false ): bool {
		return false;
	}

	public function rmdir( $path, $recursive = false ): bool {
		return false;
	}

	public function dirlist( $path, $include_hidden = true, $recursive = false ): bool {
		return false;
	}
}
