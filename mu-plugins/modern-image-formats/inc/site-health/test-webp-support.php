function register_webp_test( $tests ) {
    $tests['direct']['webp_support'] = array(
        'label' => 'WebP Image Support',
        'test'  => __NAMESPACE__ . '\\test_webp_support',
    );
    return $tests;
}
add_filter( 'site_status_tests', __NAMESPACE__ . '\\register_webp_test' );
