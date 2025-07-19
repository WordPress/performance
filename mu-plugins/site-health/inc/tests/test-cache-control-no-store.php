<?php
/**
 * Site Health Test: Warn if Cache-Control: no-store is used for unauthenticated users.
 */

add_filter( 'site_status_tests', 'performance_lab_register_cache_control_test' );

function performance_lab_register_cache_control_test( $tests ) {
    $tests['direct']['cache_control_no_store'] = array(
        'label' => __( 'Check Cache-Control header for no-store', 'performance-lab' ),
        'test'  => 'performance_lab_test_cache_control_no_store',
    );
    return $tests;
}

function performance_lab_test_cache_control_no_store() {
    $url = home_url( '/' );

    $response = wp_remote_get( $url );

    if ( is_wp_error( $response ) ) {
        return array(
            'status'  => 'recommended',
            'label'   => __( 'Could not check Cache-Control header.', 'performance-lab' ),
            'description' => __( 'Failed to perform frontend request.', 'performance-lab' ),
        );
    }

    $headers = wp_remote_retrieve_headers( $response );
    $cache_control = isset( $headers['cache-control'] ) ? strtolower( $headers['cache-control'] ) : '';

    if ( strpos( $cache_control, 'no-store' ) !== false ) {
        return array(
            'status'  => 'recommended',
            'label'   => __( 'The Cache-Control header is set to no-store.', 'performance-lab' ),
            'description' => __( 'Using no-store may reduce frontend performance for unauthenticated users.', 'performance-lab' ),
        );
    }

    return array(
        'status' => 'good',
        'label'  => __( 'The Cache-Control header is appropriate.', 'performance-lab' ),
    );
}
