/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author H. Snater < mediawiki@snater.com >
 */
( function( wb, $, dv ) {
	'use strict';

	var SELF = wb.api.ParseValueCaller = function( api ) {
		this._api = api;
	};

	$.extend( SELF.prototype, {

		/**
		 * @type {wb.RepoApi}
		 */
		_api: null,

		/**
		 * Makes a request to the API to parse values on the server side. Will return a jQuery.Promise
		 * which will be resolved if the parsing is successful or rejected if it fails or the API can't
		 * be reached.
		 * @since 0.5
		 *
		 * @param {string} parser
		 * @param {string[]} values
		 * @param {Object} options
		 * @return {jQuery.Promise}
		 *         Resolved parameters:
		 *         - {dataValues.DataValues[]}
		 *         Rejected parameters:
		 *         - {string} Error code.
		 *         - {string|Object} Error message, original response containing "error" attribute or
		 *           status object (see mediaWiki.Api.ajax).
		 */
		parseValues: function( parser, values, options ) {
			var deferred = $.Deferred();

			options = options || {};

			this._api
			.get( {
				action: 'wbparsevalue',
				parser: parser,
				values: values.join( '|' ),
				options: JSON.stringify( options )
			} )
			.done( function( response ) {
				if( !response.results ) {
					deferred.reject(
						'result-unexpected',
						'The parse API returned an unexpected result'
					);
					return;
				}

				if( response.results.length === 0 ) {
					deferred.reject( 'result-empty', 'Parse API returned an empty result set.' );
					return;
				}

				var dataValues = [];

				for( var i in response.results ) {
					var result = response.results[i];

					if( result.error ) {
						deferred.reject( result.messages[0].html['*'], result );
						break;
					}

					try {
						dataValues.push( unserializeResult( result ) );
					} catch( error ) {
						deferred.reject( error.name, error.message );
						break;
					}
				}

				deferred.resolve( dataValues );

			} ).fail( function( code, details ) {
				deferred.reject( code, details );
			} );

			return deferred.promise();
		}
	} );

	/**
	 * @param {string} result
	 * @return {dataValues.DataValue}
	 */
	function unserializeResult( result ) {
		if( !( result.value && result.type ) ) {
			throw new Error( 'Unknown API error' );
		} else {
			return dv.newDataValue( result.type, result.value );
		}
	}

}( wikibase, jQuery, dataValues ) );
