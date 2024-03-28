<?php
/**
 * Performance Lab Database Utility Functions.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 * @noinspection SpellCheckingInspection
 */

/**
 * Performance Lab Database Utility Functions class, including thresholds.
 *
 * @package performance-lab
 * @group audit-database
 *
 * @since 1.4.0
 */
class PerflabDbUtilities {
	const KILO = 1024;
	const MEGA = self::KILO * self::KILO;
	const GIGA = self::MEGA * self::KILO;
	const TERA = self::GIGA * self::KILO;
	/** Preset threshold values.
	 *
	 * These can be changed with the perflab_db_threshold filter.
	 *
	 * @var array  the values
	 */
	private $thresholds;
	/** Singleton instance
	 *
	 * @var PerflabDbUtilities instance.
	 */
	private static $instance;
	/** Test sequence number.
	 *
	 * @var integer
	 */
	private $test_sequence_number = 0;

	/** Constructor, private because this is a singleton.
	 */
	private function __construct() {
		$this->thresholds = array(
			/* 10 ms means server is very slow--critical */
			'server_response_very_slow'  => 10000.0,
			/* 2 ms means server is slow-suggestion */
			'server_response_slow'       => 2000.0,
			/* times to repeat the connect / query / disconnect operation */
			'server_response_iterations' => 20.0,
			/* how long before abandoning the repetition of connect / query / disconnect */
			'server_response_timeout'    => 100000.0,
			/* the desired storage engine and row format */
			'target_storage_engine'      => 'InnoDB',
			'target_row_format'          => 'Dynamic',
			/* 0.5 here means suggest if the pool size is less than half the total table size */
			'pool_size_fraction_min'     => 0.5,
			/* suggest when there are more than this many users */
			'target_user_count'          => 20000,
			/* ignore indexes with names starting with these prefixes */
			'index_stop_list'            => 'woo_|crp_|yarpp_',
			/* whatever_meta tables considered "large" if they have this many rows or more */
			'meta_size'                  => 2000,
			/* other tables like wp_posts considered "large" if they have this many rows or more */
			'content_size'               => 500,
			/* for server metrics, don't compute differences if the time between samples is less than this */
			'minimum_delta_timestamp'    => 600,
			/* for server metrics, don't save a new sample until the older one is this old */
			'maximum_delta_timestamp'    => 1800,
			/* this is a terrible buffer cache hit rate */
			'cache_hit_rate_terrible'    => 0.9,
			/* this is a bad buffer cache hit rate */
			'cache_hit_rate_bad'         => 0.99,
		);
	}

	/** Get the singleton utility instance.
	 *
	 * @return PerflabDbUtilities
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}



	/** Generate health-check result array.
	 *
	 * @param string $label Test label visible to user.
	 * @param string $description Test long description visible to user.
	 * @param string $actions Actions to take to correct the problem, visible to user, default ''.
	 * @param string $status 'critical', 'recommended', 'good', default 'good'.
	 * @param string $color a color specifier like 'blue', 'orange', 'red', default 'blue'.
	 *
	 * @return array
	 */
	public function test_result( $label, $description, $actions = '', $status = 'good', $color = 'blue' ) {
		$badge_label = __( 'Database Performance', 'performance-lab' );
		return $this->result( $badge_label, $label, $description, $actions, $status, $color );
	}

	/** Generate index-check result array.
	 *
	 * @param string $label Test label visible to user.
	 * @param string $description Test long description visible to user.
	 * @param string $actions Actions to take to correct the problem, visible to user, default ''.
	 * @param string $status 'critical', 'recommended', 'good', default 'good'.
	 * @param string $color a color specifier like 'blue', 'orange', 'red', default 'blue'.
	 *
	 * @return array
	 */
	public function test_index_result( $label, $description, $actions = '', $status = 'good', $color = 'blue' ) {
		$badge_label = __( 'Database Keys', 'performance-lab' );
		return $this->result( $badge_label, $label, $description, $actions, $status, $color );
	}

		/** Generate health-check result array.
		 *
		 * @param string $badge_label Badge text next to accordion.
		 * @param string $label Test label visible to user.
		 * @param string $description Test long description visible to user.
		 * @param string $actions Actions to take to correct the problem, visible to user, default ''.
		 * @param string $status 'critical', 'recommended', 'good', default 'good'.
		 * @param string $color a color specifier like 'blue', 'orange', 'red', default 'blue'.
		 *
		 * @return array
		 */
	private function result( $badge_label, $label, $description, $actions = '', $status = 'good', $color = 'blue' ) {
		$this->test_sequence_number ++;

		return array(
			'label'       => esc_html( $label ),
			'status'      => $status,
			'description' => $this->sanitize( $description ),
			'badge'       => array(
				'label' => esc_html( $badge_label ),
				'color' => $color,
			),
			'actions'     => is_string( $actions ) ? $this->sanitize( $actions ) : '',
			'test'        => 'database_performance' . $this->test_sequence_number,
		);
	}

