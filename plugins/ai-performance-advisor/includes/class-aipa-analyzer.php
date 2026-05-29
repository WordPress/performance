<?php
/**
 * AI analysis engine.
 *
 * @package ai-performance-advisor
 *
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the analysis context, calls the AI model, and returns recommendations.
 *
 * @since 1.0.0
 */
class AIPA_Analyzer {

	const CACHE_KEY = 'aipa_last_analysis';

	/**
	 * Runs an analysis and returns sanitized recommendations.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $use_cache Whether to return a cached result when the context is unchanged.
	 * @return array<int, array<string, mixed>>|WP_Error Recommendations, or an error.
	 */
	public function analyze( bool $use_cache = true ) {
		if ( ! aipa_is_ai_available() ) {
			return new WP_Error(
				'aipa_ai_unavailable',
				__( 'AI is not available. Connect an AI provider that supports text generation to use the AI Performance Advisor.', 'ai-performance-advisor' )
			);
		}

		$context = aipa_get_context_registry()->collect();
		$hash    = aipa_get_context_hash( $context );

		if ( $use_cache ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) && isset( $cached['hash'] ) && $cached['hash'] === $hash && isset( $cached['recommendations'] ) ) {
				return $cached['recommendations'];
			}
		}

		$text = $this->request( $context );
		if ( is_wp_error( $text ) ) {
			return $text;
		}

		$recommendations = aipa_sanitize_recommendations( $this->decode_json( $text ) );

		set_transient(
			self::CACHE_KEY,
			array(
				'hash'            => $hash,
				'recommendations' => $recommendations,
			),
			12 * HOUR_IN_SECONDS
		);

		return $recommendations;
	}

	/**
	 * Sends the prompt to the AI model.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The assembled context payload.
	 * @return string|WP_Error The raw model response, or an error.
	 */
	private function request( array $context ) {
		$user_prompt = sprintf(
			"Analyze the following WordPress site data and return performance recommendations as JSON.\n\nSITE DATA:\n%s",
			(string) wp_json_encode( $context )
		);

		try {
			$result = wp_ai_client_prompt( $user_prompt )
				->using_system_instruction( $this->get_system_instruction() )
				->using_temperature( 0.2 )
				->generate_text();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'aipa_ai_request_failed', $e->getMessage() );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( ! is_string( $result ) || '' === trim( $result ) ) {
			return new WP_Error( 'aipa_ai_empty_response', __( 'The AI provider returned an empty response.', 'ai-performance-advisor' ) );
		}

		return $result;
	}

	/**
	 * Decodes JSON from a model response, tolerating Markdown code fences.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The raw model response.
	 * @return mixed Decoded data, or null on failure.
	 */
	private function decode_json( string $text ) {
		$text = trim( $text );

		// Strip a leading/trailing Markdown code fence if present.
		if ( 0 === strpos( $text, '```' ) ) {
			$text = (string) preg_replace( '/^```[a-zA-Z]*\s*/', '', $text );
			$text = (string) preg_replace( '/\s*```$/', '', $text );
		}

		$decoded = json_decode( $text, true );
		if ( null !== $decoded ) {
			return $decoded;
		}

		// As a fallback, extract the first JSON array or object in the text.
		if ( 1 === preg_match( '/(\[.*\]|\{.*\})/s', $text, $matches ) ) {
			return json_decode( $matches[1], true );
		}

		return null;
	}

	/**
	 * Returns the system instruction that defines the advisor persona and output contract.
	 *
	 * @since 1.0.0
	 *
	 * @return string The system instruction.
	 */
	private function get_system_instruction(): string {
		$severities = implode( ', ', aipa_get_severities() );
		$categories = implode( ', ', aipa_get_categories() );

		return sprintf(
			'You are a WordPress performance expert. You are given structured data about a WordPress site (server and database configuration, active plugins and theme, Site Health test results, and possibly a PageSpeed Insights snapshot). ' .
			'Analyze it and produce specific, actionable performance recommendations tailored to this site. Prefer concrete, high-impact advice over generic tips, and cite the data that supports each recommendation. ' .
			'Assets served from a plugin or theme include the slug in their path (/wp-content/plugins/{slug}/ or /wp-content/themes/{slug}/); use this to attribute issues. Do not invent data that is not present. ' .
			"\n\n" .
			'Respond with ONLY a JSON array (no prose, no Markdown fences). Each array item is an object with these fields: ' .
			'"id" (a short stable slug), ' .
			'"title" (a concise headline), ' .
			'"severity" (one of: %1$s), ' .
			'"category" (one of: %2$s), ' .
			'"summary" (one or two sentences on what to do and why), ' .
			'"details" (a longer explanation including manual fix steps; plain text or simple Markdown), ' .
			'"evidence" (an array of short strings naming the data points that triggered this recommendation). ' .
			'Order the array from most to least urgent. Return at most 12 recommendations.',
			$severities,
			$categories
		);
	}
}
