/**
 * @licence GNU GPL v2+
 * @author Adrian Lang < adrian.lang@wikimedia.de >
 */
( function( sinon, wb, $ ) {
	'use strict';

	QUnit.module( 'wikibase.entityChangers.AliasesChanger' );

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
			} )
		};
		var aliasesChanger = new SUBJECT(
			api,
			{ getAliasesRevision: function() { return 0; } },
			new wb.datamodel.Item( 'Q1' )
		);

		aliasesChanger.setAliases( new wb.datamodel.MultiTerm( 'language', [] ) );

		assert.ok( api.setAliases.calledOnce );
	} );

	QUnit.test( 'setAliases correctly handles API response', function( assert ) {
		var api = {
			setAliases: sinon.spy( function() {
				return $.Deferred().resolve( {
					entity: {}
				} ).promise();
			} )
		};
		var aliasesChanger = new SUBJECT(
			api,
			{
				getAliasesRevision: function() { return 0; },
				setAliasesRevision: function() {}
			},
			new wb.datamodel.Item( 'Q1' )
		);

		QUnit.stop();

		aliasesChanger.setAliases( new wb.datamodel.MultiTerm( 'language', [] ) )
		.done( function( savedAliases ) {
			QUnit.start();
			assert.ok( true, 'setAliases succeeded' );
		} )
		.fail( function() {
			assert.ok( false, 'setAliases failed' );
		} );
	} );

	QUnit.test( 'setAliases correctly handles API failure', function( assert ) {
		var api = {
			setAliases: sinon.spy( function() {
				return $.Deferred()
					.reject( 'errorCode', { error: { code: 'errorCode' } } )
					.promise();
			} )
		};
		var aliasesChanger = new SUBJECT(
			api,
			{
				getAliasesRevision: function() { return 0; },
				setAliasesRevision: function() {}
			},
			new wb.datamodel.Item( 'Q1' )
		);

		QUnit.stop();

		aliasesChanger.setAliases( new wb.datamodel.MultiTerm( 'language', [] ) )
		.done( function( savedAliases ) {
			assert.ok( false, 'setAliases succeeded' );
		} )
		.fail( function( error ) {
			QUnit.start();

			assert.ok(
				error instanceof wb.api.RepoApiError,
				'setAliases failed with a RepoApiError'
			);

			assert.equal( error.code, 'errorCode' );
		} );
	} );

	QUnit.test( 'setAliases correctly removes aliases', function( assert ) {
		var api = {
			setAliases: sinon.spy( function() {
				return $.Deferred().resolve( {
					entity: {}
				} ).promise();
			} )
		};

		var item = new wb.datamodel.Item( 'Q1', new wb.datamodel.Fingerprint(
			null,
			null,
			new wb.datamodel.MultiTermMap( {
				language: new wb.datamodel.MultiTerm( 'language', ['alias'] )
			} )
		) );

		var aliasesChanger = new SUBJECT(
			api,
			{
				getAliasesRevision: function() { return 0; },
				setAliasesRevision: function() {}
			},
			item
		);

		QUnit.stop();

		aliasesChanger.setAliases( new wb.datamodel.MultiTerm( 'language', [] ) )
		.done( function() {
			QUnit.start();

			assert.ok( true, 'setAliases succeeded' );

			assert.ok(
				item.getFingerprint().getAliasesFor( 'language' ).isEmpty(),
				'Verified aliases being empty.'
			);

			sinon.assert.calledWith(
				api.setAliases,
				'Q1',
				0,
				sinon.match( [] ),
				sinon.match( ['alias'] ),
				'language'
			);
		} )
		.fail( function() {
			assert.ok( false, 'setAliases failed' );
		} );
	} );

} )( sinon, wikibase, jQuery );
