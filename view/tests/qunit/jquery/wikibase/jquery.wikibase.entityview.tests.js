/**
 * @license GPL-2.0-or-later
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
( function ( wb ) {
	'use strict';

	QUnit.module( 'jquery.wikibase.entityview' );

	QUnit.test( 'Direct initialization fails', function ( assert ) {
		assert.throws(
			function () {
				$( '<div/>' ).entityview( $.extend( {
					value: new wb.datamodel.Property( 'P1', 'someDataType' ),
					languages: 'en'
				} ) );
			},
			'Throwing error when trying to initialize widget directly.'
		);
	} );

}( wikibase ) );
