<?php
/**
 * Main integration file.
 *
 * @package performance-lab
 * @since 1.8.0
 */

/**
 * Function to create tables according to the schemas of WordPress.
 *
 * This is executed only once while installation.
 *
 * @since 1.8.0
 *
 * @return boolean
 */
function perflab_sqlite_make_db_sqlite() {
	include_once ABSPATH . 'wp-admin/includes/schema.php';
	$index_array = array();

	$table_schemas = wp_get_db_schema();
	$queries       = explode( ';', $table_schemas );
	$query_parser  = new Perflab_SQLite_Create_Query();
	try {
		$pdo = new PDO( 'sqlite:' . FQDB, null, null, array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ) ); // phpcs:ignore WordPress.DB.RestrictedClasses
	} catch ( PDOException $err ) {
		$err_data = $err->errorInfo; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$message  = 'Database connection error!<br />';
		$message .= sprintf( 'Error message is: %s', $err_data[2] );
		wp_die( $message, 'Database Error!' );
	}

	try {
		$pdo->beginTransaction();
		foreach ( $queries as $query ) {
			$query = trim( $query );
			if ( empty( $query ) ) {
				continue;
			}
			$rewritten_query = $query_parser->rewrite_query( $query );
			if ( is_array( $rewritten_query ) ) {
				$table_query   = array_shift( $rewritten_query );
				$index_queries = $rewritten_query;
				$table_query   = trim( $table_query );
				$pdo->exec( $table_query );
			} else {
				$rewritten_query = trim( $rewritten_query );
				$pdo->exec( $rewritten_query );
			}
		}
		$pdo->commit();
		if ( $index_queries ) {
			// $query_parser rewrites KEY to INDEX, so we don't need KEY pattern.
			$pattern = '/CREATE\\s*(UNIQUE\\s*INDEX|INDEX)\\s*IF\\s*NOT\\s*EXISTS\\s*(\\w+)?\\s*.*/im';
			$pdo->beginTransaction();
			foreach ( $index_queries as $index_query ) {
				preg_match( $pattern, $index_query, $match );
				$index_name = trim( $match[2] );
				if ( in_array( $index_name, $index_array, true ) ) {
					$r           = rand( 0, 50 );
					$replacement = $index_name . "_$r";
					$index_query = str_ireplace(
						'EXISTS ' . $index_name,
						'EXISTS ' . $replacement,
						$index_query
					);
				} else {
					$index_array[] = $index_name;
				}
				$pdo->exec( $index_query );
			}
			$pdo->commit();
		}
	} catch ( PDOException $err ) {
		$err_data = $err->errorInfo; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$err_code = $err_data[1];
		if ( 5 == $err_code || 6 == $err_code ) { // phpcs:ignore WordPress.PHP.StrictComparisons.LooseComparison
			// If the database is locked, commit again.
			$pdo->commit();
		} else {
			$pdo->rollBack();
			$message  = sprintf(
				'Error occurred while creating tables or indexes...<br />Query was: %s<br />',
				var_export( $rewritten_query, true )
			);
			$message .= sprintf( 'Error message is: %s', $err_data[2] );
			wp_die( $message, 'Database Error!' );
		}
	}

	$query_parser = null;
	$pdo          = null;

	return true;
}

/**
 * Installs the site.
 *
 * Runs the required functions to set up and populate the database,
 * including primary admin user and initial options.
 *
 * @since 1.8.0
 *
 * @param string $blog_title    Site title.
 * @param string $user_name     User's username.
 * @param string $user_email    User's email.
 * @param bool   $is_public     Whether the site is public.
 * @param string $deprecated    Optional. Not used.
 * @param string $user_password Optional. User's chosen password. Default empty (random password).
 * @param string $language      Optional. Language chosen. Default empty.
 * @return array {
 *     Data for the newly installed site.
 *
 *     @type string $url              The URL of the site.
 *     @type int    $user_id          The ID of the site owner.
 *     @type string $password         The password of the site owner, if their user account didn't already exist.
 *     @type string $password_message The explanatory message regarding the password.
 * }
 */
function wp_install( $blog_title, $user_name, $user_email, $is_public, $deprecated = '', $user_password = '', $language = '' ) {
	if ( ! empty( $deprecated ) ) {
		_deprecated_argument( __FUNCTION__, '2.6.0' );
	}

	wp_check_mysql_version();
	wp_cache_flush();
	/* SQLite changes: Replace the call to make_db_current_silent() with perflab_sqlite_make_db_sqlite(). */
	perflab_sqlite_make_db_sqlite();
	populate_options();
	populate_roles();

	update_option( 'blogname', $blog_title );
	update_option( 'admin_email', $user_email );
	update_option( 'blog_public', $is_public );

	// Freshness of site - in the future, this could get more specific about actions taken, perhaps.
	update_option( 'fresh_site', 1 );

	if ( $language ) {
		update_option( 'WPLANG', $language );
	}

	$guessurl = wp_guess_url();

	update_option( 'siteurl', $guessurl );

	// If not a public site, don't ping.
	if ( ! $is_public ) {
		update_option( 'default_pingback_flag', 0 );
	}

	/*
	 * Create default user. If the user already exists, the user tables are
	 * being shared among sites. Just set the role in that case.
	 */
	$user_id        = username_exists( $user_name );
	$user_password  = trim( $user_password );
	$email_password = false;
	$user_created   = false;

	if ( ! $user_id && empty( $user_password ) ) {
		$user_password = wp_generate_password( 12, false );
		$message       = __( '<strong><em>Note that password</em></strong> carefully! It is a <em>random</em> password that was generated just for you.', 'performance-lab' );
		$user_id       = wp_create_user( $user_name, $user_password, $user_email );
		update_user_meta( $user_id, 'default_password_nag', true );
		$email_password = true;
		$user_created   = true;
	} elseif ( ! $user_id ) {
		// Password has been provided.
		$message      = '<em>' . __( 'Your chosen password.', 'performance-lab' ) . '</em>';
		$user_id      = wp_create_user( $user_name, $user_password, $user_email );
		$user_created = true;
	} else {
		$message = __( 'User already exists. Password inherited.', 'performance-lab' );
	}

	$user = new WP_User( $user_id );
	$user->set_role( 'administrator' );

	if ( $user_created ) {
		$user->user_url = $guessurl;
		wp_update_user( $user );
	}

	wp_install_defaults( $user_id );

	wp_install_maybe_enable_pretty_permalinks();

	flush_rewrite_rules();

	wp_new_blog_notification( $blog_title, $guessurl, $user_id, ( $email_password ? $user_password : __( 'The password you chose during installation.', 'performance-lab' ) ) );

	wp_cache_flush();

	/**
	 * Fires after a site is fully installed.
	 *
	 * @since 3.9.0
	 *
	 * @param WP_User $user The site owner.
	 */
	do_action( 'wp_install', $user );

	return array(
		'url'              => $guessurl,
		'user_id'          => $user_id,
		'password'         => $user_password,
		'password_message' => $message,
	);
}
