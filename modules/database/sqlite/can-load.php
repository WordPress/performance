<?php
/**
 * Can load function to determine if SQLite can be activated.
 *
 * @since n.e.x.t
 * @package performance-lab
 */

/**
 * Checks whether the given module can be activated.
 *
 * @since n.e.x.t
 */
return function() {
	return class_exists( 'SQLite3' );
};
