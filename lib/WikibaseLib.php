<?php

/**
 * Initialization file for the WikibaseLib extension.
 *
 * Documentation:	 		https://www.mediawiki.org/wiki/Extension:WikibaseLib
 * Support					https://www.mediawiki.org/wiki/Extension_talk:WikibaseLib
 * Source code:				https://gerrit.wikimedia.org/r/gitweb?p=mediawiki/extensions/WikibaseLib.git
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

/**
 * This documentation group collects source code files belonging to Wikibase.
 *
 * @defgroup Wikibase Wikibase
 */

/**
 * This documentation group collects source code files belonging to WikibaseLib.
 *
 * @defgroup WikibaseLib WikibaseLib
 * @ingroup Wikibase
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

if ( version_compare( $GLOBALS['wgVersion'], '1.20c', '<' ) ) { // Needs to be 1.20c because version_compare() works in confusing ways.
	die( '<b>Error:</b> WikibaseLib requires MediaWiki 1.20 or above.' );
}

if ( defined( 'WBL_VERSION' ) ) {
	// Do not initialize more then once.
	return;
}

define( 'WBL_VERSION', '0.4 alpha'
	. ( defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES ? '/experimental' : '' ) );

// This is the path to the autoloader generated by composer in case of a composer install.
if ( ( !defined( 'WIKIBASE_DATAMODEL_VERSION' ) || !defined( 'Diff_VERSION' ) || !defined( 'DataValues_VERSION' ) )
	&& file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
	include_once( __DIR__ . '/../vendor/autoload.php' );
}

// Include the WikibaseDataModel component if that hasn't been done yet.
if ( !defined( 'WIKIBASE_DATAMODEL_VERSION' ) ) {
	@include_once( __DIR__ . '/../../WikibaseDataModel/WikibaseDataModel.php' );
}

// Include the Diff extension if that hasn't been done yet.
if ( !defined( 'Diff_VERSION' ) ) {
	@include_once( __DIR__ . '/../../Diff/Diff.php' );
}

// Include the DataValues extension if that hasn't been done yet.
if ( !defined( 'DataValues_VERSION' ) ) {
	@include_once( __DIR__ . '/../../DataValues/DataValues.php' );
}

// Include the ValueParsers extension if that hasn't been done yet.
if ( !defined( 'ValueParsers_VERSION' ) ) {
	@include_once( __DIR__ . '/../../ValueParsers/ValueParsers.php' );
}

// Include the ValueFormatters extension if that hasn't been done yet.
if ( !defined( 'ValueFormatters_VERSION' ) ) {
	@include_once( __DIR__ . '/../../ValueFormatters/ValueFormatters.php' );
}

// Include the ValueValidators extension if that hasn't been done yet.
if ( !defined( 'ValueValidators_VERSION' ) ) {
	@include_once( __DIR__ . '/../../ValueValidators/ValueValidators.php' );
}

// Include the ValueView extension if that hasn't been done yet.
if ( !defined( 'ValueView_VERSION' ) ) {
	@include_once( __DIR__ . '/../../ValueView/ValueView.php' );
}

// Include the DataTypes extension if that hasn't been done yet.
if ( !defined( 'DataTypes_VERSION' ) ) {
	@include_once( __DIR__ . '/../../DataTypes/DataTypes.php' );
}

$dependencies = array(
	'WIKIBASE_DATAMODEL_VERSION' => 'Wikibase DataModel',
	'Diff_VERSION' => 'Diff',
	'DataValues_VERSION' => 'DataValues',
	'ValueParsers_VERSION' => 'ValueParsers',
	'ValueFormatters_VERSION' => 'ValueFormatters',
	'ValueValidators_VERSION' => 'ValueValidators',
	'DataTypes_VERSION' => 'DataTypes',
	'ValueView_VERSION' => 'ValueView',
);

foreach ( $dependencies as $constant => $name ) {
	if ( !defined( $constant ) ) {
		throw new Exception( 'WikibaseLib depends on the ' . $name . ' extension.' );
	}
}

unset( $dependencies );

call_user_func( function() {
	global $wgExtensionCredits, $wgAutoloadClasses, $wgExtensionMessagesFiles, $wgExtensionFunctions;
	global $wgValueParsers, $wgDataValues, $wgJobClasses, $wgHooks, $wgResourceModules, $wgValueFormatters;

	$wgExtensionCredits['wikibase'][] = array(
		'path' => __DIR__,
		'name' => 'WikibaseLib',
		'version' => WBL_VERSION,
		'author' => array(
			'The Wikidata team', // TODO: link?
		),
		'url' => 'https://www.mediawiki.org/wiki/Extension:WikibaseLib',
		'descriptionmsg' => 'wikibase-lib-desc'
	);

	foreach ( include( __DIR__ . '/WikibaseLib.classes.php' ) as $class => $file ) {
		$wgAutoloadClasses[$class] = __DIR__ . '/' . $file;
	}

	define( 'SUMMARY_MAX_LENGTH', 250 );

	// i18n
	$wgExtensionMessagesFiles['WikibaseLib'] = __DIR__ . '/WikibaseLib.i18n.php';

	$wgValueParsers['wikibase-entityid'] = 'Wikibase\Lib\EntityIdParser';
	$wgDataValues['wikibase-entityid'] = 'Wikibase\DataModel\Entity\EntityIdValue';
	$wgJobClasses['ChangeNotification'] = 'Wikibase\ChangeNotificationJob';
	$wgJobClasses['UpdateRepoOnMove'] = 'Wikibase\UpdateRepoOnMoveJob';

	// Hooks
	$wgHooks['UnitTestsList'][]							= 'Wikibase\LibHooks::registerPhpUnitTests';
	$wgHooks['ResourceLoaderTestModules'][]				= 'Wikibase\LibHooks::registerQUnitTests';

	/**
	 * Called when generating the extensions credits, use this to change the tables headers.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ExtensionTypes
	 *
	 * @since 0.1
	 *
	 * @param array &$extensionTypes
	 *
	 * @return boolean
	 */
	$wgHooks['ExtensionTypes'][] = function( array &$extensionTypes ) {
		// @codeCoverageIgnoreStart
		$extensionTypes['wikibase'] = wfMessage( 'version-wikibase' )->text();

		return true;
		// @codeCoverageIgnoreEnd
	};

	/**
	 * Called when setup is done. This is somewhat ugly, find a better time to register templates.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SetupAfterCache
	 *
	 * @return bool
	 */
	$wgHooks['SetupAfterCache'][] = function() {
		\Wikibase\TemplateRegistry::singleton()->addTemplates( include( __DIR__ . "/resources/templates.php" ) );
		return true;
	};

	/**
	 * Shorthand function to retrieve a template filled with the specified parameters.
	 *
	 * important! note that the Template class does not escape anything.
	 * be sure to escape your params before using this function!
	 *
	 * @since 0.2
	 *
	 * @param $key string template key
	 * Varargs: normal template parameters
	 *
	 * @return string
	 */
	function wfTemplate( $key /*...*/ ) {
		$params = func_get_args();
		array_shift( $params );

		if ( isset( $params[0] ) && is_array( $params[0] ) ) {
			$params = $params[0];
		}

		$template = new \Wikibase\Template( \Wikibase\TemplateRegistry::singleton(), $key, $params );

		return $template->render();
	}

	// Resource Loader Modules:
	$wgResourceModules = array_merge( $wgResourceModules, include( __DIR__ . "/resources/Resources.php" ) );

	$wgValueFormatters['wikibase-entityid'] = 'Wikibase\Lib\EntityIdFormatter';

	if ( defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES ) {
		include_once( __DIR__ . '/config/WikibaseLib.experimental.php' );
	}
} );

