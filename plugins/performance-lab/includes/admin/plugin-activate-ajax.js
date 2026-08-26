/**
 * Handles activation of Performance Features (Plugins) using AJAX.
 */

( function () {
	// @ts-ignore
	const { i18n, a11y, apiFetch } = wp;
	const { __ } = i18n;

	// Whether the current request needs FTP/SSH credentials before the
	// plugin can be installed. Set from PHP via wp_add_inline_script().
	const filesystemCredentialsRequired = Boolean(
		// @ts-ignore
		window.perflabPluginActivate?.filesystemCredentialsRequired
	);

	// Queue to hold pending activation requests.
	/** @type {{target: HTMLElement, pluginSlug: string}[]} */
	const activationQueue = [];
	let isProcessingActivation = false;

	/**
	 * Installs a plugin via wp.updates.installPlugin() so the standard
	 * "Connection Information" modal handles the FTP/SSH credentials flow.
	 *
	 * Resolves with a status describing the outcome:
	 *   - 'installed': the plugin was written to disk.
	 *   - 'canceled': the user closed the credentials modal.
	 * Rejects only on a genuine install failure (e.g. download error or
	 * wp.updates not being available on the page).
	 *
	 * @param {string} slug Plugin slug.
	 * @return {Promise<'installed' | 'canceled'>} Outcome of the install.
	 */
	function installPluginViaWpUpdates( slug ) {
		return new Promise( ( resolve, reject ) => {
			// @ts-ignore
			const updates = window.wp?.updates;
			// @ts-ignore
			const $ = window.jQuery;
			if ( ! updates?.installPlugin || ! $ ) {
				reject(
					new Error( 'wp.updates.installPlugin is not available.' )
				);
				return;
			}

			const cleanup = () => {
				$( document ).off( 'wp-plugin-install-success', onSuccess );
				$( document ).off( 'wp-plugin-install-error', onError );
				$( document ).off( 'credential-modal-cancel', onCancel );
			};

			/**
			 * @param {unknown}        _event
			 * @param {{slug: string}} response
			 */
			const onSuccess = ( _event, response ) => {
				if ( response.slug !== slug ) {
					return;
				}
				cleanup();
				resolve( 'installed' );
			};

			/**
			 * @param {unknown}                               _event
			 * @param {{slug: string, errorMessage?: string}} response
			 */
			const onError = ( _event, response ) => {
				if ( response.slug !== slug ) {
					return;
				}
				cleanup();
				reject(
					new Error( response.errorMessage || 'Install failed.' )
				);
			};

			const onCancel = () => {
				cleanup();
				resolve( 'canceled' );
			};

			$( document ).on( 'wp-plugin-install-success', onSuccess );
			$( document ).on( 'wp-plugin-install-error', onError );
			$( document ).on( 'credential-modal-cancel', onCancel );

			updates.installPlugin( { slug } );
		} );
	}

	/**
	 * Enqueues plugin activation requests and starts processing if not already in progress.
	 *
	 * @param {MouseEvent} event - The click event object.
	 */
	function enqueuePluginActivation( event ) {
		// Prevent the default link behavior.
		event.preventDefault();

		const target = /** @type {HTMLElement} */ ( event.target );

		if (
			target.classList.contains( 'updating-message' ) ||
			target.classList.contains( 'disabled' )
		) {
			return;
		}

		target.classList.add( 'updating-message' );
		target.textContent = __( 'Waiting…', 'performance-lab' );

		const pluginSlug = /** @type {string} */ ( target.dataset.pluginSlug );
		activationQueue.push( { target, pluginSlug } );

		// Start processing the queue if not already doing so.
		if ( ! isProcessingActivation ) {
			handlePluginActivation();
		}
	}

	/**
	 * Handles activation of feature/plugin using queue based approach.
	 *
	 * @return {Promise<void>} The asynchronous function returns a promise that resolves to void.
	 */
	async function handlePluginActivation() {
		const activationItem = activationQueue.shift();
		if ( ! activationItem ) {
			isProcessingActivation = false;
			return;
		}

		isProcessingActivation = true;

		const { target, pluginSlug } = activationItem;

		target.textContent = __( 'Activating…', 'performance-lab' );

		a11y.speak( __( 'Activating…', 'performance-lab' ) );

		try {
			// When the filesystem method is not 'direct' and credentials
			// have not been stored, the REST endpoint silently fails to
			// install the plugin. Route the install through wp.updates so
			// the standard "Connection Information" modal handles credential
			// entry, then let the REST endpoint perform the activation step
			// (which no longer needs filesystem access).
			if ( filesystemCredentialsRequired ) {
				const outcome = await installPluginViaWpUpdates( pluginSlug );
				if ( 'canceled' === outcome ) {
					target.classList.remove( 'updating-message' );
					target.textContent = __( 'Activate', 'performance-lab' );
					return;
				}
			}

			// Activate the plugin/feature via the REST API.
			await apiFetch( {
				path: `/performance-lab/v1/features/${ pluginSlug }:activate`,
				method: 'POST',
			} );

			// Fetch the plugin/feature information via the REST API.
			/** @type {{settingsUrl: string|null}} */
			const featureInfo = await apiFetch( {
				path: `/performance-lab/v1/features/${ pluginSlug }`,
				method: 'GET',
			} );

			if ( featureInfo.settingsUrl ) {
				const actionButtonList = document.querySelector(
					`.plugin-card-${ pluginSlug } .plugin-action-buttons`
				);

				const listItem = document.createElement( 'li' );
				const anchor = document.createElement( 'a' );

				anchor.href = featureInfo.settingsUrl;
				anchor.textContent = __( 'Settings', 'performance-lab' );

				listItem.appendChild( anchor );
				actionButtonList?.appendChild( listItem );
			}

			a11y.speak( __( 'Plugin activated.', 'performance-lab' ) );

			target.textContent = __( 'Active', 'performance-lab' );
			target.classList.remove( 'updating-message' );
			target.classList.add( 'disabled' );
		} catch {
			a11y.speak( __( 'Plugin failed to activate.', 'performance-lab' ) );

			target.classList.remove( 'updating-message' );
			target.textContent = __( 'Activate', 'performance-lab' );
		} finally {
			handlePluginActivation();
		}
	}

	// Attach the event listeners.
	document
		.querySelectorAll( '.perflab-install-active-plugin[data-plugin-slug]' )
		.forEach( ( item ) => {
			item.addEventListener( 'click', ( event ) =>
				enqueuePluginActivation( /** @type {MouseEvent} */ ( event ) )
			);
		} );
} )();
