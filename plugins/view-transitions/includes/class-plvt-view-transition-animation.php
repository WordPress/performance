<?php
/**
 * Class 'PLVT_View_Transition_Animation'.
 *
 * @package view-transitions
 * @since 1.0.0
 */

// @codeCoverageIgnoreStart
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
// @codeCoverageIgnoreEnd

/**
 * Class representing a view transition animation.
 *
 * @phpstan-type AnimationConfig array{
 *     aliases?: non-empty-string[],
 *     use_stylesheet?: bool|non-empty-string,
 *     use_global_transition_names?: bool|callable(string, array<string,string>):bool,
 *     use_post_transition_names?: bool|callable(string, array<string,string>):bool,
 *     get_stylesheet_callback?: callable(string, string, array<string,string>):string|null
 * }
 *
 * @since 1.0.0
 * @access private
 */
final class PLVT_View_Transition_Animation {

	/**
	 * The unique animation slug.
	 *
	 * @since 1.0.0
	 * @var non-empty-string
	 */
	private $slug;

	/**
	 * Unique aliases for the animation, if any.
	 *
	 * @since 1.0.0
	 * @var non-empty-string[]
	 */
	private $aliases = array();

	/**
	 * Whether the animation uses a stylesheet, optionally a concrete file path.
	 *
	 * If no concrete path is provided, it is assumed to be internal to the plugin, in
	 * `css/view-transition-animation-{$slug}.css`.
	 *
	 * @since 1.0.0
	 * @var bool|string
	 */
	private $use_stylesheet = false;

	/**
	 * Whether to apply the global view transition names while using this animation.
	 *
	 * @since 1.0.0
	 * @var bool|callable
	 */
	private $use_global_transition_names = true;

	/**
	 * Whether to apply the post-specific view transition names while using this animation.
	 *
	 * @since 1.0.0
	 * @var bool|callable
	 */
	private $use_post_transition_names = true;

	/**
	 * Callback to get the stylesheet for the animation, as inline CSS.
	 *
	 * This can be used if the animation CSS requires further preparation other than simply loading its stylesheet from
	 * the animation's corresponding CSS file.
	 *
	 * The callback will receive the CSS from the assigned stylesheet (or empty string if none), and the `$alias` and
	 * `$args` used as parameters.
	 *
	 * @since 1.0.0
	 * @var callable|null
	 */
	private $get_stylesheet_callback = null;

	/**
	 * Default animation arguments.
	 *
	 * These are provided during registration, and they are used if no specific arguments are provided when using the
	 * animation.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private $default_args = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @phpstan-param AnimationConfig $config Animation config.
	 *
	 * @param non-empty-string     $slug         Unique animation slug.
	 * @param array<string, mixed> $config       {
	 *     Animation configuration.
	 *
	 *     @type string[]      $aliases                     Unique aliases for the animation, if any. Default empty
	 *                                                      array.
	 *     @type bool|string   $use_stylesheet              Whether the animation uses a stylesheet, optionally a
	 *                                                      concrete file path. If no concrete path is provided, it is
	 *                                                      assumed to be internal to the plugin, in
	 *                                                      `css/view-transition-animation-{$slug}.css`. Default false.
	 *     @type bool|callable $use_global_transition_names Whether to apply the global view transition names while
	 *                                                      using this animation. Alternatively to a concrete value, a
	 *                                                      callback can be specified to determine it dynamically.
	 *                                                      Default true.
	 *     @type bool|callable $use_post_transition_names   Whether to apply the post-specific view transition names
	 *                                                      while using this animation. Alternatively to a concrete
	 *                                                      value, a callback can be specified to determine it
	 *                                                      dynamically. Default true.
	 *     @type callable|null $get_stylesheet_callback     Callback to get the stylesheet for the animation, as
	 *                                                      inline CSS. This can be used if the animation CSS requires
	 *                                                      further preparation other than simply loading its
	 *                                                      stylesheet from the animation's corresponding CSS file.
	 *                                                      Default null.
	 * }
	 * @param array<string, mixed> $default_args Optional. Default animation arguments. Default empty array.
	 *
	 * @throws InvalidArgumentException Thrown if the slug or an alias is invalid.
	 */
	public function __construct( string $slug, array $config, array $default_args = array() ) {
		if ( ! $this->is_valid_slug( $slug ) ) {
			throw new InvalidArgumentException(
				sprintf(
					/* translators: %s: invalid slug */
					esc_html__( 'The animation slug "%s" is invalid.', 'view-transitions' ),
					esc_html( $slug )
				)
			);
		}

		$this->slug = $slug;

		$this->apply_config( $config );

		$this->default_args = $default_args;
	}

	/**
	 * Gets the unique animation slug.
	 *
	 * @since 1.0.0
	 *
	 * @return non-empty-string Unique animation slug.
	 */
	public function get_slug(): string {
		return $this->slug;
	}

