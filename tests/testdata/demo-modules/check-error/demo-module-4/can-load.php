<?php
/**
 * Can load function to determine if Site Health module is supported or not.
 *
 * @package performance-lab
 */

return static function () {
    return new WP_Error( 'module_not_loaded', esc_html__( 'The module cannot be loaded with Performance Lab since it is disabled.', 'performance-lab' ) );
};
