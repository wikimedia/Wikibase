/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( $, QUnit, wb, dt, mw ) {
'use strict';

QUnit.module( 'jquery.wikibase.snakview', QUnit.newMwEnvironment( {
	teardown: function() {
		$( '.test_snakview' ).each( function() {
			var $snakview = $( this ),
				snakview = $snakview.data( 'snakview' );

			if( snakview ) {
				snakview.destroy();
			}

			$snakview.remove();
		} );
	}
} ) );

var entityStore = {
	get: function() {
		return $.Deferred().resolve( new wb.store.FetchedContent( {
			title: new mw.Title( 'Property:P1' ),
			content: new wb.datamodel.Property(
				'P1',
				'string',
				new wb.datamodel.Fingerprint( new wb.datamodel.TermMap( [
					new wb.datamodel.Term( 'en', 'P1' )
				] ) )
			)
		} ) );
	}
};

var snakSerializer = new wb.serialization.SnakSerializer();

/**
 * @param {Object} [options={}]
 * @param {jQuery} [$node]
 * @return {jQuery}
 */
var createSnakview = function( options, $node ) {
	options = $.extend( {
		entityStore: entityStore,
		valueViewBuilder: 'I am a ValueViewBuilder',
		dataTypeStore: new dt.DataTypeStore()
	}, options || {} );

	$node = $node || $( '<div/>' ).appendTo( 'body' );

	return $node
		.addClass( 'test_snakview' )
		.snakview( options );
};

QUnit.test( 'Create & destroy', function( assert ) {
	var $snakview = createSnakview(),
		snakview = $snakview.data( 'snakview' );

	assert.ok(
		snakview instanceof $.wikibase.snakview,
		'Created widget.'
	);

	snakview.destroy();

	assert.ok(
		$snakview.data( 'snakview' ) === undefined,
		'Destroyed widget.'
	);

	$snakview = createSnakview( {
		value: new wb.datamodel.PropertyNoValueSnak( 'P1' )
	} );
	snakview = $snakview.data( 'snakview' );

	assert.ok(
		snakview !== undefined,
		'Created widget passing a wikibase.datamodel.Snak object.'
	);

	snakview.destroy();

	assert.ok(
		$snakview.data( 'snakview' ) === undefined,
		'Destroyed widget.'
	);

	$snakview = createSnakview( {
		value: snakSerializer.serialize( new wb.datamodel.PropertyNoValueSnak( 'P1' ) )
	} );
	snakview = $snakview.data( 'snakview' );

	assert.ok(
		snakview !== undefined,
		'Created widget passing a Snak serialization.'
	);

	snakview.destroy();

	assert.ok(
		$snakview.data( 'snakview' ) === undefined,
		'Destroyed widget.'
	);
} );

QUnit.test( 'value()', function( assert ) {
	var $snakview = createSnakview(),
		snakview = $snakview.data( 'snakview' );

	assert.deepEqual(
		snakview.value(),
		{
			property: null,
			snaktype: wb.datamodel.PropertyValueSnak.TYPE
		},
		'Verified default value.'
	);

	var newValue = {
		property: 'P1',
		snaktype: wb.datamodel.PropertySomeValueSnak.TYPE
	};

	snakview.value( newValue );

	assert.deepEqual(
		snakview.value(),
		newValue,
		'Set Snak serialization value.'
	);

	newValue = new wb.datamodel.PropertyNoValueSnak( 'P1' );

	snakview.value( newValue );

	assert.deepEqual(
		snakview.value(),
		snakSerializer.serialize( newValue ),
		'Set wikibase.datamodel.Snak value.'
	);

	newValue = {
		property: 'P1',
		snaktype: wb.datamodel.PropertyValueSnak.TYPE
	};

	snakview.value( newValue );

	assert.deepEqual(
		snakview.value(),
		newValue,
		'Set incomplete Snak serialization value.'
	);
} );

}( jQuery, QUnit, wikibase, dataTypes, mediaWiki ) );
