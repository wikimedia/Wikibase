'use strict';

const MWBot = require( 'mwbot' ),
	bot = new MWBot( {
		apiUrl: browser.options.baseUrl + '/api.php'
	} );

class WikibaseApi {

	/**
	 * Create an item
	 *
	 * @param {string} [label] Optional English label of the item
	 * @param {Object} [data] Optional data to populate the item with
	 * @return {Promise}
	 */
	createItem( label, data ) {
		let labels = {},
			itemData = {};
		if ( label ) {
			labels = {
				en: {
					language: 'en',
					value: label
				}
			};
		}

		Object.assign( itemData, { labels }, data );

		return bot.getEditToken()
			.then( () => {
				return new Promise( ( resolve, reject ) => {
					bot.request( {
						action: 'wbeditentity',
						'new': 'item',
						data: JSON.stringify( itemData ),
						token: bot.editToken
					} ).then( ( response ) => {
						resolve( response.entity.id );
					}, reject );
				} );
			} );
	}

	createProperty( datatype, data ) {
		let propertyData = {};

		propertyData = Object.assign( {}, { datatype }, data );

		return bot.getEditToken()
			.then( () => {
				return new Promise( ( resolve, reject ) => {
					bot.request( {
						action: 'wbeditentity',
						'new': 'property',
						data: JSON.stringify( propertyData ),
						token: bot.editToken
					} ).then( ( response ) => {
						resolve( response.entity.id );
					}, reject );
				} );
			} );
	}

	getEntity( id ) {
		return new Promise( ( resolve, reject ) => {
			bot.request( {
				ids: id,
				action: 'wbgetentities',
				token: bot.editToken
			} ).then( ( response ) => {
				resolve( response.entities[ id ] );
			}, reject );
		} );
	}

	getProperty( datatype ) {
		let envName = `WIKIBASE_PROPERTY_${ datatype.toUpperCase() }`;
		if ( envName in process.env ) {
			return Promise.resolve( process.env[ envName ] );
		} else {
			return this.createProperty( datatype ).then( ( propertyId ) => {
				process.env[ envName ] = propertyId;
				return propertyId;
			} );
		}
	}

}

module.exports = new WikibaseApi();
