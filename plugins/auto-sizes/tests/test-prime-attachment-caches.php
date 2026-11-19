<?php
/**
 * Tests for priming the attachment caches before blocks render.
 *
 * @package auto-sizes
 * @group   prime-attachment-caches
 */

class Tests_Prime_Attachment_Caches extends WP_UnitTestCase {

	/**
	 * Attachment IDs.
	 *
	 * @var int[]
	 */
	public static $image_ids = array();

	/**
	 * Set up the environment for the tests.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		// Priming only happens for more than one image, so two attachments are needed.
		self::$image_ids = array(
			self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' ),
			self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' ),
		);
	}

	/**
	 * Test that the priming filter is registered to run before blocks are rendered.
	 *
	 * @covers ::auto_sizes_prime_attachment_caches
	 */
	public function test_priming_runs_before_do_blocks(): void {
		$priming_priority   = has_filter( 'the_content', 'auto_sizes_prime_attachment_caches' );
		$do_blocks_priority = has_filter( 'the_content', 'do_blocks' );

		$this->assertIsInt( $priming_priority, 'The priming filter should be registered on the_content.' );
		$this->assertIsInt( $do_blocks_priority, 'Core should register do_blocks on the_content.' );
		$this->assertLessThan( $do_blocks_priority, $priming_priority, 'Priming must run before do_blocks so that image blocks render with warm caches.' );
	}

	/**
	 * Test that the attachment caches are warm by the time each image block renders.
	 *
	 * @covers ::auto_sizes_prime_attachment_caches
	 */
	public function test_attachment_caches_are_warm_when_image_blocks_render(): void {
		$block_content = '';
		foreach ( self::$image_ids as $image_id ) {
			$block_content .= '<!-- wp:image {"id":' . $image_id . ',"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="' . wp_get_attachment_image_url( $image_id, 'large' ) . '" alt="" class="wp-image-' . $image_id . '"/></figure><!-- /wp:image -->';
		}

		// Start cold: building the markup above has loaded the attachments.
		foreach ( self::$image_ids as $image_id ) {
			clean_post_cache( $image_id );
			$this->assertFalse( wp_cache_get( $image_id, 'posts' ) );
			$this->assertFalse( wp_cache_get( $image_id, 'post_meta' ) );
		}

		// Record the cache state for each image block right before it renders.
		$cache_states = array();
		add_filter(
			'render_block_data',
			static function ( array $parsed_block ) use ( &$cache_states ): array {
				if ( 'core/image' === $parsed_block['blockName'] && isset( $parsed_block['attrs']['id'] ) ) {
					$image_id                  = (int) $parsed_block['attrs']['id'];
					$cache_states[ $image_id ] = array(
						'post' => is_object( wp_cache_get( $image_id, 'posts' ) ), // Core caches the raw post object, not a WP_Post.
						'meta' => false !== wp_cache_get( $image_id, 'post_meta' ),
					);
				}
				return $parsed_block;
			}
		);

		apply_filters( 'the_content', $block_content );

		$this->assertSame( self::$image_ids, array_keys( $cache_states ), 'Both image blocks should have rendered.' );
		foreach ( self::$image_ids as $image_id ) {
			$this->assertTrue( $cache_states[ $image_id ]['post'], "The post cache for attachment {$image_id} should be primed before its block renders." );
			$this->assertTrue( $cache_states[ $image_id ]['meta'], "The meta cache for attachment {$image_id} should be primed before its block renders." );
		}
	}
}
