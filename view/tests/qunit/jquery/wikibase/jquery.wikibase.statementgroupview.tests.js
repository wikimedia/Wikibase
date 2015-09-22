/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( $, wb, QUnit ) {
'use strict';

/**
 * @param {Object} [options={}]
 * @param {jQuery} [$node]
 * @return {jQuery}
 */
var createStatementgroupview = function( options, $node ) {
	options = $.extend( {
		claimGuidGenerator: 'I am a ClaimGuidGenerator',
		entityStore: {
			get: function () {
				return $.Deferred().resolve().promise();
			}
		},
		valueViewBuilder: 'I am a ValueViewBuilder',
		entityChangersFactory: {
			getClaimsChanger: function() {
				return 'I am a ClaimsChanger';
			},
			getReferencesChanger: function() {
				return 'I am a ReferencesChanger';
			}
		},
		dataTypeStore: 'I am a DataTypeStore'
	}, options || {} );

	$node = $node || $( '<div/>' ).appendTo( 'body' );

	return $node
		.addClass( 'test_statementgroupview' )
		.statementgroupview( options );
};

QUnit.module( 'jquery.wikibase.statementgroupview', QUnit.newMwEnvironment( {
	teardown: function() {
		$( '.test_statementgroupview' ).each( function() {
			var $statementgroupview = $( this ),
				statementgroupview = $statementgroupview.data( 'statementgroupview' );

			if( statementgroupview ) {
				statementgroupview.destroy();
			}

			$statementgroupview.remove();
		} );
	}
} ) );

QUnit.test( 'Create & destroy', function( assert ) {
	var $statementgroupview = createStatementgroupview(),
		statementgroupview = $statementgroupview.data( 'statementgroupview' );

	assert.ok(
		statementgroupview instanceof $.wikibase.statementgroupview,
		'Created widget.'
	);

	statementgroupview.destroy();

	assert.ok(
		$statementgroupview.data( 'statementgroupview' ) === undefined,
		'Destroyed widget.'
	);

	$statementgroupview = createStatementgroupview( {
		value: new wb.datamodel.StatementGroup( 'P1', new wb.datamodel.StatementList( [
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P1' ) )
			)
		] ) )
	} );
	statementgroupview = $statementgroupview.data( 'statementgroupview' );

	assert.ok(
		statementgroupview instanceof $.wikibase.statementgroupview,
		'Created widget with filled wb.datamodel.StatementGroup instance.'
	);
} );

QUnit.test( 'value()', function( assert ) {
	var statementGroup1 = new wb.datamodel.StatementGroup( 'P1', new wb.datamodel.StatementList( [
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P1' ) )
			)
		] ) ),
		statementGroup2 = new wb.datamodel.StatementGroup( 'P2', new wb.datamodel.StatementList( [
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P2' ) )
			)
		] ) ),
		$statementgroupview = createStatementgroupview( {
			value: statementGroup1
		} ),
		statementgroupview = $statementgroupview.data( 'statementgroupview' );

	assert.ok(
		statementgroupview.value().equals( statementGroup1 ),
		'Retrieved value.'
	);

	statementgroupview.value( statementGroup2 );

	assert.ok(
		statementgroupview.value().equals( statementGroup2 ),
		'Retrieved value after setting a new value.'
	);

	var statementlistview = statementgroupview.statementlistview,
		statementList1 = new wb.datamodel.StatementList( [
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P2' ) )
			),
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertySomeValueSnak( 'P2' ) )
			)
		] ),
		statementList2 = new wb.datamodel.StatementList( [
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P3' ) )
			)
		] ),
		statementList3 = new wb.datamodel.StatementList( [
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P1' ) )
			),
			new wb.datamodel.Statement(
				new wb.datamodel.Claim( new wb.datamodel.PropertyNoValueSnak( 'P2' ) )
			)
		] );

	statementlistview.value( statementList1 );

	assert.ok(
		statementgroupview.value().equals(
			new wb.datamodel.StatementGroup( 'P2', statementList1 )
		),
		'Retrieved current value after setting a new value to the statementlistview encapsulated '
			+ 'by the statementgroupview.'
	);

	assert.ok(
		statementgroupview.option( 'value' ).equals( statementGroup2 ),
		'Retrieved value still persisting via option().'
	);

	statementlistview.value( statementList2 );

	assert.ok(
		statementgroupview.value().equals(
			new wb.datamodel.StatementGroup( 'P3', statementList2 )
		),
		'Retrieved current value after setting a new value featuring another Property to the '
			+ 'statementlistview encapsulated by the statementgroupview.'
	);

	statementlistview.value( statementList3 );

	assert.throws(
		function() {
			statementgroupview.value();
		},
		'Property of Statements in statementlistview differ resulting in not being able to '
			+ 'instantiate a StatementGroup.'
	);
} );

}( jQuery, wikibase, QUnit ) );
