/**
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 */
( function( wb, util ) {
'use strict';

var PARENT = wb.Snak;

/**
 * Represents a Wikibase PropertyNoValueSnak in JavaScript.
 * @constructor
 * @extends wb.Snak
 * @since 0.2
 * @see https://meta.wikimedia.org/wiki/Wikidata/Data_model#PropertyNoValueSnak
 *
 * @param {Number} propertyId
 */
var SELF = wb.PropertyNoValueSnak = util.inherit( 'WbPropertyNoValueSnak', PARENT, {} );

/**
 * @see wb.Snak.TYPE
 * @type String
 */
SELF.TYPE = 'novalue';

}( wikibase, util ) );
