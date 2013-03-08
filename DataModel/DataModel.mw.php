<?php

/**
 * MediaWiki setup for the DataValues component of Wikibase.
 * The component should be included via the main entry point, DataValues.php.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.4
 *
 * @file
 * @ingroup WikibaseDataModel
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

global $wgExtensionCredits, $wgExtensionMessagesFiles, $wgAutoloadClasses, $wgHooks;

//$wgExtensionCredits['other'][] = include( __DIR__ . '/DataModel.credits.php' );

//$wgExtensionMessagesFiles['WikibaseDataModel'] = __DIR__ . '/DataModel.i18n.php';

// Autoloading
foreach ( include( __DIR__ . '/DataModel.classes.php' ) as $class => $file ) {
	$wgAutoloadClasses[$class] = __DIR__ . '/' . $file;
}

if ( defined( 'MW_PHPUNIT_TEST' ) ) {
//	$wgAutoloadClasses['Ask\Tests\AskTestCase']
//		= __DIR__ . '/tests/phpunit/AskTestCase.php';
}

/**
 * Hook to add PHPUnit test cases.
 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
 *
 * @since 0.4
 *
 * @param array $files
 *
 * @return boolean
 */
$wgHooks['UnitTestsList'][]	= function( array &$files ) {
	// @codeCoverageIgnoreStart
	$testFiles = array(
		'Language/Description/AnyValue',
		'Language/Description/Conjunction',
		'Language/Description/SomeProperty',
		'Language/Description/Disjunction',
		'Language/Description/ValueDescription',

		'Language/Option/PropertyValueSortExpression',
		'Language/Option/QueryOptions',
		'Language/Option/SortOptions',

		'Language/Selection/PropertySelection',
		'Language/Selection/SubjectSelection',

		'Language/Query',
	);

	foreach ( $testFiles as $file ) {
		$files[] = __DIR__ . '/tests/phpunit/' . $file . 'Test.php';
	}

	return true;
	// @codeCoverageIgnoreEnd
};
