/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( QUnit, valueview, wb ) {
	'use strict';

	var testExpert = valueview.tests.testExpert;

	QUnit.module( 'wikibase.experts.Entity' );

	testExpert( {
		expertConstructor: wb.experts.Entity
	} );

}( QUnit, jQuery.valueview, wikibase ) );
