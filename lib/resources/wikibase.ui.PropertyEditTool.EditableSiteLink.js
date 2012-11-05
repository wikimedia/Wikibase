/**
 * JavaScript for managing editable representation of site links.
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author H. Snater <mediawiki@snater.com>
 * @author Daniel Werner
 */
( function( mw, wb, $, undefined ) {
'use strict';
var PARENT = wb.ui.PropertyEditTool.EditableValue;

/**
 * Serves the input interface for a site link, extends EditableValue.
 * @constructor
 * @extends wb.ui.PropertyEditTool.EditableValue
 * @since 0.1
 */
var SELF = wb.ui.PropertyEditTool.EditableSiteLink = wb.utilities.inherit( PARENT, {
	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.API_VALUE_KEY
	 */
	API_VALUE_KEY: 'sitelinks',

	/**
	 * The part of the editable site link representing the site the selected page belongs to
	 * @type wb.ui.PropertyEditTool.EditableValue.SiteIdInterface
	 */
	siteIdInterface: null,

	/**
	 * The part of the editable site link representing the link to the site
	 * @type wb.ui.PropertyEditTool.EditableValue.SitePageInterface
	 */
	sitePageInterface: null,

	/**
	 * current results received from the api
	 * @type Array
	 */
	_currentResults: null,

	/**
	 * @see wb.ui.PropertyEditTool.EditableValue._init
	 */
	_init: function( subject, options, interfaces, toolbar ) {
		if( interfaces.length < 2
			|| !( interfaces[0] instanceof wb.ui.PropertyEditTool.EditableValue.SiteIdInterface )
			|| !( interfaces[1] instanceof wb.ui.PropertyEditTool.EditableValue.SitePageInterface )
		) {
			throw new Error( 'Proper interfaces needed for EditableSiteLink are not provided' );
		}

		// TODO: rather provide getters for these
		this.siteIdInterface = interfaces.siteId = interfaces[0]; // this._interfaces.siteId
		this.sitePageInterface = interfaces.pageName = interfaces[1]; // this._interfaces.pageName

		PARENT.prototype._init.apply( this, arguments );
	},

	/**
	 * @see wb.ui.PropertyEditTool.EditableValue._bindInterfaces
	 */
	_bindInterfaces: function( interfaces ) {
		PARENT.prototype._bindInterfaces.call( this, interfaces );

		// TODO: move this into _init() perhaps
		/* TODO: Setting the language attributes on initialisation will not be required as soon as
		the attributes are already attached in PHP for the non-JS version */
		if ( this.siteIdInterface.getSelectedSite() !== null ) {
			this.sitePageInterface.setLanguageAttributes(
				this.siteIdInterface.getSelectedSite().getLanguage()
			);
		}
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue._interfaceHandler_onInputRegistered
	 *
	 * @param relatedInterface wikibase.ui.PropertyEditTool.EditableValue.Interface
	 */
	_interfaceHandler_onInputRegistered: function( relatedInterface ) {
		PARENT.prototype._interfaceHandler_onInputRegistered.call( this, relatedInterface );

		var idInterface = this._interfaces.siteId;
		var pageInterface = this._interfaces.pageName;

		// set up necessary communication between both interfaces:
		var site = idInterface.getSelectedSite();
		if( site !== pageInterface.getSite() && site !== null ) {
			// FIXME: this has to be done on idInterface.onInputRegistered only but that
			//        is not really possible with the current 'event' system since this function is
			//        registered there.
			pageInterface.setSite( site );

			var siteId = idInterface.getSelectedSiteId();

			// change class names:
			this._subject.removeClassByRegex( /^wb-sitelinks-.+/ );
			this._subject.addClass( 'wb-sitelinks-' + siteId );

			idInterface._getValueContainer().removeClassByRegex( /^wb-sitelinks-site-.+/ );
			idInterface._getValueContainer().addClass( 'wb-sitelinks-site wb-sitelinks-site-' + siteId );

			pageInterface._getValueContainer().removeClassByRegex( /^wb-sitelinks-link-.+/ );
			pageInterface._getValueContainer().addClass( 'wb-sitelinks-link wb-sitelinks-link-' + siteId );
			// directly updating the page interface's language attributes when a site is selected
			pageInterface.setLanguageAttributes( site.getLanguage() );
		}

		// only enable site page selector if there is a valid site id selected
		pageInterface[ idInterface.isValid() ? 'enable' : 'disable' ]();
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue._getValueFromApiResponse
	 */
	_getValueFromApiResponse: function( response ) {
		var siteId = this._interfaces.siteId.getSelectedSite().getId();
		var normalizedTitle = response[ this.API_VALUE_KEY ][ this._interfaces.siteId.getSelectedSite().getGlobalSiteId() ].title;
		return [ siteId, normalizedTitle ];
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue._getToolbarParent
	 *
	 * @return jQuery node the toolbar should be appended to
	 */
	_getToolbarParent: function() {
		return this._subject.children( 'td' ).last();
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.startEditing
	 *
	 * @return bool will return false if edit mode is active already.
	 */
	startEditing: function() {
		// set ignored site links again since they could have changed
		this._interfaces.siteId.setOption( 'ignoredSiteLinks', this.ignoredSiteLinks );
		return PARENT.prototype.startEditing.call( this );
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.stopEditing
	 *
	 * @param bool save whether to save the current value
	 * @return jQuery.Promise
	 */
	stopEditing: function( save ) {
		var promise = PARENT.prototype.stopEditing.call( this, save );
		/*
		 * prevent siteId input element from appearing again when triggering edit on a just created
		 * site link without having reloaded the page; however, it is required to check whether the
		 * corresponding interface (still) exists since it might have been destroyed when - instead
		 * of being saved - the pending value got removed again by cancelling
		 */
		promise.done( $.proxy( function() {
			if ( this._interfaces !== null ) {
				this._interfaces.siteId.setActive( this.isPending() );
			}
		}, this ) );
		return promise;
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.getInputHelpMessage
	 *
	 * @return string tooltip help message
	 */
	getInputHelpMessage: function() {
		return mw.msg( 'wikibase-sitelinks-input-help-message' );
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.getApiCallParams
	 *
	 * @param number apiAction
	 * @return Object containing the API call specific parameters
	 */
	getApiCallParams: function( apiAction ) {
		var params = PARENT.prototype.getApiCallParams.call( this, apiAction );
		params = $.extend( params, {
			action: 'wbsetsitelink',
			baserevid: mw.config.get( 'wgCurRevisionId' ),
			linksite: this.siteIdInterface.getSelectedSite().getGlobalSiteId(),
			linktitle: ( apiAction === this.API_ACTION.REMOVE || apiAction === this.API_ACTION.SAVE_TO_REMOVE ) ? '' : this.getValue()[1]
		} );
		delete( params.link ); // ? danwe: why is there an 'item' AND a 'link' param here?
		delete( params.item ); // ? danwe: why is there an 'item' AND a 'link' param here?
		delete( params.language );

		return params;
	},

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.performApiAction
	 */
	performApiAction: function( apiAction ) {
		var promise = PARENT.prototype.performApiAction.call( this, apiAction ),
			self = this;

		// (bug 40399) for site-links we want to get the normalized link from the API result to make sure we have
		// the right links without knowing about the site type.
		promise.done( function( response ) {
			var page = self._interfaces.pageName.getValue(),
				site = wb.getSite( self._interfaces.siteId.getValue() );

			if( page !== '' && site !== null ) {
				var url = response.entity.sitelinks[ site.getGlobalSiteId() ].url,
					oldFn = site.getUrlTo;

				// overwrite the getUrlTo function of this site object to always return the valid url returned by the
				// API without caring about the site type. This acts as a filter on top of the original function.
				// TODO/FIXME: this is rather hacky, a real cache could be introduced to wb.Site.getUrlTo
				site.getUrlTo = function( pageTitle ) {
					if( $.trim( pageTitle ) === page ) {
						return url;
					}
					return oldFn( pageTitle );
				};
			}
		} );
		return promise;
	},

	/////////////////
	// CONFIGURABLE:
	/////////////////

	/**
	 * determines whether to keep an empty form when leaving edit mode
	 * @see wikibase.ui.PropertyEditTool.EditableValue
	 * @var bool
	 */
	preserveEmptyForm: false,

	/**
	 * Allows to specify an array with sites which should not be allowed to choose
	 * @var wikibase.Site[]
	 */
	ignoredSiteLinks: null
} );

/**
 * @see wb.ui.PropertyEditTool.EditableValue.newFromDom
 */
SELF.newFromDom = function( subject, options, toolbar, /** internal */ constructor ) {
	var $subject = $( subject ),
		ev = wb.ui.PropertyEditTool.EditableValue,
		newEV = new ( constructor || SELF )(),
		iSiteId = new ev.SiteIdInterface(),
		iSitePage  = new ev.SitePageInterface(),
		$tableCells = $subject.children( 'td' );

	// interface for choosing the source site:
	// propagate information about which site links are set already to the site ID interface:
	iSiteId.setOption( 'ignoredSiteLinks', newEV.ignoredSiteLinks );

	iSiteId.init( $tableCells[0], {
		inputPlaceholder: mw.msg( 'wikibase-sitelink-site-edit-placeholder' )
	} );

	// once stored, the site ID should not be editable!
	// TODO/FIXME: not sure this should be done here
	iSiteId.setActive( $subject.hasClass( 'wb-pending-value' ) );

	// interface for choosing a page (from the source site)
	// pass the second to last cell as subject since the last cell will be used by the toolbar
	iSitePage.init(
		$tableCells[ $tableCells.length - 2 ],
		{ inputPlaceholder: mw.msg( 'wikibase-sitelink-page-edit-placeholder' ) },
		iSiteId.getSelectedSite()
	);
	iSitePage.ajaxParams = {
		action: 'opensearch',
		namespace: 0,
		suggest: ''
	};

	newEV.init( $subject, options, [ iSiteId, iSitePage ], toolbar );
	return newEV;
};

}( mediaWiki, wikibase, jQuery ) );