	/** Sanitize HTML (containing some markup) before sending it to the browser.
	 *
	 * @since 1.4.0
	 *
	 * @param string $string HTML to sanitize.
	 *
	 * @return string for browser.
	 */
	private function sanitize( $string ) {
		return wp_kses( $string, 'post', array( 'https', 'http' ) );
	}
	/** Get a DB threshold value after filtering it.
	 *
	 * @param string $name Name of the value.
	 *
	 * @return mixed The value.
	 * @throws InvalidArgumentException Upon an unrecognized name.
	 */
	public function get_threshold_value( $name ) {
		if ( ! array_key_exists( $name, $this->thresholds ) ) {
			$value = null;
		} else {
			$value = $this->thresholds[ $name ];
		}

		/**
		 * Filter database performance troubleshooting thresholds.
		 *
		 * To filter a particular threshold value, check the $name for a match,
		 * then return the $value you want.
		 *
		 * @since 1.4.0
		 *
		 * @param mixed $value The threshold value.
		 * @param string $name The threshold name.
		 */

		$value = apply_filters( 'perflab_db_threshold', $value, $name );
		if ( null === $value ) {
			throw new InvalidArgumentException( $name . ': no such PerflabDbThreshold item' );
		}
		return $value;

	}
	/** Arithmetic mean
	 *
	 * @param array $a dataset.
	 *
	 * @return number
	 */
	public function mean( array $a ) {
		$n = count( $a );
		if ( ! $n ) {
			return null;
		}
		if ( 1 === $n ) {
			return $a[0];
		}
		$acc = 0;
		foreach ( $a as $v ) {
			$acc += $v;
		}

		return $acc / $n;
	}

	/** Mean absolute deviation.
	 *
	 * @param array $a dataset.
	 *
	 * @return float|int|null
	 */
	public function mad( array $a ) {
		$n = count( $a );
		if ( ! $n ) {
			return null;
		}
		if ( 1 === $n ) {
			return 0.0;
		}
		$acc = 0;
		foreach ( $a as $v ) {
			$acc += $v;
		}
		$mean = $acc / $n;
		$acc  = 0;
		foreach ( $a as $v ) {
			$acc += abs( $v - $mean );
		}

		return $acc / $n;
	}

	/** Percentile.
	 *
	 * @param array  $a dataset, an array of numbers.
	 * @param number $p percentile as fraction 0-1.
	 *
	 * @return float
	 */
	public function percentile( array $a, $p ) {
		$n = count( $a );
		sort( $a );
		$i = floor( $n * $p );
		if ( $i >= $n ) {
			$i = $n - 1;
		}

		return $a[ $i ];
	}
	/** Convert an array to an object, and the numeric properties therein to numbers rather than strings.
	 *
	 * @param array $ob associative array.
	 *
	 * @return object
	 */
	public function make_numeric( $ob ) {
		$result = array();
		foreach ( $ob as $key => $val ) {
			if ( is_numeric( $val ) ) {
				$val = $val + 0;
			}
			$result[ $key ] = $val;
		}

		return (object) $result;
	}

	/** Get formatted byte counts
	 *
	 * @param number     $bytes  Byte count to format.
	 * @param array|null $unit The unit to use.
	 * @param string     $prefix A prefix to apply.
	 *
	 * @return string
	 */
	public function format_bytes( $bytes, $unit = null, $prefix = '' ) {
		if ( 0.0 === $bytes ) {
			return '' !== $prefix ? '' : '0';
		}
		if ( null === $unit ) {
			$unit = $this->get_byte_unit( $bytes );
		}

		return $prefix . number_format_i18n( $bytes / $unit[0], $unit[2] ) . $unit[1];
	}

