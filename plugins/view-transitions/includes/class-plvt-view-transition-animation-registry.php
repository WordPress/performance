<?php
/**
 * Class 'PLVT_View_Transition_Animation_Registry'.
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
 * Class representing a view transition animation registry.
 *
 * @phpstan-import-type AnimationConfig from PLVT_View_Transition_Animation
 *
 * @since 1.0.0
 */
final class PLVT_View_Transition_Animation_Registry {

	/**
	 * Registered animation class instances, keyed by slug.
	 *
	 * @since 1.0.0
	 * @var array<non-empty-string, PLVT_View_Transition_Animation>
	 */
	private $registered_animations = array();

	/**
	 * Map of aliases to their animation slugs.
	 *
	 * Includes the animation slug itself to avoid unnecessary conditionals.
	 *
	 * @since 1.0.0
	 * @var array<non-empty-string, non-empty-string>
	 */
	private $alias_map = array();

	/**
	 * Registers a view transition animation.
	 *
	 * @since 1.0.0
	 *
	 * @phpstan-param AnimationConfig $config Animation config.
	 *
	 * @param non-empty-string     $slug         Unique animation slug.
	 * @param array<string, mixed> $config       Animation configuration. See
	 *                                           {@see PLVT_View_Transition_Animation::__construct()} for possible
	 *                                           values.
	 * @param array<string, mixed> $default_args Optional. Default animation arguments. Default empty array.
	 * @return bool True on success, false on failure.
	 */
	public function register_animation( string $slug, array $config, array $default_args = array() ): bool {
		// Check slug conflict.
		if ( isset( $this->alias_map[ $slug ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
					/* translators: %s: duplicate slug */
					esc_html__( 'The animation slug "%s" conflicts with an existing slug or alias.', 'view-transitions' ),
					esc_html( $slug )
				),
				'1.0.0'
			);
			return false;
		}

		try {
			$animation = new PLVT_View_Transition_Animation( $slug, $config, $default_args );
		} catch ( InvalidArgumentException $e ) {
			_doing_it_wrong(
				__METHOD__,
				esc_html( $e->getMessage() ),
				'1.0.0'
			);
			return false;
		}

		// Check alias conflicts.
		$aliases = $animation->get_aliases();
		foreach ( $aliases as $alias ) {
			if ( isset( $this->alias_map[ $alias ] ) ) {
				_doing_it_wrong(
					__METHOD__,
					sprintf(
						/* translators: %s: duplicate alias */
						esc_html__( 'The animation alias "%s" conflicts with an existing slug or alias.', 'view-transitions' ),
						esc_html( $alias )
					),
					'1.0.0'
				);
				return false;
			}
		}

		$this->registered_animations[ $slug ] = $animation;
		$this->alias_map[ $slug ]             = $slug;
		foreach ( $aliases as $alias ) {
			$this->alias_map[ $alias ] = $slug;
		}

		return true;
	}

	/**
	 * Gets the animation stylesheet for the given alias, as inline CSS.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $alias Slug or alias to reference the animation with. May be used to alter the
	 *                                    animation's behavior.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return string Animation stylesheet, as inline CSS, or empty string if none.
	 */
	public function get_animation_stylesheet( string $alias, array $args = array() ): string {
		if ( ! isset( $this->alias_map[ $alias ] ) ) {
			return '';
		}

		return $this->registered_animations[ $this->alias_map[ $alias ] ]->get_stylesheet( $alias, $args );
	}

	/**
	 * Returns whether to apply the global view transition names for the given animation alias.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $alias Slug or alias to reference the animation with. May be used to alter the
	 *                                    animation's behavior.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return bool True if the global view transition names should be applied, false otherwise.
	 */
	public function use_animation_global_transition_names( string $alias, array $args = array() ): bool {
		if ( ! isset( $this->alias_map[ $alias ] ) ) {
			return true;
		}

		return $this->registered_animations[ $this->alias_map[ $alias ] ]->use_global_transition_names( $alias, $args );
	}

	/**
	 * Returns whether to apply the post-specific view transition names for the given animation alias.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $alias Slug or alias to reference the animation with. May be used to alter the
	 *                                    animation's behavior.
	 * @param array<string, mixed> $args  Optional. Animation arguments. Default is the animation's default arguments.
	 * @return bool True if the post-specific view transition names should be applied, false otherwise.
	 */
	public function use_animation_post_transition_names( string $alias, array $args = array() ): bool {
		if ( ! isset( $this->alias_map[ $alias ] ) ) {
			return true;
		}

		return $this->registered_animations[ $this->alias_map[ $alias ] ]->use_post_transition_names( $alias, $args );
	}
}
