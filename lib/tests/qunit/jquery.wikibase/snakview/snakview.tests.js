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

var snakSerializer = new wb.serialization.SnakSerializer(),
	snakDeserializer = new wb.serialization.SnakDeserializer();

/**
 * @param {Object} [options={}]
 * @param {jQuery} [$node]
 * @return {jQuery}
 */
var createSnakview = function( options, $node ) {
	options = $.extend( {
		autoStartEditing: false,
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

	assert.ok(
		snakview.snak().equals( snakDeserializer.deserialize( newValue ) ),
		'Verified Snak object returned by snak().'
	);

	newValue = new wb.datamodel.PropertyNoValueSnak( 'P1' );

	snakview.value( newValue );

	assert.deepEqual(
		snakview.value(),
		snakSerializer.serialize( newValue ),
		'Set wikibase.datamodel.Snak value.'
	);

	assert.ok(
		snakview.snak().equals( newValue ),
		'Verified Snak object returned by snak().'
	);

	newValue = {
		snaktype: wb.datamodel.PropertyValueSnak.TYPE
	};

	snakview.value( newValue );

	assert.deepEqual(
		snakview.value(),
		newValue,
		'Set incomplete Snak serialization value.'
	);

	assert.strictEqual(
		snakview.snak(),
		null,
		'Verified snak() returning "null".'
	);
} );

QUnit.test( 'snak()', function( assert ) {
	var $snakview = createSnakview(),
		snakview = $snakview.data( 'snakview' );

	assert.strictEqual(
		snakview.snak(),
		null,
		'Returning "null" since default value is an incomplete serialization.'
	);

	var snak = new wb.datamodel.PropertySomeValueSnak( 'P1' );

	snakview.snak( snak );

	assert.ok(
		snakview.snak().equals( snak ),
		'Set Snak value.'
	);

	assert.deepEqual(
		snakview.value(),
		snakSerializer.serialize( snak ),
		'Verified serialization returned by value().'
	);

	snakview.snak( null );

	assert.strictEqual(
		snakview.snak(),
		null,
		'Reset value by passing "null" to snak().'
	);

	assert.deepEqual(
		snakview.value(),
		{},
		'Verified serialization returned by value().'
	);
} );

QUnit.test( 'propertyId()', function( assert ) {
	var $snakview = createSnakview(),
		snakview = $snakview.data( 'snakview' );

	assert.strictEqual(
		snakview.propertyId(),
		null,
		'By default, the Property ID is "null".'
	);

	snakview.propertyId( 'P1' );

	assert.equal(
		snakview.propertyId(),
		'P1',
		'Set Property ID.'
	);

	snakview.propertyId( null );

	assert.strictEqual(
		snakview.propertyId(),
		null,
		'Reset Property ID.'
	);

	snakview.snak( new wb.datamodel.PropertyNoValueSnak( 'P1' ) );

	assert.equal(
		snakview.propertyId(),
		'P1',
		'Property ID is updated when setting a Snak.'
	);

	snakview.propertyId( 'P2' );

	assert.ok(
		snakview.snak().equals( new wb.datamodel.PropertyNoValueSnak( 'P2' ) ),
		'Updated Property ID of Snak.'
	);
} );

QUnit.test( 'snakType()', function( assert ) {
	var $snakview = createSnakview(),
		snakview = $snakview.data( 'snakview' );

	assert.strictEqual(
		snakview.snakType(),
		'value',
		'By default, the Snak type is "value".'
	);

	snakview.snakType( 'novalue' );

	assert.equal(
		snakview.snakType(),
		'novalue',
		'Set Snak type.'
	);

	snakview.snakType( null );

	assert.strictEqual(
		snakview.snakType(),
		null,
		'Reset Snak type.'
	);

	snakview.snak( new wb.datamodel.PropertySomeValueSnak( 'P1' ) );

	assert.equal(
		snakview.snakType(),
		'somevalue',
		'Snak type is updated when setting a Snak.'
	);

	snakview.snakType( 'novalue' );

	assert.ok(
		snakview.snak().equals( new wb.datamodel.PropertyNoValueSnak( 'P1' ) ),
		'Updated Snak type of Snak.'
	);
} );

QUnit.test( 'isInitialValue()', function( assert ) {
	var $snakview = createSnakview(),
		snakview = $snakview.data( 'snakview' );

	assert.ok(
		snakview.isInitialValue(),
		'Verified returning TRUE after default initialization.'
	);

	// Simulate change of value by overwriting output of value():
	snakview.value = function() {
		return $.extend( this.options.value, {
			snaktype: 'novalue'
		} );
	};

	assert.ok(
		snakview.isInitialValue(),
		'No proper Snak currently and on initialization is regarded FALSE.'
	);

	snakview.value = function() {
		return snakSerializer.serialize( new wb.datamodel.PropertyNoValueSnak( 'P1' ) );
	};

	assert.ok(
		!snakview.isInitialValue(),
		'Returning FALSE after setting a proper Snak.'
	);

	$snakview = createSnakview( {
		value: new wb.datamodel.PropertyNoValueSnak( 'P1' )
	} );
	snakview = $snakview.data( 'snakview' );

	assert.ok(
		snakview.isInitialValue(),
		'Verified returning TRUE after initialization with a proper Snak object.'
	);

	snakview.value = function() {
		var value = this.options.value;
		delete value.propertyId;
		return value;
	};

	assert.ok(
		!snakview.isInitialValue(),
		'Returning FALSE after breaking serialization.'
	);

	snakview.value = function() {
		return snakSerializer.serialize( new wb.datamodel.PropertySomeValueSnak( 'P1' ) );
	};

	assert.ok(
		!snakview.isInitialValue(),
		'Returning FALSE after setting another Snak.'
	);

	snakview.value = function() {
		return snakSerializer.serialize( new wb.datamodel.PropertyNoValueSnak( 'P1' ) );
	};

	assert.ok(
		snakview.isInitialValue(),
		'Returning TRUE after resetting to initial Snak.'
	);
} );

}( jQuery, QUnit, wikibase, dataTypes, mediaWiki ) );
