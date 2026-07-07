const fs = require( 'fs' );
const path = require( 'path' );
const { chdir } = require( 'process' );
const { spawnSync } = require( 'child_process' );
const CssMinimizerPlugin = require( 'css-minimizer-webpack-plugin' );

/**
 * Return plugin root path.
 *
 * @return {string} The plugin root path.
 */
const getPluginRootPath = () => {
	return path.resolve( __dirname, '../../' );
};

/**
 * Delete a file or directory.
 *
 * @param {string} _path The path to the file or directory.
 *
 * @return {void}
 */
const deleteFileOrDirectory = ( _path ) => {
	if ( fs.existsSync( _path ) ) {
		fs.rmSync( _path, { recursive: true } );
	}
};

/**
 * Determine the plugin version from the readme.txt file.
 *
 * @param {string} pluginPath The path to the plugin.
 *
 * @return {string|false} The plugin version or false if not found.
 */
const getPluginVersion = ( pluginPath ) => {
	const readmePath = path.resolve( pluginPath, 'readme.txt' );

	const fileContent = fs.readFileSync( readmePath, 'utf-8' );
	const versionRegex = /(?:Stable tag|v)\s*:\s*(\d+\.\d+\.\d+(?:-[\w\.]+)?)/i;
	const match = versionRegex.exec( fileContent );

	if ( match ) {
		return match[ 1 ];
	}

	return false;
};

/**
 * Generate build manifest for the plugin.
 *
 * @param {string} slug The plugin slug.
 * @param {string} from The path to the plugin.
 *
 * @return {void}
 */
const generateBuildManifest = ( slug, from ) => {
	const version = getPluginVersion( from );

	if ( ! version ) {
		throw new Error( `Plugin version not found for "${ slug }".` );
	}

	const buildDir = path.resolve( getPluginRootPath(), 'build' );

	if ( ! fs.existsSync( buildDir ) ) {
		fs.mkdirSync( buildDir );
	}

	/** @type {Record<string, string>} */
	let manifest = {};
	const manifestPath = path.resolve( buildDir, 'manifest.json' );

	if ( fs.existsSync( manifestPath ) ) {
		manifest = require( manifestPath );
	}

	manifest[ slug ] = version;

	fs.writeFileSync( manifestPath, JSON.stringify( manifest, null, 2 ) );
};

/**
 * Transformer to get version from package.json and return it as a PHP file.
 *
 * @param {Buffer} content      The content as a Buffer of the file being transformed.
 * @param {string} absoluteFrom The absolute path to the file being transformed.
 *
 * @return {Buffer|string} The transformed content.
 */
const assetDataTransformer = ( content, absoluteFrom ) => {
	if ( 'package.json' !== path.basename( absoluteFrom ) ) {
		return content;
	}

	const contentAsString = content.toString();
	const contentAsJson = JSON.parse( contentAsString );
	const { version } = contentAsJson;

	return `<?php return array('dependencies' => array(), 'version' => '${ version }');`;
};

/**
 * Transformer to minify CSS content.
 *
 * @param {Buffer} content      The content as a Buffer of the file being transformed.
 * @param {string} absoluteFrom The absolute path to the file being transformed.
 *
 * @return {Promise<string>} A promise that resolves to the transformed (minified) content.
 */
const cssMinifyTransformer = ( content, absoluteFrom ) => {
	const cssContent = content.toString();

	return Promise.resolve(
		CssMinimizerPlugin.cssnanoMinify(
			{ [ absoluteFrom ]: cssContent },
			undefined,
			{
				preset: [
					'default',
					{
						discardComments: {
							removeAll: true,
						},
					},
				],
			}
		)
	).then( ( result ) => {
		return result.code;
	} );
};

/**
 * Compute a content fingerprint for a zip archive, ignoring entry timestamps.
 *
 * Uses `unzip -v`, which lists each entry's uncompressed size, CRC-32 checksum,
 * and name. The date and time columns are omitted so that two archives with
 * identical contents produce the same fingerprint even when built at different
 * times.
 *
 * @param {string} zipPath The path to the zip file.
 *
 * @return {string|null} The fingerprint, or null if the archive does not exist.
 */
const getZipContentFingerprint = ( zipPath ) => {
	if ( ! fs.existsSync( zipPath ) ) {
		return null;
	}

	const proc = spawnSync( 'unzip', [ '-v', zipPath ] );

	if ( 0 !== proc.status ) {
		if ( proc.error ) {
			throw proc.error;
		} else {
			throw new Error( proc.stderr.toString() || proc.stdout.toString() );
		}
	}

	const lineRegex =
		/^\s*(\d+)\s+\S+\s+\d+\s+\S+\s+\d\d-\d\d-\d\d\d\d\s+\d\d:\d\d\s+([0-9a-f]{8})\s+(.*)$/;

	return proc.stdout
		.toString()
		.split( '\n' )
		.map( ( line ) => {
			const match = lineRegex.exec( line );
			return match
				? `${ match[ 1 ] }:${ match[ 2 ] }:${ match[ 3 ] }`
				: null;
		} )
		.filter( Boolean )
		.join( '\n' );
};

/**
 * Create plugins zip file using `zip` command.
 *
 * The archive is built into a temporary file first and only replaces the
 * existing zip when its contents actually change. This leaves the existing
 * file (and its modified time) untouched when a rebuild produces identical
 * output. Building fresh each time also ensures files removed from the plugin
 * do not linger in an updated-in-place archive.
 *
 * @param {string} pluginPath The path where the plugin build is located.
 * @param {string} pluginName The name of the plugin.
 *
 * @return {void}
 */
const createPluginZip = ( pluginPath, pluginName ) => {
	chdir( pluginPath );

	const zipFile = `${ pluginName }.zip`;
	const tempZipFile = `${ pluginName }.zip.tmp`;

	deleteFileOrDirectory( tempZipFile );

	const proc = spawnSync( 'zip', [ '-r', tempZipFile, pluginName ] );

	if ( 0 !== proc.status ) {
		deleteFileOrDirectory( tempZipFile );
		if ( proc.error ) {
			throw proc.error;
		} else {
			throw new Error( proc.stderr.toString() || proc.stdout.toString() );
		}
	}

	// If the freshly built archive matches the existing one, discard it so the
	// existing file is left untouched.
	if (
		fs.existsSync( zipFile ) &&
		getZipContentFingerprint( zipFile ) ===
			getZipContentFingerprint( tempZipFile )
	) {
		deleteFileOrDirectory( tempZipFile );
		return;
	}

	fs.renameSync( tempZipFile, zipFile );
};

module.exports = {
	getPluginRootPath,
	deleteFileOrDirectory,
	getPluginVersion,
	generateBuildManifest,
	assetDataTransformer,
	cssMinifyTransformer,
	getZipContentFingerprint,
	createPluginZip,
};
