function register_avif_test( $tests ) {
    $tests['direct']['avif_support'] = array(
        'label' => 'AVIF Image Support',
        'test'  => __NAMESPACE__ . '\\test_avif_support',
    );
    return $tests;
}
add_filter( 'site_status_tests', __NAMESPACE__ . '\\register_avif_test' );