	/**
	 * Gets the unique aliases for the animation, if any.
	 *
	 * @since 1.0.0
	 *
	 * @return non-empty-string[] Unique aliases for the animation, or empty array if none.
	 */
	public function get_aliases(): array {
		return $this->aliases;
	}

	/**
	 * Gets the animation stylesheet, as inline CSS.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $alias Optional. Slug or alias to reference the animation with. May be used to alter
	 *                                    the animation's behavior. Default is the animation's slug.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return string Animation stylesheet, as inline CSS, or empty string if none.
	 */
	public function get_stylesheet( string $alias = '', array $args = array() ): string {
		$css = '';
		if ( (bool) $this->use_stylesheet ) {
			$stylesheet_path = $this->use_stylesheet;
			if ( ! is_string( $stylesheet_path ) ) {
				$stylesheet_path = plvt_get_asset_path( "css/view-transition-animation-{$this->slug}.css" );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$css = file_get_contents( $stylesheet_path );
			if ( false === $css || '' === $css ) {
				// This clause should never be entered, but is needed to please PHPStan. Can't hurt to be safe.
				return '';
			}
		}

		if ( is_callable( $this->get_stylesheet_callback ) ) {
			if ( '' === $alias ) {
				$alias = $this->slug;
			}
			$args = wp_parse_args( $args, $this->default_args );
			return (string) call_user_func_array(
				$this->get_stylesheet_callback,
				array( $css, $alias, $args )
			);
		}

		return $css;
	}

	/**
	 * Returns whether to apply the global view transition names while using this animation.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $alias Optional. Slug or alias to reference the animation with. May be used to alter
	 *                                    the animation's behavior. Default is the animation's slug.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return bool True if the global view transition names should be applied, false otherwise.
	 */
	public function use_global_transition_names( string $alias = '', array $args = array() ): bool {
		if ( is_bool( $this->use_global_transition_names ) ) {
			return $this->use_global_transition_names;
		}
		if ( '' === $alias ) {
			$alias = $this->slug;
		}
		$args = wp_parse_args( $args, $this->default_args );
		return call_user_func( $this->use_global_transition_names, $alias, $args );
	}

	/**
	 * Returns whether to apply the post-specific view transition names while using this animation.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $alias Optional. Slug or alias to reference the animation with. May be used to alter
	 *                                    the animation's behavior. Default is the animation's slug.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return bool True if the post-specific view transition names should be applied, false otherwise.
	 */
	public function use_post_transition_names( string $alias = '', array $args = array() ): bool {
		if ( is_bool( $this->use_post_transition_names ) ) {
			return $this->use_post_transition_names;
		}
		if ( '' === $alias ) {
			$alias = $this->slug;
		}
		$args = wp_parse_args( $args, $this->default_args );
		return call_user_func( $this->use_post_transition_names, $alias, $args );
	}

	/**
	 * Applies the given configuration to the class properties.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $config Animation configuration. See
	 *                                     {@see PLVT_View_Transition_Animation::__construct()} for possible values.
	 *
	 * @throws InvalidArgumentException Thrown when an invalid alias is provided.
	 */
	private function apply_config( array $config ): void {
		if ( isset( $config['aliases'] ) ) {
			$this->aliases = (array) $config['aliases'];
			foreach ( $this->aliases as $alias ) {
				if ( ! $this->is_valid_slug( $alias ) ) {
					throw new InvalidArgumentException(
						sprintf(
							/* translators: %s: invalid alias */
							esc_html__( 'The animation alias "%s" is invalid.', 'view-transitions' ),
							esc_html( $alias )
						)
					);
				}
			}
		}
		if ( isset( $config['use_stylesheet'] ) ) {
			$this->use_stylesheet = is_string( $config['use_stylesheet'] ) ? $config['use_stylesheet'] : (bool) $config['use_stylesheet'];
		}
		if ( isset( $config['use_global_transition_names'] ) ) {
			$this->use_global_transition_names = is_callable( $config['use_global_transition_names'] ) ?
				$config['use_global_transition_names'] :
				(bool) $config['use_global_transition_names'];
		}
		if ( isset( $config['use_post_transition_names'] ) ) {
			$this->use_post_transition_names = is_callable( $config['use_post_transition_names'] ) ?
				$config['use_post_transition_names'] :
				(bool) $config['use_post_transition_names'];
		}
		if ( isset( $config['get_stylesheet_callback'] ) && is_callable( $config['get_stylesheet_callback'] ) ) {
			$this->get_stylesheet_callback = $config['get_stylesheet_callback'];
		}
	}

	/**
	 * Checks whether the given slug (or alias) is valid.
	 *
	 * @since 1.0.0
	 *
	 * @param string $slug Animation slug or alias.
	 * @return bool True if the ID is valid, false otherwise.
	 */
	private function is_valid_slug( string $slug ): bool {
		return (bool) preg_match( '/^[a-z][a-z0-9_-]+$/', $slug );
	}
}
