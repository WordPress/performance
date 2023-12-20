<?php
/**
 * Can load function to determine if Site Health module is supported or not.
 *
 * @package performance-lab
 */

return static function () {
    return new WP_Error( 'cannot_load_module', esc_html__( 'The module cannot be loaded.', 'performance-lab' ) );
};
