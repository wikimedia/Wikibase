/**
 * @license GPL-2.0+
 * @author H. Snater < mediawiki@snater.com >
 */
( function ( wb, vv, dv ) {
	'use strict';

	/**
	 * @type {object} Map from property type to expert module name
	 */
	var registeredExperts = require( 'wikibase.experts.modules' );

	var MODULE = wb.experts;

	/**
	 * @param {dataTypes.DataTypeStore} dataTypeStore
	 */
	MODULE.getStore = function ( dataTypeStore ) {
		var expertStore = new vv.ExpertStore( vv.experts.UnsupportedValue );

		expertStore.registerDataValueExpert(
			vv.experts.GlobeCoordinateInput,
			dv.GlobeCoordinateValue.TYPE
		);

		expertStore.registerDataValueExpert(
			vv.experts.QuantityInput,
			dv.QuantityValue.TYPE
		);

		expertStore.registerDataValueExpert(
			vv.experts.StringValue,
			dv.StringValue.TYPE
		);

		expertStore.registerDataValueExpert(
			vv.experts.TimeInput,
			dv.TimeValue.TYPE
		);

		expertStore.registerDataValueExpert(
			vv.experts.UnDeserializableValue,
			dv.UnDeserializableValue.TYPE
		);

		// Register experts for data types defined in Wikibase. Since those data types are defined by a
		// setting, it needs to be checked whether they are actually defined.

		var dataTypeIdToExpertConstructor = resolveExpertModules( registeredExperts );

		for ( var dataTypeId in dataTypeIdToExpertConstructor ) {
			var dataType = dataTypeStore.getDataType( dataTypeId );
			if ( dataType ) {
				expertStore.registerDataTypeExpert(
					dataTypeIdToExpertConstructor[ dataTypeId ],
					dataType.getId()
				);
			}
		}

		return expertStore;

	};

	function resolveExpertModules( registeredExperts ) {
		var constructors = {};
		for ( var dataType in registeredExperts ) {
			if ( registeredExperts.hasOwnProperty( dataType ) ) {
				constructors[ dataType ] = require( registeredExperts[ dataType ] );
			}
		}

		return constructors;
	}

}( wikibase, jQuery.valueview, dataValues ) );
