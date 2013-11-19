/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */

( function( wb, dv, $, QUnit ) {
	'use strict';

	QUnit.module( 'wikibase.datamodel.Statement', QUnit.newWbEnvironment() );

	QUnit.test( 'Rank evaluation on instantiation', function( assert ) {
		var statement = new wb.Statement(
			new wb.PropertyValueSnak( 'P1', new dv.StringValue( 'string1' ) )
		);

		assert.equal(
			statement.getRank(),
			wb.Statement.RANK.NORMAL,
			'Assigning \'normal\' rank by default.'
		);

		statement = new wb.Statement(
			new wb.PropertyValueSnak( 'P1', new dv.StringValue( 'string1' ) ),
			null,
			null,
			wb.Statement.RANK.DEPRECATED
		);

		assert.equal(
			statement.getRank(),
			wb.Statement.RANK.DEPRECATED,
			'Instantiated statement object with \'deprecated\' rank.'
		);
	} );

	QUnit.test( 'setRank() & getRank()', function( assert ) {
		var statement = new wb.Statement( new wb.PropertyNoValueSnak( 'P1' ) );

		statement.setRank( wb.Statement.RANK.PREFERRED );

		assert.equal(
			statement.getRank(),
			wb.Statement.RANK.PREFERRED,
			'Assigned \'preferred\' rank.'
		);

		statement.setRank( wb.Statement.RANK.DEPRECATED );

		assert.equal(
			statement.getRank(),
			wb.Statement.RANK.DEPRECATED,
			'Assigned \'deprecated\' rank.'
		);

		statement.setRank( wb.Statement.RANK.NORMAL );

		assert.equal(
			statement.getRank(),
			wb.Statement.RANK.NORMAL,
			'Assigned \'normal\' rank.'
		);
	} );

	QUnit.test( 'toJSON', function( assert ) {
		var statement = new wb.Statement( new wb.PropertyNoValueSnak( 'P42' ) );

		assert.ok(
			statement.equals( wb.Claim.newFromJSON( statement.toJSON() ) ),
			'Exported simple statement to JSON.'
		);

		statement = new wb.Statement(
			new wb.PropertyValueSnak( 'P23', new dv.StringValue( '~=[,,_,,]:3' ) ),
			new wb.SnakList(
				[
					new wb.PropertyNoValueSnak( 'P9001' ),
					new wb.PropertySomeValueSnak( 'P42' )
				]
			),
			[
				new wb.Reference(
					new wb.SnakList(
						[
							new wb.PropertyValueSnak( 'P3', new dv.StringValue( 'string' ) ),
							new wb.PropertySomeValueSnak( 'P245' )
						]
					)
				),
				new wb.Reference(
					new wb.SnakList(
						[
							new wb.PropertyValueSnak( 'P856', new dv.StringValue( 'another string' ) ),
							new wb.PropertySomeValueSnak( 'P97' )
						]
					)
				)
			],
			wb.Statement.RANK.PREFERRED
		);

		assert.ok(
			statement.equals( wb.Claim.newFromJSON( statement.toJSON() ) ),
			'Exported complex statement to JSON.'
		);

	} );

	QUnit.test( 'equals()', function( assert ) {
		var statements = [
			new wb.Statement( new wb.PropertyValueSnak( 'P42', new dv.StringValue( 'string' ) ) ),
			new wb.Statement(
				new wb.PropertyValueSnak( 'P42', new dv.StringValue( 'string' ) ),
				new wb.SnakList(
					[
						new wb.PropertyValueSnak( 'P2', new dv.StringValue( 'some string' ) ),
						new wb.PropertySomeValueSnak( 'P9001' )
					]
				),
				[
					new wb.Reference(
						new wb.SnakList(
							[
								new wb.PropertyValueSnak( 'P3', new dv.StringValue( 'string' ) ),
								new wb.PropertySomeValueSnak( 'P245' )
							]
						)
					),
					new wb.Reference(
						new wb.SnakList(
							[
								new wb.PropertyValueSnak( 'P856', new dv.StringValue( 'another string' ) ),
								new wb.PropertySomeValueSnak( 'P97' )
							]
						)
					)
				],
				wb.Statement.RANK.PREFERRED
			),
			new wb.Statement( new wb.PropertyValueSnak( 'P41', new dv.StringValue( 'string' ) ) ),
			new wb.Statement(
				new wb.PropertyValueSnak( 'P42', new dv.StringValue( 'string' ) ),
				new wb.SnakList(
					[
						new wb.PropertyValueSnak( 'P2', new dv.StringValue( 'some string' ) ),
						new wb.PropertySomeValueSnak( 'P9001' )
					]
				)
			),
			new wb.Statement(
				new wb.PropertyValueSnak( 'P42', new dv.StringValue( 'string' ) ),
				new wb.SnakList(
					[
						new wb.PropertyValueSnak( 'P2', new dv.StringValue( 'some string' ) ),
						new wb.PropertySomeValueSnak( 'P9001' )
					]
				),
				[
					new wb.Reference(
						new wb.SnakList(
							[
								new wb.PropertyValueSnak( 'P3', new dv.StringValue( 'string' ) ),
								new wb.PropertySomeValueSnak( 'P245' )
							]
						)
					),
					new wb.Reference(
						new wb.SnakList(
							[
								new wb.PropertyValueSnak( 'P123', new dv.StringValue( 'another string' ) ),
								new wb.PropertySomeValueSnak( 'P97' )
							]
						)
					)
				],
				wb.Statement.RANK.PREFERRED
			)
		];

		// Compare statements:
		$.each( statements, function( i, statement ) {
			var clonedStatement = wb.Claim.newFromJSON( statement.toJSON() );

			// Check if "cloned" statement is equal:
			assert.ok(
				statement.equals( clonedStatement ),
				'Verified statement "' + i + '" on equality.'
			);

			// Compare to all other statements:
			$.each( statements, function( j, otherStatement ) {
				if ( j !== i ) {
					assert.ok(
						!statement.equals( otherStatement ),
						'Statement "' + i + '" is not equal to statement "'+ j + '".'
					);
				}
			} );

		} );

		// Compare claim to statement:
		var claim = new wb.Claim( new wb.PropertyValueSnak( 'P42', new dv.StringValue( 'string' ) ) ),
			statement = new wb.Statement(
				new wb.PropertyValueSnak( 'P42', new dv.StringValue( 'string' ) )
			);

		assert.ok(
			!statement.equals( claim ),
			'Statement does not equal claim that received the same initialization parameters.'
		);

	} );

}( wikibase, dataValues, jQuery, QUnit ) );
