/**
 * @licence GNU GPL v2+
 * @author Adrian Lang < adrian.lang@wikimedia.de >
 */
( function( sinon, wb, $ ) {
	'use strict';

	QUnit.module( 'wikibase.entityChangers.AliasesChanger', QUnit.newMwEnvironment() );

	var SUBJECT = wikibase.entityChangers.AliasesChanger;

	QUnit.test( 'is a function', function( assert ) {
		assert.equal(
			typeof SUBJECT,
			'function',
			'is a function.'
		);
	} );

	QUnit.test( 'is a constructor', function( assert ) {
		assert.ok( new SUBJECT() instanceof SUBJECT );
	} );

	QUnit.test( 'setAliases performs correct API call', function( assert ) {
		var api = {
			setAliases: sinon.spy( function() {
				return $.Deferred().promise();
			} ),
		};
		var aliasesChanger = new SUBJECT(
			api,
			{ getAliasesRevision: function() { return 0; } },
			new wb.datamodel.Item()
		);

		aliasesChanger.setAliases(
			[],
			'language'
		);

		assert.ok( api.setAliases.calledOnce );
	} );

	QUnit.test( 'setAliases correctly handles API response', function( assert ) {
		var api = {
			setAliases: sinon.spy( function() {
				return $.Deferred().resolve( {
					entity: {}
				} ).promise();
			} ),
		};
		var aliasesChanger = new SUBJECT(
			api,
			{ getAliasesRevision: function() { return 0; }, setAliasesRevision: function() {} },
			new wb.datamodel.Item()
		);

		QUnit.stop();

		aliasesChanger.setAliases(
			[],
			'language'
		)
		.done( function( savedAliases ) {
			QUnit.start();
			assert.ok( true, 'setAliases succeeded' );
		} )
		.fail( function() {
			assert.ok( false, 'setAliases failed' );
		} );
	} );

} )( sinon, wikibase, jQuery );
