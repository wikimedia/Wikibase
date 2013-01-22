/**
 * JavaScript for managing editable representation of item labels.
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author Tobias Gritschacher
 */
( function( mw, wb, $, undefined ) {
'use strict';
var PARENT = wb.ui.PropertyEditTool.EditableValue;

/**
 * Serves the input interface for the label of an item, extends EditableValue.
 * @constructor
 * @extends wb.ui.PropertyEditTool.EditableValue
 * @since 0.1
 */
var SELF = wb.ui.PropertyEditTool.EditableLabel = wb.utilities.inherit( PARENT, {
	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.API_VALUE_KEY
	 */
	API_VALUE_KEY: 'labels',

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.prototype.getInputHelpMessage
	 *
	 * @return string tooltip help message
	 */
	getInputHelpMessage: function() {
		return mw.msg( 'wikibase-label-input-help-message', mw.config.get('wbDataLangName') );
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue._setRevisionIdFromApiResponse
	 */
	_setRevisionIdFromApiResponse: function( response ) {
		wb.getRevisionStore().setLabelRevision( response.lastrevid );
		return true;
	},

	/**
	 * Calling the corresponding method in the wikibase.RepoApi
	 *
	 * @return {jQuery.Promise}
	 */
	queryApi: function() {
		return this._api.setLabel(
			mw.config.get( 'wbEntityId' ),
			wb.getRevisionStore().getLabelRevision(),
			this.getValue().toString(),
			mw.config.get( 'wgUserLanguage' )
		);
	}
} );

/**
 * @see wb.ui.PropertyEditTool.EditableValue.newFromDom
 */
SELF.newFromDom = function( subject, options, toolbar ) {
	var $subject = $( subject ),
		interfaceParent = $subject.children( '.wb-value' ).first(),
		simpleInterface = new wb.ui.PropertyEditTool.EditableValue.Interface( interfaceParent, {
			'inputPlaceholder': mw.msg( 'wikibase-label-edit-placeholder' ),
			'autoExpand': false
		} );

	// TODO: get rid of this
	simpleInterface.normalize = function( value ) {
		value = wb.ui.PropertyEditTool.EditableValue.Interface.prototype.normalize.call( this, value );
		value = value.replace( /\s+/g, ' ' ); // make sure we don't ever allow several spaces in the items label
		return value;
	};

	return new SELF( $subject, options, simpleInterface, toolbar );
};

}( mediaWiki, wikibase, jQuery ) );
