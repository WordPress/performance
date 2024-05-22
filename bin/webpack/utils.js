const fs = require( 'fs' );
const path = require( 'path' );
const { execSync } = require( 'child_process' );

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
	const versionRegex = /(?:Stable tag|v)\s*:\s*(\d+\.\d+\.\d+)/i;
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
 * Create plugins zip file using `zip` command.
 *
 * @param {string} pluginPath The path where the plugin build is located.
 * @param {string} pluginName The name of the plugin.
 *
 * @return {void}
 */
const createPluginZip = ( pluginPath, pluginName ) => {
	const command = `cd ${ pluginPath } && zip -r ${ pluginName }.zip ${ pluginName }`;
	execSync( command );
};

module.exports = {
	getPluginRootPath,
	deleteFileOrDirectory,
	getPluginVersion,
	generateBuildManifest,
	assetDataTransformer,
	createPluginZip,
};
