/**
 * @licence GNU GPL v2+
 * @author Adrian Lang < adrian.lang@wikimedia.de >
 */
( function( wb ) {
	'use strict';

	QUnit.module( 'wikibase.store.EntityStore', QUnit.newWbEnvironment() );

	QUnit.test( 'Initialize', function( assert ) {
		var entityStore = new wb.store.EntityStore();
		assert.ok( entityStore.get, 'Entity store has get() method.' );
	} );

	QUnit.test( 'get() returns $.Promise', function( assert ) {
		var entityStore = new wb.store.EntityStore(),
			promise = entityStore.get( 'id' );

		assert.ok( promise.done, 'done() method exists.' );
	} );

	QUnit.test(
		'Promise is resolved asynchronously, even if the entity is cached',
		2,
		function( assert ) {
			var entityStore = new wb.store.EntityStore();
			entityStore.compile( {
				id: 'value'
			} );

			var promise = entityStore.get( 'id' );
			assert.equal( promise.state(), 'pending', 'Promise is pending.' );

			QUnit.stop();
			promise.done( function( entity ) {
				QUnit.start();
				assert.ok( true, 'Resolved promise.' );
			} );
		}
	);

} )( wikibase );
