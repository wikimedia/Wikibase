/**
 * @license GPL-2.0-or-later
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 * @author H. Snater < mediawiki@snater.com >
 */
wikibase.dataTypeStore = ( function ( wb ) {
	'use strict';

	var dataTypeStore = new wb.dataTypes.DataTypeStore(),
		dataTypeDefinitions = mw.config.get( 'wbDataTypes' ) || {};

	$.each( dataTypeDefinitions, function ( dtTypeId, dtDefinition ) {
		dataTypeStore.registerDataType( wb.dataTypes.DataType.newFromJSON( dtTypeId, dtDefinition ) );
	} );

	return dataTypeStore;

}( wikibase ) );
