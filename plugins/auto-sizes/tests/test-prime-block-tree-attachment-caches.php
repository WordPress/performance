<?php
/**
 * Tests for priming the attachment caches of block trees outside the post content.
 *
 * @package auto-sizes
 * @group   prime-attachment-caches
 */

class Tests_Prime_Block_Tree_Attachment_Caches extends WP_UnitTestCase {

	/**
	 * Attachment IDs.
	 *
	 * @var int[]
	 */
	public static $image_ids = array();

	/**
	 * Cache state of each image and cover block, recorded right before it renders.
	 *
	 * @var array<int, array{post: bool, meta: bool}>
	 */
	private $cache_states = array();

	/**
	 * Set up the environment for the tests.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		for ( $i = 0; $i < 3; $i++ ) {
			self::$image_ids[] = self::factory()->attachment->create_upload_object( TESTS_PLUGIN_DIR . '/tests/data/images/leaves.jpg' );
		}
	}

	/**
	 * Records the cache state of each image and cover block right before it renders.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->cache_states = array();
		add_filter(
			'render_block_data',
			function ( array $parsed_block ): array {
				if ( in_array( $parsed_block['blockName'], array( 'core/image', 'core/cover' ), true ) && isset( $parsed_block['attrs']['id'] ) ) {
					$image_id                        = (int) $parsed_block['attrs']['id'];
					$this->cache_states[ $image_id ] = array(
						'post' => is_object( wp_cache_get( $image_id, 'posts' ) ), // Core caches the raw post object, not a WP_Post.
						'meta' => false !== wp_cache_get( $image_id, 'post_meta' ),
					);
				}
				return $parsed_block;
			},
			20 // After the priming filter at priority 10, which primes when a tree's root block is about to render.
		);
	}

	/**
	 * Test that the priming filter is registered.
	 *
	 * @covers ::auto_sizes_prime_block_tree_attachment_caches
	 */
	public function test_priming_filter_is_registered(): void {
		$this->assertSame( 10, has_filter( 'render_block_data', 'auto_sizes_prime_block_tree_attachment_caches' ) );
	}

	/**
	 * Test that the images in a group are primed before they render, as in a block template.
	 *
	 * @covers ::auto_sizes_prime_block_tree_attachment_caches
	 */
	public function test_images_in_a_group_are_primed_before_they_render(): void {
		$content = $this->get_group_block( $this->get_image_block( self::$image_ids[0] ) . $this->get_image_block( self::$image_ids[1] ) );

		$this->clean_attachment_caches();
		do_blocks( $content );

		$this->assertAllPrimed( array( self::$image_ids[0], self::$image_ids[1] ) );
	}

	/**
	 * Test that image and cover blocks in nested blocks are primed before they render.
	 *
	 * @covers ::auto_sizes_prime_block_tree_attachment_caches
	 */
	public function test_nested_image_and_cover_blocks_are_primed_before_they_render(): void {
		$columns = '<!-- wp:columns --><div class="wp-block-columns">'
			. '<!-- wp:column --><div class="wp-block-column">' . $this->get_image_block( self::$image_ids[0] ) . '</div><!-- /wp:column -->'
			. '<!-- wp:column --><div class="wp-block-column">' . $this->get_cover_block( self::$image_ids[1] ) . '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->';
		$content = $this->get_group_block( $this->get_group_block( $columns ) . $this->get_image_block( self::$image_ids[2] ) );

		$this->clean_attachment_caches();
		do_blocks( $content );

		$this->assertAllPrimed( self::$image_ids );
	}

	/**
	 * Test that the images in a synced pattern are primed before they render.
	 *
	 * Since WordPress 6.9 the Pattern block renders the pattern's blocks as its own inner blocks,
	 * so they reach `render_block_data` with the `core/block` block as their parent.
	 *
	 * @covers ::auto_sizes_prime_block_tree_attachment_caches
	 */
	public function test_images_in_a_synced_pattern_are_primed_before_they_render(): void {
		$pattern_id = self::factory()->post->create(
			array(
				'post_type'    => 'wp_block',
				'post_status'  => 'publish',
				'post_content' => $this->get_group_block( $this->get_image_block( self::$image_ids[0] ) . $this->get_image_block( self::$image_ids[1] ) ),
			)
		);
		$this->assertIsInt( $pattern_id );

		$this->clean_attachment_caches();
		do_blocks( '<!-- wp:block {"ref":' . $pattern_id . '} /-->' );

		$this->assertAllPrimed( array( self::$image_ids[0], self::$image_ids[1] ) );
	}

