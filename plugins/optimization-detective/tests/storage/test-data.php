<?php
/**
 * Tests for optimization-detective plugin storage/data.php.
 *
 * @package optimization-detective
 *
 * @noinspection PhpUnhandledExceptionInspection
 */

class Test_OD_Storage_Data extends WP_UnitTestCase {

	/**
	 * @var string
	 */
	private $original_request_uri;

	public function set_up(): void {
		$this->original_request_uri = $_SERVER['REQUEST_URI'];
		parent::set_up();
	}

	public function tear_down(): void {
		$_SERVER['REQUEST_URI'] = $this->original_request_uri;
		unset( $GLOBALS['wp_customize'] );
		unset( $GLOBALS['template'] );
		unset( $GLOBALS['_wp_current_template_id'] );
		parent::tear_down();
	}

	/**
	 * Test od_get_url_metric_freshness_ttl().
	 *
	 * @covers ::od_get_url_metric_freshness_ttl
	 */
	public function test_od_get_url_metric_freshness_ttl(): void {
		$this->assertSame( DAY_IN_SECONDS, od_get_url_metric_freshness_ttl() );

		add_filter(
			'od_url_metric_freshness_ttl',
			static function (): int {
				return HOUR_IN_SECONDS;
			}
		);

		$this->assertSame( HOUR_IN_SECONDS, od_get_url_metric_freshness_ttl() );
	}

