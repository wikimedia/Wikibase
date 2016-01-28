
/**
 * @licence GNU GPL v2+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
( function( mw, wb, $ ) {
	'use strict';

var MODULE = wb;
var PARENT = util.ContentLanguages;

/**
 * @constructor
 */
var SELF = MODULE.WikibaseContentLanguages = util.inherit(
	'WbContentLanguages',
	PARENT,
	function() {
		this._languageMap = mw.config.get( 'wgULSLanguages' );
		this._languageCodes = $.map( this._languageMap, function( val, key ) {
			return key;
		} );
		this._languageCodes = $.grep( this._languageCodes, function( code ) {
			return [ 'de-formal', 'nl-informal', 'tokipona' ].indexOf( code ) === -1;
		} );
	}
);

$.extend( SELF.prototype, {
	/**
	 * @type {Object|null}
	 * @private
	 */
	_languageMap: null,

	/**
	 * @type {string[]|null}
	 * @private
	 */
	_languageCodes: null,

	/**
	 * @inheritdoc
	 */
	getAll: function() {
		return this._languageCodes;
	},

	/**
	 * @inheritdoc
	 */
	getName: function( code ) {
		return this._languageMap ? this._languageMap[ code ] : null;
	}
} );

}( mediaWiki, wikibase, jQuery ) );
