/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, $, dt ) {
	'use strict';

	var api = new mw.Api();

	/**
	 * ValueFormatters API.
	 * @since 0.5
	 * @type {Object}
	 */
	wikibase.formatters.api = {};

	/**
	 * Makes a request to the API to format values on the server side. Will return a jQuery.Promise
	 * which will be resolved if formatting is successful or rejected if it fails or the API cannot
	 * be reached.
	 * @since 0.5
	 *
	 * @param {dataValues.DataValue} dataValue
	 * @param {string} [dataType]
	 * @param {string} [outputFormat]
	 * @param {Object} [options]
	 *
	 * @return {$.Promise}
	 */
	wikibase.formatters.api.formatValue = function(
		dataValue, dataType, outputFormat, options
	) {

		// Evaluate optional arguments:
		if( outputFormat === undefined ) {
			if( $.isPlainObject( dataType ) ) {
				options = dataType;
				dataType = undefined;
			} else if( !dt.hasDataType( dataType ) ) {
				outputFormat = dataType;
				dataType = undefined;
			}
		} else if( options === undefined ) {
			if( $.isPlainObject( outputFormat ) ) {
				options = outputFormat;
				outputFormat = undefined;
			}
		}

		var deferred = $.Deferred(),
			params = {
				action: 'wbformatvalue',
				datavalue:  $.toJSON( {
					value: dataValue.toJSON(),
					type: dataValue.getType()
				} ),
				options: $.toJSON( options || {} )
			};

		if( dataType ) {
			params.datatype = dataType;
		}

		if( outputFormat ) {
			params.generate = outputFormat;
		}

		api.get( params ).done( function( apiResult ) {
			if( apiResult.result ) {
				deferred.resolve( apiResult.result );
			} else {
				deferred.reject(
					'unexpected-result',
					'The formatter API returned an unexpected result'
				);
			}
		} ).fail( function( code, details ) {
			deferred.reject( code, details );
		} );

		return deferred.promise();
	};

}( mediaWiki, jQuery, dataTypes ) );