	/**
	 * Test that a tree with a single image is not primed, as priming one attachment saves nothing.
	 *
	 * @covers ::auto_sizes_prime_block_tree_attachment_caches
	 */
	public function test_a_tree_with_one_image_is_not_primed(): void {
		$content = $this->get_group_block( $this->get_image_block( self::$image_ids[0] ) );

		$this->clean_attachment_caches();
		do_blocks( $content );

		$this->assertSame(
			array(
				'post' => false,
				'meta' => false,
			),
			$this->cache_states[ self::$image_ids[0] ]
		);
	}

	/**
	 * Test that a nested block does not prime, as its tree's root block already did.
	 *
	 * @covers ::auto_sizes_prime_block_tree_attachment_caches
	 */
	public function test_nested_blocks_do_not_prime(): void {
		$group        = parse_blocks( $this->get_group_block( $this->get_image_block( self::$image_ids[0] ) . $this->get_image_block( self::$image_ids[1] ) ) )[0];
		$parent_block = new WP_Block( parse_blocks( $this->get_group_block( '' ) )[0] );

		$this->clean_attachment_caches();
		$result = auto_sizes_prime_block_tree_attachment_caches( $group, $group, $parent_block );

		$this->assertSame( $group, $result, 'The block should be returned unchanged.' );
		foreach ( array( self::$image_ids[0], self::$image_ids[1] ) as $image_id ) {
			$this->assertFalse( wp_cache_get( $image_id, 'posts' ), "Attachment {$image_id} should not be primed by a nested block." );
		}
	}

	/**
	 * Test that the root block is returned unchanged.
	 *
	 * @covers ::auto_sizes_prime_block_tree_attachment_caches
	 */
	public function test_root_block_is_returned_unchanged(): void {
		$group = parse_blocks( $this->get_group_block( $this->get_image_block( self::$image_ids[0] ) . $this->get_image_block( self::$image_ids[1] ) ) )[0];

		$this->assertSame( $group, auto_sizes_prime_block_tree_attachment_caches( $group, $group, null ) );
	}

	/**
	 * Asserts that the post and meta caches of each attachment were warm when its block rendered.
	 *
	 * @param int[] $image_ids Attachment IDs.
	 */
	private function assertAllPrimed( array $image_ids ): void {
		foreach ( $image_ids as $image_id ) {
			$this->assertArrayHasKey( $image_id, $this->cache_states, "The block for attachment {$image_id} should have rendered." );
			$this->assertTrue( $this->cache_states[ $image_id ]['post'], "The post cache for attachment {$image_id} should be primed before its block renders." );
			$this->assertTrue( $this->cache_states[ $image_id ]['meta'], "The meta cache for attachment {$image_id} should be primed before its block renders." );
		}
	}

	/**
	 * Empties the attachment caches, which building the block markup has filled.
	 */
	private function clean_attachment_caches(): void {
		foreach ( self::$image_ids as $image_id ) {
			clean_post_cache( $image_id );
		}
	}

	/**
	 * Gets the markup of an image block.
	 *
	 * @param int $image_id Attachment ID.
	 * @return string Block markup.
	 */
	private function get_image_block( int $image_id ): string {
		return '<!-- wp:image {"id":' . $image_id . ',"sizeSlug":"large","linkDestination":"none"} --><figure class="wp-block-image size-large"><img src="' . wp_get_attachment_image_url( $image_id, 'large' ) . '" alt="" class="wp-image-' . $image_id . '"/></figure><!-- /wp:image -->';
	}

	/**
	 * Gets the markup of a cover block with an image background.
	 *
	 * @param int $image_id Attachment ID.
	 * @return string Block markup.
	 */
	private function get_cover_block( int $image_id ): string {
		$url = wp_get_attachment_image_url( $image_id, 'full' );
		return '<!-- wp:cover {"url":"' . $url . '","id":' . $image_id . ',"dimRatio":50} --><div class="wp-block-cover"><span aria-hidden="true" class="wp-block-cover__background has-background-dim"></span><img class="wp-block-cover__image-background wp-image-' . $image_id . '" alt="" src="' . $url . '" data-object-fit="cover"/><div class="wp-block-cover__inner-container"></div></div><!-- /wp:cover -->';
	}

	/**
	 * Gets the markup of a group block around the given inner blocks.
	 *
	 * @param string $inner_blocks Inner block markup.
	 * @return string Block markup.
	 */
	private function get_group_block( string $inner_blocks ): string {
		return '<!-- wp:group --><div class="wp-block-group">' . $inner_blocks . '</div><!-- /wp:group -->';
	}
}