	/**
	 * Test bad od_get_url_metric_freshness_ttl().
	 *
	 * @expectedIncorrectUsage od_get_url_metric_freshness_ttl
	 * @covers ::od_get_url_metric_freshness_ttl
	 */
	public function test_bad_od_get_url_metric_freshness_ttl(): void {
		add_filter(
			'od_url_metric_freshness_ttl',
			static function (): int {
				return -1;
			}
		);

		$this->assertSame( 0, od_get_url_metric_freshness_ttl() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_od_get_normalized_query_vars(): array {
		return array(
			'homepage'     => array(
				'set_up' => function (): array {
					$this->go_to( home_url( '/' ) );
					return array();
				},
			),
			'post'         => array(
				'set_up' => function (): array {
					$post_id = self::factory()->post->create();
					$this->go_to( get_permalink( $post_id ) );
					return array( 'p' => (string) $post_id );
				},
			),
			'date-archive' => array(
				'set_up' => function (): array {
					$post_id = self::factory()->post->create();
					$date = get_post_datetime( $post_id );

					$this->go_to(
						add_query_arg(
							array(
								'day'      => $date->format( 'j' ),
								'year'     => $date->format( 'Y' ),
								'monthnum' => $date->format( 'm' ),
								'bogus'    => 'ignore me',
							),
							home_url()
						)
					);
					return array(
						'year'     => $date->format( 'Y' ),
						'monthnum' => $date->format( 'm' ),
						'day'      => $date->format( 'j' ),
					);
				},
			),
			'not-found'    => array(
				'set_up' => function (): array {
					$this->go_to( home_url( '/?p=1000000' ) );
					return array( 'error' => 404 );
				},
			),
			'logged-in'    => array(
				'set_up' => function (): array {
					wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
					$this->go_to( home_url( '/' ) );
					return array();
				},
			),
		);
	}

	/**
	 * Test od_get_normalized_query_vars().
	 *
	 * @covers ::od_get_normalized_query_vars
	 *
	 * @dataProvider data_provider_test_od_get_normalized_query_vars
	 */
	public function test_od_get_normalized_query_vars( Closure $set_up ): void {
		$expected = $set_up();
		$this->assertSame( $expected, od_get_normalized_query_vars() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_test_get_current_url(): array {
		$assertions = array(
			'path'                        => function (): void {
				$_SERVER['REQUEST_URI'] = wp_slash( '/foo/' );
				$this->assertEquals(
					home_url( '/foo/' ),
					od_get_current_url()
				);
			},

			'query'                       => function (): void {
				$_SERVER['REQUEST_URI'] = wp_slash( '/bar/?baz=1' );
				$this->assertEquals(
					home_url( '/bar/?baz=1' ),
					od_get_current_url()
				);
			},

			'idn_domain'                  => function (): void {
				$this->set_home_url_with_filter( 'https://⚡️.example.com' );
				$this->go_to( '/?s=lightning' );
				$this->assertEquals( 'https://⚡️.example.com/?s=lightning', od_get_current_url() );
			},

			'punycode_domain'             => function (): void {
				$this->set_home_url_with_filter( 'https://xn--57h.example.com' );
				$this->go_to( '/?s=thunder' );
				$this->assertEquals( 'https://xn--57h.example.com/?s=thunder', od_get_current_url() );
			},

			'ip_host'                     => function (): void {
				$this->set_home_url_with_filter( 'http://127.0.0.1:1234' );
				$this->go_to( '/' );
				$this->assertEquals( 'http://127.0.0.1:1234/', od_get_current_url() );
			},

			'permalink'                   => function (): void {
				global $wp_rewrite;
				update_option( 'permalink_structure', '/%year%/%monthnum%/%day%/%postname%/' );
				$wp_rewrite->use_trailing_slashes = true;
				$wp_rewrite->init();
				$wp_rewrite->flush_rules();

				$permalink = get_permalink( self::factory()->post->create() );

				$this->go_to( $permalink );
				$this->assertEquals( $permalink, od_get_current_url() );
			},

			'unset_request_uri'           => function (): void {
				unset( $_SERVER['REQUEST_URI'] );
				$this->assertEquals( home_url( '/' ), od_get_current_url() );
			},

			'empty_request_uri'           => function (): void {
				$_SERVER['REQUEST_URI'] = '';
				$this->assertEquals( home_url( '/' ), od_get_current_url() );
			},

			'no_slash_prefix_request_uri' => function (): void {
				$_SERVER['REQUEST_URI'] = 'foo/';
				$this->assertEquals( home_url( '/foo/' ), od_get_current_url() );
			},

			'reconstructed_home_url'      => function (): void {
				$_SERVER['HTTPS']       = 'on';
				$_SERVER['REQUEST_URI'] = '/about/';
				$_SERVER['HTTP_HOST']   = 'foo.example.org';
				$this->set_home_url_with_filter( '/' );
				$this->assertEquals(
					'https://foo.example.org/about/',
					od_get_current_url()
				);
			},

			'home_url_with_trimmings'     => function (): void {
				$this->set_home_url_with_filter( 'https://example.museum:8080' );
				$_SERVER['REQUEST_URI'] = '/about/';
				$this->assertEquals(
					'https://example.museum:8080/about/',
					od_get_current_url()
				);
			},

			'complete_parse_fail'         => function (): void {
				$_SERVER['HTTP_HOST'] = 'env.example.org';
				unset( $_SERVER['REQUEST_URI'] );
				$this->set_home_url_with_filter( ':' );
				$this->assertEquals(
					( is_ssl() ? 'https:' : 'http:' ) . '//env.example.org/',
					od_get_current_url()
				);
			},

			'default_to_localhost'        => function (): void {
				unset( $_SERVER['HTTP_HOST'], $_SERVER['REQUEST_URI'] );
				$this->set_home_url_with_filter( ':' );
				$this->assertEquals(
					( is_ssl() ? 'https:' : 'http:' ) . '//localhost/',
					od_get_current_url()
				);
			},
		);
		return array_map(
			static function ( Closure $assertion ): array {
				return array( $assertion );
			},
			$assertions
		);
	}

	/**
	 * Set home_url with filter.
	 *
	 * @param string $home_url Home URL.
	 */
	private function set_home_url_with_filter( string $home_url ): void {
		add_filter(
			'home_url',
			static function () use ( $home_url ): string {
				return $home_url;
			}
		);
	}

	/**
	 * Test od_get_current_url().
	 *
	 * @covers ::od_get_current_url
	 *
	 * @dataProvider data_provider_test_get_current_url
	 */
	public function test_od_get_current_url( Closure $assert ): void {
		call_user_func( $assert );
	}

	/**
	 * Test od_get_url_metrics_slug().
	 *
	 * @covers ::od_get_url_metrics_slug
	 */
	public function test_od_get_url_metrics_slug(): void {
		$first  = od_get_url_metrics_slug( array() );
		$second = od_get_url_metrics_slug( array( 'p' => 1 ) );
		$this->assertNotEquals( $second, $first );
		foreach ( array( $first, $second ) as $slug ) {
			$this->assertMatchesRegularExpression( '/^[0-9a-f]{32}\z/', $slug );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed>
	 */
	public function data_provider_test_od_get_current_url_metrics_etag(): array {
		return array(
			'homepage_one_post'           => array(
				'set_up' => function (): Closure {
					$post = self::factory()->post->create_and_get();
					$this->assertInstanceOf( WP_Post::class, $post );
					$this->go_to( '/' );
					$GLOBALS['template'] = trailingslashit( get_template_directory() ) . 'home.php';

					return function ( array $etag_data, Closure $get_etag ) use ( $post ): void {
						$this->assertTrue( is_home() );
						$this->assertTrue( is_front_page() );
						$this->assertNull( $etag_data['queried_object']['id'] );
						$this->assertNull( $etag_data['queried_object']['type'] );
						$this->assertCount( 1, $etag_data['queried_posts'] );
						$this->assertSame( $post->ID, $etag_data['queried_posts'][0]['id'] );
						$this->assertSame( $post->post_modified_gmt, $etag_data['queried_posts'][0]['post_modified_gmt'] );
						$this->assertSame( 'home.php', $etag_data['current_template'] );

						// Modify data using filters.
						$etag = $get_etag();
						add_filter(
							'od_current_url_metrics_etag_data',
							static function ( $data ) {
								$data['custom'] = true;
								return $data;
							}
						);
						$etag_after_filtering = $get_etag();
						$this->assertNotEquals( $etag, $etag_after_filtering );
					};
				},
			),

			'singular_post_then_modified' => array(
				'set_up' => function (): Closure {
					$force_old_post_modified_data = static function ( $data ) {
						$data['post_modified']     = '1970-01-01 00:00:00';
						$data['post_modified_gmt'] = '1970-01-01 00:00:00';
						return $data;
					};
					add_filter( 'wp_insert_post_data', $force_old_post_modified_data );
					$post = self::factory()->post->create_and_get();
					$this->assertInstanceOf( WP_Post::class, $post );
					remove_filter( 'wp_insert_post_data', $force_old_post_modified_data );
					$this->go_to( get_permalink( $post ) );
					$GLOBALS['template'] = trailingslashit( get_template_directory() ) . 'single.php';

					return function ( array $etag_data, Closure $get_etag ) use ( $post ): void {
						$this->assertTrue( is_single( $post ) );
						$this->assertSame( $post->ID, $etag_data['queried_object']['id'] );
						$this->assertSame( 'post', $etag_data['queried_object']['type'] );
						$this->assertArrayHasKey( 'post_modified_gmt', $etag_data['queried_object'] );
						$this->assertSame( $post->post_modified_gmt, $etag_data['queried_object']['post_modified_gmt'] );
						$this->assertCount( 1, $etag_data['queried_posts'] );
						$this->assertSame( $post->ID, $etag_data['queried_posts'][0]['id'] );
						$this->assertSame( $post->post_modified_gmt, $etag_data['queried_posts'][0]['post_modified_gmt'] );
						$this->assertSame( 'single.php', $etag_data['current_template'] );

						// Now try updating the post and re-navigating to it to verify that the modified date changes the ETag.
						$previous_etag = $get_etag();
						$r = wp_update_post(
							array(
								'ID'         => $post->ID,
								'post_title' => 'Modified Title!',
							),
							true
						);
						$this->assertIsInt( $r );
						$this->go_to( get_permalink( $post ) );
						$next_etag = $get_etag();
						$this->assertNotSame( $previous_etag, $next_etag );
					};
				},
			),

			'category_archive'            => array(
				'set_up' => function (): Closure {
					$term = self::factory()->category->create_and_get();
					$this->assertInstanceOf( WP_Term::class, $term );
					$post_ids = self::factory()->post->create_many( 2 );
					foreach ( $post_ids as $post_id ) {
						wp_set_post_terms( $post_id, array( $term->term_id ), 'category' );
					}
					$this->go_to( get_category_link( $term ) );
					$GLOBALS['template'] = trailingslashit( get_template_directory() ) . 'category.php';

					return function ( array $etag_data ) use ( $term, $post_ids ): void {
						$this->assertTrue( is_category( $term ) );
						$this->assertSame( $term->term_id, $etag_data['queried_object']['id'] );
						$this->assertSame( 'term', $etag_data['queried_object']['type'] );
						$this->assertCount( 2, $etag_data['queried_posts'] );
						$this->assertEqualSets( $post_ids, wp_list_pluck( $etag_data['queried_posts'], 'id' ) );
						$this->assertSame( 'category.php', $etag_data['current_template'] );
					};
				},
			),

			'user_archive'                => array(
				'set_up' => function (): Closure {
					$user_id = self::factory()->user->create();
					$this->assertIsInt( $user_id );
					$post_ids = self::factory()->post->create_many( 3, array( 'post_author' => $user_id ) );

					// This is a workaround because the author URL pretty permalink is failing for some reason only on GHA.
					add_filter(
						'author_link',
						static function ( $link, $author_id ) {
							return add_query_arg( 'author', $author_id, home_url( '/' ) );
						},
						10,
						2
					);
					$this->go_to( get_author_posts_url( $user_id ) );
					$GLOBALS['template'] = trailingslashit( get_template_directory() ) . 'author.php';

					return function ( array $etag_data ) use ( $user_id, $post_ids ): void {
						$this->assertTrue( is_author( $user_id ), 'Expected is_author() after having gone to ' . get_author_posts_url( $user_id ) );
						$this->assertSame( $user_id, $etag_data['queried_object']['id'] );
						$this->assertSame( 'user', $etag_data['queried_object']['type'] );
						$this->assertCount( 3, $etag_data['queried_posts'] );
						$this->assertEqualSets( $post_ids, wp_list_pluck( $etag_data['queried_posts'], 'id' ) );
						$this->assertSame( 'author.php', $etag_data['current_template'] );
					};
				},
			),

			'post_type_archive'           => array(
				'set_up' => function (): Closure {
					register_post_type(
						'book',
						array(
							'public'      => true,
							'has_archive' => true,
						)
					);
					$post_ids = self::factory()->post->create_many( 4, array( 'post_type' => 'book' ) );
					$this->go_to( get_post_type_archive_link( 'book' ) );
					$GLOBALS['template'] = trailingslashit( get_template_directory() ) . 'archive-book.php';

					return function ( array $etag_data ) use ( $post_ids ): void {
						$this->assertTrue( is_post_type_archive( 'book' ) );
						$this->assertNull( $etag_data['queried_object']['id'] );
						$this->assertSame( 'book', $etag_data['queried_object']['type'] );
						$this->assertCount( 4, $etag_data['queried_posts'] );
						$this->assertEqualSets( $post_ids, wp_list_pluck( $etag_data['queried_posts'], 'id' ) );
						$this->assertSame( 'archive-book.php', $etag_data['current_template'] );
					};
				},
			),

			'page_for_posts'              => array(
				'set_up' => function (): Closure {
					$page_id = self::factory()->post->create( array( 'post_type' => 'page' ) );
					update_option( 'show_on_front', 'page' );
					update_option( 'page_for_posts', $page_id );

					$post_ids = self::factory()->post->create_many( 5 );
					$this->go_to( get_page_link( $page_id ) );
					$GLOBALS['template'] = trailingslashit( get_template_directory() ) . 'home.php';

					return function ( array $etag_data ) use ( $page_id, $post_ids ): void {
						$this->assertTrue( is_home() );
						$this->assertFalse( is_front_page() );
						$this->assertSame( $page_id, $etag_data['queried_object']['id'] );
						$this->assertSame( 'post', $etag_data['queried_object']['type'] );
						$this->assertCount( 5, $etag_data['queried_posts'] );
						$this->assertEqualSets( $post_ids, wp_list_pluck( $etag_data['queried_posts'], 'id' ) );
						$this->assertSame( 'home.php', $etag_data['current_template'] );
					};
				},
			),

			'block_theme'                 => array(
				'set_up' => function (): Closure {
					self::factory()->post->create();
					register_theme_directory( __DIR__ . '/../data/themes' );
					update_option( 'template', 'block-theme' );
					update_option( 'stylesheet', 'block-theme' );
					$this->go_to( '/' );
					$this->assertTrue( is_home() );
					$this->assertTrue( is_front_page() );
					$GLOBALS['_wp_current_template_id'] = 'block-theme//index';

					return function ( array $etag_data ): void {
						$this->assertTrue( wp_is_block_theme() );
						$this->assertIsArray( $etag_data['current_template'] );
						$this->assertEquals( 'wp_template', $etag_data['current_template']['type'] );
						$this->assertEquals( 'block-theme//index', $etag_data['current_template']['id'] );
						$this->assertArrayHasKey( 'modified', $etag_data['current_template'] );
					};
				},
			),
		);
	}

	/**
	 * Test od_get_current_url_metrics_etag().
	 *
	 * @dataProvider data_provider_test_od_get_current_url_metrics_etag
	 *
	 * @covers ::od_get_current_url_metrics_etag
	 * @covers ::od_get_current_theme_template
	 */
	public function test_od_get_current_url_metrics_etag( Closure $set_up ): void {
		$captured_etag_data = null;
		add_filter(
			'od_current_url_metrics_etag_data',
			static function ( array $data ) use ( &$captured_etag_data ) {
				$captured_etag_data = $data;
				return $data;
			},
			PHP_INT_MAX
		);

		$registry = new OD_Tag_Visitor_Registry();
		$registry->register( 'foo', static function (): void {} );
		$registry->register( 'bar', static function (): void {} );
		$registry->register( 'baz', static function (): void {} );
		$get_etag = static function () use ( $registry ) {
			global $wp_the_query;
			return od_get_current_url_metrics_etag( $registry, $wp_the_query, od_get_current_theme_template() );
		};

		$extra_assert = $set_up();

		$initial_active_theme = array(
			'template'   => array(
				'name'    => get_template(),
				'version' => wp_get_theme( get_template() )->get( 'Version' ),
			),
			'stylesheet' => array(
				'name'    => get_stylesheet(),
				'version' => wp_get_theme( get_stylesheet() )->get( 'Version' ),
			),
		);

		$etag = $get_etag();
		$this->assertMatchesRegularExpression( '/^[a-z0-9]{32}\z/', $etag );
		$this->assertIsArray( $captured_etag_data );
		$expected_keys = array( 'tag_visitors', 'queried_object', 'queried_posts', 'active_theme', 'current_template' );
		foreach ( $expected_keys as $expected_key ) {
			$this->assertArrayHasKey( $expected_key, $captured_etag_data );
		}
		$this->assertSame( $initial_active_theme, $captured_etag_data['active_theme'] );
		$this->assertContains( 'foo', $captured_etag_data['tag_visitors'] );
		$this->assertContains( 'bar', $captured_etag_data['tag_visitors'] );
		$this->assertContains( 'baz', $captured_etag_data['tag_visitors'] );
		$this->assertArrayHasKey( 'id', $captured_etag_data['queried_object'] );
		$this->assertArrayHasKey( 'type', $captured_etag_data['queried_object'] );
		$previous_captured_etag_data = $captured_etag_data;
		$this->assertSame( $etag, $get_etag() );
		$this->assertSame( $captured_etag_data, $previous_captured_etag_data );

		if ( $extra_assert instanceof Closure ) {
			$extra_assert( $captured_etag_data, $get_etag );
		}
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_to_test_hmac(): array {
		return array(
			'is_home'   => array(
				'set_up' => static function (): array {
					$post_id = self::factory()->post->create();
					return array(
						home_url(),
						od_get_url_metrics_slug( array() ),
						$post_id,
					);
				},
			),
			'is_single' => array(
				'set_up' => static function (): array {
					$post_id = self::factory()->post->create();
					return array(
						get_permalink( $post_id ),
						od_get_url_metrics_slug( array( 'p' => $post_id ) ),
						$post_id,
					);
				},
			),
		);
	}

	/**
	 * Test od_get_url_metrics_storage_hmac() and od_verify_url_metrics_storage_hmac().
	 *
	 * @dataProvider data_provider_to_test_hmac
	 *
	 * @covers ::od_get_url_metrics_storage_hmac
	 * @covers ::od_verify_url_metrics_storage_hmac
	 */
	public function test_od_get_url_metrics_storage_hmac_and_od_verify_url_metrics_storage_hmac( Closure $set_up ): void {
		list( $url, $slug, $cache_purge_post_id ) = $set_up();
		$this->go_to( $url );
		$hmac = od_get_url_metrics_storage_hmac( $slug, $url, $cache_purge_post_id );
		$this->assertMatchesRegularExpression( '/^[0-9a-f]+\z/', $hmac );
		$this->assertTrue( od_verify_url_metrics_storage_hmac( $hmac, $slug, $url, $cache_purge_post_id ) );
	}

	/**
	 * Test od_get_minimum_viewport_aspect_ratio().
	 *
	 * @covers ::od_get_minimum_viewport_aspect_ratio
	 */
	public function test_od_get_minimum_viewport_aspect_ratio(): void {
		$this->assertSame( 0.4, od_get_minimum_viewport_aspect_ratio() );

		add_filter(
			'od_minimum_viewport_aspect_ratio',
			static function () {
				return '0.6';
			}
		);

		$this->assertSame( 0.6, od_get_minimum_viewport_aspect_ratio() );
	}

	/**
	 * Test od_get_maximum_viewport_aspect_ratio().
	 *
	 * @covers ::od_get_maximum_viewport_aspect_ratio
	 */
	public function test_od_get_maximum_viewport_aspect_ratio(): void {
		$this->assertSame( 2.5, od_get_maximum_viewport_aspect_ratio() );

		add_filter(
			'od_maximum_viewport_aspect_ratio',
			static function () {
				return 3;
			}
		);

		$this->assertSame( 3.0, od_get_maximum_viewport_aspect_ratio() );
	}

	/**
	 * Test od_get_breakpoint_max_widths().
	 *
	 * @covers ::od_get_breakpoint_max_widths
	 */
	public function test_od_get_breakpoint_max_widths(): void {
		$this->assertSame(
			array( 480, 600, 782 ),
			od_get_breakpoint_max_widths()
		);

		$filtered_breakpoints = array( 2000, 500, '1000', 3000 );

		add_filter(
			'od_breakpoint_max_widths',
			static function () use ( $filtered_breakpoints ): array {
				return $filtered_breakpoints;
			}
		);

		$filtered_breakpoints = array_map( 'intval', $filtered_breakpoints );
		sort( $filtered_breakpoints );
		$this->assertSame( $filtered_breakpoints, od_get_breakpoint_max_widths() );
	}

	/**
	 * Data provider.
	 *
	 * @return array<string, mixed> Data.
	 */
	public function data_provider_test_bad_od_get_breakpoint_max_widths(): array {
		return array(
			'negative' => array(
				'breakpoints' => array( -1 ),
				'expected'    => array( 1 ),
			),
			'zero'     => array(
				'breakpoints' => array( 0 ),
				'expected'    => array( 1 ),
			),
			'max'      => array(
				'breakpoints' => array( PHP_INT_MAX ),
				'expected'    => array( PHP_INT_MAX - 1 ),
			),
			'multiple' => array(
				'breakpoints' => array( -1, 0, 10, PHP_INT_MAX ),
				'expected'    => array( 1, 10, PHP_INT_MAX - 1 ),
			),
		);
	}

	/**
	 * Test bad od_get_breakpoint_max_widths().
	 *
	 * @covers ::od_get_breakpoint_max_widths
	 *
	 * @expectedIncorrectUsage od_get_breakpoint_max_widths
	 * @dataProvider data_provider_test_bad_od_get_breakpoint_max_widths
	 *
	 * @param int[] $breakpoints Breakpoints.
	 * @param int[] $expected Expected breakpoints.
	 */
	public function test_bad_od_get_breakpoint_max_widths( array $breakpoints, array $expected ): void {
		add_filter(
			'od_breakpoint_max_widths',
			static function () use ( $breakpoints ): array {
				return $breakpoints;
			}
		);

		$this->assertSame( $expected, od_get_breakpoint_max_widths() );
	}

	/**
	 * Test od_get_url_metrics_breakpoint_sample_size().
	 *
	 * @covers ::od_get_url_metrics_breakpoint_sample_size
	 */
	public function test_od_get_url_metrics_breakpoint_sample_size(): void {
		$this->assertSame( 3, od_get_url_metrics_breakpoint_sample_size() );

		add_filter(
			'od_url_metrics_breakpoint_sample_size',
			static function (): string {
				return '1';
			}
		);

		$this->assertSame( 1, od_get_url_metrics_breakpoint_sample_size() );
	}

	/**
	 * Test bad od_get_url_metrics_breakpoint_sample_size().
	 *
	 * @expectedIncorrectUsage od_get_url_metrics_breakpoint_sample_size
	 * @covers ::od_get_url_metrics_breakpoint_sample_size
	 */
	public function test_bad_od_get_url_metrics_breakpoint_sample_size(): void {
		add_filter(
			'od_url_metrics_breakpoint_sample_size',
			static function (): int {
				return 0;
			}
		);

		$this->assertSame( 1, od_get_url_metrics_breakpoint_sample_size() );
	}
}
