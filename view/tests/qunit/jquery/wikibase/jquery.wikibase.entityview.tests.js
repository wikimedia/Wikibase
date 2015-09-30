/**
 * @licence GNU GPL v2+
 * @author Adrian Lang <adrian.lang@wikimedia.de>
 */
( function( $, wb, QUnit ) {
'use strict';

QUnit.module( 'jquery.wikibase.entityview' );

QUnit.test( 'Direct initialization fails', function( assert ) {
	assert.throws(
		function() {
			$( '<div/>' ).entityview( $.extend( {
				value: new wb.datamodel.Property( 'P1', 'someDataType' ),
				languages: 'en'
			} ) );
		},
		'Throwing error when trying to initialize widget directly.'
	);
} );

}( jQuery, wikibase, QUnit ) );
