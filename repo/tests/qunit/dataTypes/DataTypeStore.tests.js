/**
 * @license GPL-2.0-or-later
 * @author H. Snater < mediawiki@snater.com >
 */
( function ( QUnit, dataTypes ) {
	'use strict';

	QUnit.module( 'wikibase.dataTypes.DataTypeStore' );

	QUnit.test( 'Test initializing a DataType object', function ( assert ) {
		var dataTypeStore = new dataTypes.DataTypeStore(),
			testDataType = new dataTypes.DataType( 'foo', 'fooDataValueType' ),
			testDataTypeId = testDataType.getId();

		dataTypeStore.registerDataType( testDataType );

		assert.strictEqual(
			dataTypeStore.hasDataType( testDataTypeId ),
			true,
			'hasDataType: Data type "' + testDataTypeId + '" is available after registering it'
		);

		assert.strictEqual(
			testDataType === dataTypeStore.getDataType( testDataTypeId ),
			true,
			'getDataType: returns exact same instance of the data type which was registered before'
		);
	} );

}( QUnit, wikibase.dataTypes ) );
