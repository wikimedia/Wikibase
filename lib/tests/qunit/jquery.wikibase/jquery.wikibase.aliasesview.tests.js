/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */

( function( $, wb, QUnit ) {
'use strict';

/**
 * @param {Object} [options]
 * @return {jQuery}
 */
var createAliasesview = function( options ) {
	options = $.extend( {
		entityId: 'i am an EntityId',
		aliasesChanger: 'i am an AliasesChanger',
		value: new wb.datamodel.MultiTerm( 'en', ['a', 'b', 'c'] )
	}, options || {} );

	var $aliasesview = $( '<div/>' )
		.addClass( 'test_aliasesview' )
		.appendTo( 'body' )
		.aliasesview( options );

	$aliasesview.data( 'aliasesview' )._save = function() {
		return $.Deferred().resolve().promise();
	};

	return $aliasesview;
};

QUnit.module( 'jquery.wikibase.aliasesview', QUnit.newMwEnvironment( {
	teardown: function() {
		$( '.test_aliasesview' ).each( function() {
			var $aliasesview = $( this ),
				aliasesview = $aliasesview.data( 'aliasesview' );

			if( aliasesview ) {
				aliasesview.destroy();
			}

			$aliasesview.remove();
		} );
	}
} ) );

QUnit.test( 'Create & destroy', function( assert ) {
	assert.throws(
		function() {
			createAliasesview( { value: null } );
		},
		'Throwing error when trying to initialize widget without a value.'
	);

	var $aliasesview = createAliasesview(),
		aliasesview = $aliasesview.data( 'aliasesview' );

	assert.ok(
		aliasesview instanceof $.wikibase.aliasesview,
		'Created widget'
	);

	aliasesview.destroy();

	assert.ok(
		$aliasesview.data( 'aliasesview' ) === undefined,
		'Destroyed widget.'
	);
} );

QUnit.test( 'startEditing() & stopEditing()', 7, function( assert ) {
	var $aliasesview = createAliasesview(),
		aliasesview = $aliasesview.data( 'aliasesview' );

	$aliasesview
	.on( 'aliasesviewafterstartediting', function( event ) {
		assert.ok(
			true,
			'Started edit mode.'
		);
	} )
	.on( 'aliasesviewafterstopediting', function( event, dropValue ) {
		assert.ok(
			true,
			'Stopped edit mode.'
		);
	} );

	aliasesview.startEditing();

	assert.ok(
		aliasesview.$list.data( 'tagadata' ) !== undefined,
		'Instantiated tagadata widget.'
	);

	aliasesview.startEditing(); // should not trigger event
	aliasesview.stopEditing( true );
	aliasesview.stopEditing( true ); // should not trigger event
	aliasesview.stopEditing(); // should not trigger event

	aliasesview.startEditing();

	aliasesview.$list.data( 'tagadata' ).getTags().first().find( 'input' ).val( 'b' );

	aliasesview.stopEditing();
	aliasesview.startEditing();

	aliasesview.$list.data( 'tagadata' ).getTags().first().removeClass( 'tagadata-choice-equal' )
		.find( 'input' ).val( 'd' );

	aliasesview.stopEditing();
} );

QUnit.test( 'isInitialValue()', function( assert ) {
	var $aliasesview = createAliasesview(),
		aliasesview = $aliasesview.data( 'aliasesview' );

	aliasesview.startEditing();

	assert.ok(
		aliasesview.isInitialValue(),
		'Verified isInitialValue() returning true.'
	);

	aliasesview.$list.data( 'tagadata' ).getTags().first().find( 'input' ).val( 'changed' );

	assert.ok(
		!aliasesview.isInitialValue(),
		'Verified isInitialValue() returning false after changing value.'
	);

	aliasesview.$list.data( 'tagadata' ).getTags().first().find( 'input' ).val( 'a' );

	assert.ok(
		aliasesview.isInitialValue(),
		'Verified isInitialValue() returning true after resetting to initial value.'
	);
} );

QUnit.test( 'setError()', function( assert ) {
	var $aliasesview = createAliasesview(),
		aliasesview = $aliasesview.data( 'aliasesview' );

	$aliasesview
	.on( 'aliasesviewtoggleerror', function( event, error ) {
		assert.ok(
			true,
			'Triggered "toggleerror" event.'
		);
	} );

	aliasesview.setError();
} );

QUnit.test( 'value()', function( assert ) {
	var $aliasesview = createAliasesview(),
		aliasesview = $aliasesview.data( 'aliasesview' ),
		newValue = null;

	assert.throws(
		function() {
			aliasesview.value( newValue );
		},
		'Trying to set no value fails.'
	);

	newValue = new wb.datamodel.MultiTerm( 'de', ['x', 'y'] );
	aliasesview.value( newValue );

	assert.ok(
		aliasesview.value().equals( newValue ),
		'Set new value.'
	);

	newValue = new wb.datamodel.MultiTerm( 'en', [] );
	aliasesview.value( newValue );

	assert.ok(
		aliasesview.value().equals( newValue ),
		'Set another value.'
	);
} );

}( jQuery, wikibase, QUnit ) );
