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
var createFingerprintgroupview = function( options ) {
	options = $.extend( {
		value: [
			{
				language: 'de',
				label: new wb.datamodel.Term( 'de', 'de-label' ),
				description: new wb.datamodel.Term( 'de', 'de-description' ),
				aliases: new wb.datamodel.MultiTerm( 'de', [] )
			}, {
				language: 'en',
				label: new wb.datamodel.Term( 'en', 'en-label' ),
				description: new wb.datamodel.Term( 'en', 'en-description' ),
				aliases: new wb.datamodel.MultiTerm( 'en', [] )
			}
		],
		entityId: 'i am an EntityId',
		entityChangersFactory: {
			getAliasesChanger: function() { return 'i am an AliasesChanger'; },
			getDescriptionsChanger: function() { return 'i am a DescriptionsChanger'; },
			getLabelsChanger: function() { return 'i am a LabelsChanger'; }
		}
	}, options || {} );

	return $( '<div/>' )
		.appendTo( 'body' )
		.addClass( 'test_fingerprintgroupview' )
		.fingerprintgroupview( options );
};

QUnit.module( 'jquery.wikibase.fingerprintgroupview', QUnit.newMwEnvironment( {
	teardown: function() {
		$( '.test_fingerprintgroupview' ).each( function() {
			var $fingerprintgroupview = $( this ),
				fingerprintgroupview = $fingerprintgroupview.data( 'fingerprintgroupview' );

			if( fingerprintgroupview ) {
				fingerprintgroupview.destroy();
			}

			$fingerprintgroupview.remove();
		} );
	}
} ) );

QUnit.test( 'Create & destroy', function( assert ) {
	assert.throws(
		function() {
			createFingerprintgroupview( { value: null } );
		},
		'Throwing error when trying to initialize widget without a value.'
	);

	var $fingerprintgroupview = createFingerprintgroupview(),
		fingerprintgroupview = $fingerprintgroupview.data( 'fingerprintgroupview' );

	assert.ok(
		fingerprintgroupview !== undefined,
		'Created widget.'
	);

	fingerprintgroupview.destroy();

	assert.ok(
		$fingerprintgroupview.data( 'fingerprintgroupview' ) === undefined,
		'Destroyed widget.'
	);
} );

QUnit.test( 'setError()', function( assert ) {
	var $fingerprintgroupview = createFingerprintgroupview(),
		fingerprintgroupview = $fingerprintgroupview.data( 'fingerprintgroupview' );

	$fingerprintgroupview
	.on( 'fingerprintgroupviewtoggleerror', function( event, error ) {
		assert.ok(
			true,
			'Triggered "toggleerror" event.'
		);
	} );

	fingerprintgroupview.setError();
} );

QUnit.test( 'value()', function( assert ) {
	var $fingerprintgroupview = createFingerprintgroupview(),
		fingerprintgroupview = $fingerprintgroupview.data( 'fingerprintgroupview' );

	// TODO: Enhance test as soon as SiteLinkList is implemented in DataModelJavaScript
	assert.equal(
		fingerprintgroupview.value().length,
		2,
		'Retrieved value.'
	);

	assert.throws(
		function() {
			fingerprintgroupview.value( [] );
		},
		'Throwing error when trying to set a new value.'
	);
} );

}( jQuery, wikibase, QUnit ) );
