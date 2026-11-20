/**
 * Compares two semantic version strings with optional prerelease tags.
 *
 * @param {string} v1 First version string.
 * @param {string} v2 Second version string.
 * @return {number} 1 if v1 > v2, -1 if v1 < v2, 0 if v1 === v2.
 */
function compareVersions( v1, v2 ) {
	if ( v1 === v2 ) {
		return 0;
	}

	/**
	 * Parses a version string.
	 *
	 * @param {string} version Version string.
	 * @return {{major: number, minor: number, patch: number, prerelease: string|null}|null} Parsed version or null.
	 */
	const parseVersion = ( version ) => {
		const match = version.match( /^(\d+)\.(\d+)\.(\d+)(?:-([\w.]+))?$/ );
		if ( ! match ) {
			return null;
		}
		return {
			major: parseInt( match[ 1 ], 10 ),
			minor: parseInt( match[ 2 ], 10 ),
			patch: parseInt( match[ 3 ], 10 ),
			prerelease: match[ 4 ] || null,
		};
	};

	const parsed1 = parseVersion( v1 );
	const parsed2 = parseVersion( v2 );

	// Fallback to numeric-aware string comparison if not conforming to X.Y.Z(-prerelease).
	if ( ! parsed1 || ! parsed2 ) {
		return v1.localeCompare( v2, undefined, { numeric: true } );
	}

	if ( parsed1.major !== parsed2.major ) {
		return parsed1.major > parsed2.major ? 1 : -1;
	}
	if ( parsed1.minor !== parsed2.minor ) {
		return parsed1.minor > parsed2.minor ? 1 : -1;
	}
	if ( parsed1.patch !== parsed2.patch ) {
		return parsed1.patch > parsed2.patch ? 1 : -1;
	}

	// If one has a prerelease and the other doesn't, the one without is greater.
	if ( parsed1.prerelease && ! parsed2.prerelease ) {
		return -1;
	}
	if ( ! parsed1.prerelease && parsed2.prerelease ) {
		return 1;
	}
	if ( parsed1.prerelease && parsed2.prerelease ) {
		return parsed1.prerelease.localeCompare(
			parsed2.prerelease,
			undefined,
			{
				numeric: true,
			}
		);
	}

	return 0;
}

/**
 * Fetches plugin metadata from WordPress.org Plugins API.
 *
 * @param {string} pluginSlug Plugin slug.
 * @return {Promise<{version?: string, versions?: Record<string, string>}|null>} Plugin data or null if not found.
 */
async function getWpOrgPluginData( pluginSlug ) {
	try {
		const response = await fetch(
			`https://api.wordpress.org/plugins/info/1.0/${ pluginSlug }.json`
		);
		if ( response.status === 404 ) {
			return null;
		}
		if ( ! response.ok ) {
			throw new Error(
				`Failed to fetch WordPress.org data for ${ pluginSlug }: ${ response.status } ${ response.statusText }`
			);
		}
		const data = await response.json();
		if ( data && data.error ) {
			return null;
		}
		return data;
	} catch ( error ) {
		if (
			error instanceof Error &&
			error.message.includes( 'Failed to fetch WordPress.org data' )
		) {
			throw error;
		}
		// In case of network errors, rethrow to avoid silent false negatives.
		throw new Error(
			`Could not reach WordPress.org API to check ${ pluginSlug }: ${
				error instanceof Error ? error.message : 'Unknown error'
			}`
		);
	}
}

/**
 * Checks whether a given plugin version is already released on WordPress.org.
 *
 * @param {string} pluginSlug Plugin slug.
 * @param {string} version    Version string to check.
 * @return {Promise<boolean>} True if the version is already released, false otherwise.
 */
async function isVersionReleased( pluginSlug, version ) {
	const data = await getWpOrgPluginData( pluginSlug );
	if ( ! data ) {
		return false;
	}

	if (
		data.versions &&
		Object.prototype.hasOwnProperty.call( data.versions, version )
	) {
		return true;
	}

	if ( data.version && compareVersions( version, data.version ) <= 0 ) {
		return true;
	}

	return false;
}

module.exports = {
	compareVersions,
	getWpOrgPluginData,
	isVersionReleased,
};
