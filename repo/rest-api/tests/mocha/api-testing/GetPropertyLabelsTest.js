'use strict';

const { assert } = require( 'api-testing' );
const { expect } = require( '../helpers/chaiHelper' );
const { createUniqueStringProperty, getLatestEditMetadata } = require( '../helpers/entityHelper' );
const { newGetPropertyLabelsRequestBuilder } = require( '../helpers/RequestBuilderFactory' );
const { assertValidError } = require( '../helpers/responseValidator' );

describe( newGetPropertyLabelsRequestBuilder().getRouteDescription(), () => {
	let propertyId;
	let propertyEnLabel;

	before( async () => {
		const testProperty = await createUniqueStringProperty();
		propertyId = testProperty.entity.id;
		propertyEnLabel = testProperty.entity.labels.en.value;
	} );

	it( 'can get the labels of a property', async () => {
		const testPropertyCreationMetadata = await getLatestEditMetadata( propertyId );

		const response = await newGetPropertyLabelsRequestBuilder( propertyId ).assertValidRequest().makeRequest();

		expect( response ).to.have.status( 200 );
		assert.deepEqual( response.body, { en: propertyEnLabel } );
		assert.strictEqual( response.header.etag, `"${testPropertyCreationMetadata.revid}"` );
		assert.strictEqual( response.header[ 'last-modified' ], testPropertyCreationMetadata.timestamp );
	} );

	it( '400 error - bad request, invalid property ID', async () => {
		const invalidPropertyId = 'X123';
		const response = await newGetPropertyLabelsRequestBuilder( invalidPropertyId )
			.assertInvalidRequest()
			.makeRequest();

		assertValidError( response, 400, 'invalid-property-id', { 'property-id': invalidPropertyId } );
		assert.include( response.body.message, invalidPropertyId );
	} );

	it( 'responds 404 in case the property does not exist', async () => {
		const nonExistentProperty = 'P99999999';
		const response = await newGetPropertyLabelsRequestBuilder( nonExistentProperty )
			.assertValidRequest()
			.makeRequest();

		assertValidError( response, 404, 'property-not-found' );
		assert.include( response.body.message, nonExistentProperty );
	} );

} );
