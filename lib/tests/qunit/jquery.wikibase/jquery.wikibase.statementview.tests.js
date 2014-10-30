/**
 * @licence GNU GPL v2+
 * @author Adrian Lang <adrian.lang@wikimedia.de>
 */
( function( $, QUnit ) {
'use strict';

/**
 * @param {Object} [options]
 * @param {jQuery} [$node]
 * @return {jQuery}
 */
var createStatementview = function( options, $node ) {
	options = $.extend( {
		entityStore: 'i am an entity store',
		valueViewBuilder: 'i am a valueview builder',
		claimsChanger: 'I am a ClaimsChanger',
		entityChangersFactory: {
			getReferencesChanger: function() {
				return 'I am a ReferencesChanger';
			}
		},
		api: 'i am an api'
	}, options || {} );

	$node = $node || $( '<div/>' ).appendTo( 'body' );

	return $node
		.addClass( 'test_statementview' )
		.statementview( options );
};

QUnit.module( 'jquery.wikibase.statementview', QUnit.newMwEnvironment( {
	teardown: function() {
		$( '.test_statementview' ).each( function() {
			var $statementview = $( this ),
				statementview = $statementview.data( 'statementview' );

			if( statementview ) {
				statementview.destroy();
			}

			$statementview.remove();
		} );
	}
} ) );

QUnit.test( 'Create & destroy', function( assert ) {
	var $statementview = createStatementview(),
		statementview = $statementview.data( 'statementview' );

	assert.ok(
		statementview instanceof $.wikibase.statementview,
		'Created widget.'
	);

	statementview.destroy();

	assert.ok(
		$statementview.data( 'statementview' ) === undefined,
		'Destroyed widget.'
	);
} );

}( jQuery, QUnit ) );
