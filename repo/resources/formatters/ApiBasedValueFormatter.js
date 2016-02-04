/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( $, wb, vf, util ) {
	'use strict';

	wb.formatters = wb.formatters || {};

	var PARENT = vf.ValueFormatter;

	/**
	 * A ValueFormatter which is doing an API request to
	 * the FormatSnakValue API module for formatting a value.
	 *
	 * @constructor
	 * @extends valueFormatters.ValueFormatter
	 * @since 0.5
	 *
	 * @param {wikibase.api.FormatValueCaller} formatValueCaller
	 * @param {Object] additionalOptions
	 * @param {string} dataTypeId
	 * @param {string} outputType
	 */
	wb.formatters.ApiBasedValueFormatter = util.inherit(
		'WbApiBasedValueFormatter',
		PARENT,
		function( formatValueCaller, additionalOptions, dataTypeId, outputType ) {
			this._formatValueCaller = formatValueCaller;
			this._options = additionalOptions;
			this._dataTypeId = dataTypeId;
			this._outputType = outputType;
		},
		{
			/**
			 * @var {wikibase.api.FormatValueCaller}
			 */
			_formatValueCaller: null,

			/**
			 * @param {string}
			 */
			_dataTypeId: null,

			/**
			 * @param {Object}
			 */
			_options: null,

			/**
			 * @param {string} outputType
			 */
			_outputType: null,

			/**
			 * @see valueFormatters.ValueFormatter.format
			 * @since 0.1
			 *
			 * @param {dataValues.DataValue} dataValue
			 * @return {jQuery.Promise}
			 *         Resolved parameters:
			 *         - {string} Formatted DataValue.
			 *         - {dataValues.DataValues} Original DataValue object.
			 *         Rejected parameters:
			 *         - {string} HTML error message.
			 */
			format: function( dataValue ) {
				var deferred = $.Deferred();

				this._formatValueCaller.formatValue( dataValue, this._dataTypeId, this._outputType, this._options )
				.done( function( formattedValue ) {
					deferred.resolve( formattedValue, dataValue );
				} )
				.fail( function( error ) {
					deferred.reject( error.detailedMessage || error.code );
				} );

				return deferred.promise();
			}

		}
	);

}( jQuery, wikibase, valueFormatters, util ) );