	/** Retrieve appropriate unit and decimal places.
	 *
	 * @param number $bytes  Byte count to format.
	 *
	 * @return array
	 */
	private function get_byte_unit( $bytes ) {
		if ( $bytes >= self::TERA ) {
			$unit = array( self::TERA, 'TiB', 0 );
		} elseif ( $bytes >= self::TERA * 0.5 ) {
			$unit = array( self::TERA, 'TiB', 1 );
		} elseif ( $bytes >= self::GIGA ) {
			$unit = array( self::TERA, 'GiB', 0 );
		} elseif ( $bytes >= self::GIGA * 0.5 ) {
			$unit = array( self::GIGA, 'GiB', 1 );
		} elseif ( $bytes >= self::MEGA ) {
			$unit = array( self::MEGA, 'MiB', 0 );
		} elseif ( $bytes >= self::MEGA * 0.5 ) {
			$unit = array( self::MEGA, 'MiB', 1 );
		} elseif ( $bytes >= self::KILO ) {
			$unit = array( self::KILO, 'KiB', 0 );
		} elseif ( $bytes >= self::KILO * 0.1 ) {
			$unit = array( self::KILO, 'KiB', 1 );
		} else {
			$unit = array( 1, 'B', 0 );
		}

		return $unit;
	}

	/** Round up a number to the next highest power of two.
	 *
	 * @param float|int $val  like 33000.
	 *
	 * @return int rounded up number like 65536.
	 */
	public function power_of_two_round_up( $val ) {
		$val = $val <= 0 ? 1 : $val;
		return intval( pow( 2, ceil( log( 0.0 + $val, 2 ) ) ) );
	}

	/** Get Instructions
	 *
	 * @param callable $formatter Function to format WP-CLI command, called with ($tablename, $contents).
	 * @param string   $explanation The problem.
	 * @param string   $exhortation What to do about it.
	 * @param string   $header_1 Column header for the first column of instructions.
	 * @param string   $header_2 Column header for the second column of instructions.
	 * @param array    $tables Associative array indexed by table name.
	 *
	 * @return string[] [$description, $action] HTML strings for description and action to take.
	 */
	public function instructions( callable $formatter, $explanation, $exhortation, $header_1, $header_2, $tables ) {
		/* these are fragments of HTML that will be concatenated with implode() */
		$html = array();

		$html[] = '<p class="description">';
		$html[] = $explanation;
		$html[] = '</p>';

		$html[] = '<p class="description">';
		$html[] = $exhortation;
		$html[] = '</p>';

		/* handle the copy-to-clipboard UI HTML */
		$clip          = plugin_dir_url( __FILE__ ) . 'assets/clip.svg';
		$copy_txt      = esc_attr__( 'Copy to clipboard', 'performance-lab' );
		$copied_txt    = esc_attr__( 'Copied', 'performance-lab' );
		$copyall_txt   = esc_attr__( 'Copy all commands to clipboard', 'performance-lab' );
		$copiedall_txt = 1 === count( $tables ) ? $copied_txt : sprintf(
			/* translators: 1 number of command lines copied to clipboard */
			esc_attr__( 'Copied %d commands', 'performance-lab' ),
			count( $tables )
		);

		/*
		 * ClipboardJS doesn't handle large batches of text. Sigh.
		 * $header_icon = "<div title=\"$copyall_txt\" data-normal=\"dashicons-clipboard\" data-ack=\"dashicons-yes\" class=\"dashicons dashicons-clipboard clip\" ></div>";
		 */
		$header_icon = '';
		$icon        = "<div title=\"$copy_txt\" data-normal=\"dashicons-clipboard\" data-ack=\"dashicons-yes\" class=\"dashicons dashicons-clipboard clip\" ></div>";
		$acts        = array();
		$acts[]      = '<div class="panel">';

		$acts[] = '<table class="upgrades"><thead><tr>';
		$acts[] = '<th scope="col" class=\"table\">' . $header_1 . '</th>';
		$acts[] = '<th scope="col">' . $header_2 . '</th>';
		$acts[] = '<th scope="col" class=\"icon\">' . $header_icon . '</th>';
		$acts[] = '</tr></thead><tbody>';
		foreach ( $tables as $table_name => $data ) {
			$acts[]  = '<tr>';
			$acts[]  = "<td class=\"table\">$table_name</td>";
			$acts[]  = '<td class="cmd">';
			$acts[]  = '<pre class="item">';
			$acts[]  = call_user_func( $formatter, $table_name, $data );
			$acts [] = '</pre>';
			$acts[]  = '</td>';
			$acts[]  = "<td class=\"icon\">$icon</td>";
			$acts[]  = '</tr>';
		}
		$acts [] = '</tbody></table></div>';

		return array( implode( '', $html ), implode( '', $acts ) );
	}

}
