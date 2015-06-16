/**
 * @licence GNU GPL v2+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
( function( wb, $ ) {
	'use strict';

	/**
	 * @constructor
	 *
	 * @param {jQuery.valueview.ExpertStore} expertStore
	 * @param {valueFormatters.ValueFormatterStore} formatterStore
	 * @param {valueParsers.ValueParserStore} parserStore
	 * @param {string} language
	 * @param {util.MessageProvider} messageProvider
	 * @param {util.ContentLanguages} contentLanguages
	 */
	var SELF = wb.ValueViewBuilder = function WbValueViewBuilder(
		expertStore, formatterStore, parserStore, language, messageProvider, contentLanguages
	) {
		this._baseOptions = {
			expertStore: expertStore,
			formatterStore: formatterStore,
			parserStore: parserStore,
			language: language,
			messageProvider: messageProvider,
			contentLanguages: contentLanguages
		};
	};

	$.extend( SELF.prototype, {
		/**
		 * @type {Object}
		 */
		_baseOptions: null,

		/**
		 * @param {jQuery} $valueViewDom
		 * @param {dataTypes.DataType} dataType
		 * @param {dataValues.DataValue} dataValue
		 *
		 * @return {jQuery.valueview}
		 */
		initValueView: function( $valueViewDom, dataType, dataValue ) {
			var valueView,
				valueViewOptions = this._getOptions( dataType, dataValue );

			// TODO: Use something like an 'editview' and just change its data type rather than
			// initializing this over and over again and doing the checks.
			$valueViewDom.valueview( valueViewOptions );
			valueView = $valueViewDom.data( 'valueview' );

			return valueView;
		},

		_getOptions: function( dataType, dataValue ) {
			var valueViewOptions = $.extend( {}, this._baseOptions, {
				value: dataValue
			} );

			if( dataType ) {
				valueViewOptions.dataTypeId = dataType.getId();
			}

			if( dataType || dataValue ) {
				valueViewOptions.dataValueType = this._getDataValueType( dataType, dataValue );
			}

			return valueViewOptions;
		},

		_getDataValueType: function( dataType, dataValue ) {
			// If the value is undeserializable, then we need an UnDeserializableValue expert
			// regardless of the snak's data type.
			if( dataValue && dataValue.getType() === 'undeserializable' ) {
				return dataValue.getType();
			} else if( dataType ) {
				return dataType.getDataValueType();
			}

			return dataValue.getType();
		}
	} );

}( wikibase, jQuery ) );
