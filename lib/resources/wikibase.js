/**
 * JavaScript for 'wikibase' extension
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner at wikimedia.de >
 */

/**
 * Global 'Wikibase' extension singleton.
 * @since 0.1
 *
 * TODO: startItemPageEditMode and stopItemPageEditMode should be removed or marked deprecated as
 *       soon as we can get rid of old wb.ui.PropertyEditTool ui. There should be no global edit
 *       mode event anymore since with the new jQuery.wikibase widgets all editing related events
 *       bubble through the DOM anyhow, so everyone can listen to those on any level of a pages DOM.
 *
 * @event startItemPageEditMode: Triggered when any edit mode on the item page is started
 *        (1) {jQuery.Event}
 *        (2) {wb.ui.PropertyEditTool.EditableValue|jQuery} origin Object which triggered the event.
 *            If the origin of the event is one of the new (jQuery.wikibase) widgets, then this will
 *            be the widget's DOM node.
 *
 * @event newItemCreated: Triggered after an item has been created and the necessary API request has
 *        returned.
 *        (1) {jQuery.Event}
 *        (2) {Object} item The new item returned by the API request. | FIXME: this should be an
 *            'Item' object!
 *
 * @event stopItemPageEditMode: Triggered when any edit mode on the item page is stopped.
 *        (1) {jQuery.Event}
 *        (2) {wb.ui.PropertyEditTool.EditableValue|jQuery} origin Object which triggered the event.
 *            If the origin of the event is one of the new (jQuery.wikibase) widgets, then this will
 *            be the widget's DOM node.
 *        (3) {Boolean} wasPending Whether value was a previously not existent/new value that has
 *            just been added
 *
 * @event restrictEntityPageActions: Triggered when editing is not allowed for the user.
 *        (see TODO/FIXME in wikibase.ui.entityViewInit - handle edit restrictions)
 *        (1) {jQuery.Event}
 *
 * @event blockEntityPageActions: Triggered when editing is not allowed for the user because he is
 *        blocked from the page.
 *        (see TODO/FIXME in wikibase.ui.entityViewInit - handle edit restrictions)
 *        (1) {jQuery.Event}
 */
var wikibase = new ( function Wb( mw, $ ) {
	'use strict';

	/**
	 * same as mediaWiki.log() but prefixes the log entry with 'wb:'
	 */
	this.log = function() {
		var args = $.makeArray( arguments );
		args.unshift( 'wb:' );
		mw.log.apply( mw.log, args );
	};

	/**
	 * Caches all entity information when loading the page.
	 * @var {Object}
	 */
	this.entity = {
		claims: []
	};

	/**
	 * Holds very basic information about all entities used in the pages entity view. Entity IDs
	 * are used as keys for many inner hashes where each is an instance of wb.FetchedContent.
	 * Each of those wb.FetchedContent instances holds an instance of wb.Entity as its content. This
	 * entity data might be incomplete though, it is guaranteed that ID, and label are provided,
	 * for wb.Property instances it is also guaranteed that the datatype is provided.
	 *
	 * TODO: This should vanish from here (since its global) and will be replaced with a proper
	 *       store interface which will be injected into each jQuery.wikibase.entityview
	 *
	 * @since 0.4
	 *
	 * @type {Object}
	 */
	this.fetchedEntities = {};

	/**
	 * Will hold a list of all the sites after getSites() was called. This will cache the result.
	 * @var wikibase.Site[]
	 */
	this._siteList = null;

	/**
	 * Holds a RevisionStore object to have access to stored revision ids.
	 * @var wikibase.RevisionStore
	 */
	this._revisionStore = null;

	/**
	 * Returns a revision store
	 *
	 * @return wikibase.RevisionStore
	 */
	this.getRevisionStore = function() {
		if( this._revisionStore === null ) {
			this._revisionStore = new this.RevisionStore( mw.config.get( 'wgCurRevisionId' ) );
		}
		return this._revisionStore;
	};

	/**
	 * Returns an array with all the known sites.
	 *
	 * @return wikibase.Site[]
	 */
	this.getSites = function() {
		if( this._siteList !== null ) {
			// get cached list since this can be an expensive job to do
			return this._siteList;
		}

		// get all the details about all the sites:
		var sitesDetails = mw.config.get( 'wbSiteDetails' );
		this._siteList = {};

		for( var siteId in sitesDetails ) {
			if( sitesDetails.hasOwnProperty( siteId ) ) {
				var site = sitesDetails[ siteId ];
				site.id = siteId;
				this._siteList[ siteId ] =  new this.Site( site );
			}
		}

		return this._siteList;
	};

	/**
	 * Returns whether the Wikibase installation knows a site with a certain ID.
	 *
	 * @return bool
	 */
	this.hasSite = function ( siteId ) {
		return this.getSite( siteId ) !== null;
	};

	/**
	 * Returns a wikibase.Site object with details about a site by the sites site ID. If there is no
	 * site related to the given ID, null will be returned.
	 *
	 * @param int siteId
	 * @return wikibase.Site|null
	 */
	this.getSite = function( siteId ) {
		var sites = this.getSites();
		var site = sites[ siteId ];

		if( site === undefined ) {
			return null;
		}
		return site;
	};

	/**
	 * Returns a wikibase.Site object with details about a site by the sites global ID. If there is no
	 * site related to the given ID, null will be returned.
	 *
	 * @since 0.4
	 *
	 * @param string globalSiteId
	 * @return wikibase.Site|null
	 */
	this.getSiteByGlobalId = function( globalSiteId ) {
		var sites = this.getSites(),
			site;
		for( site in sites ) {
			if ( sites[ site ].getGlobalSiteId() === globalSiteId ) {
				return sites[ site ];
			}
		}

		return null;
	};

	/**
	 * Tries to retrieve Universal Language Selector's set of languages.
	 *
	 * @return {Object} Set of languages (empty object when ULS is not available)
	 */
	this.getLanguages = function() {
		return ( $.uls !== undefined ) ? $.uls.data.languages : {};
	};

	/**
	 * Returns the name of a language by its language code. If the language code is unknown or ULS
	 * can not provide sufficient language information, then an empty string will be returned.
	 *
	 * @param {string} langCode
	 * @return string
	 */
	this.getLanguageNameByCode = function( langCode ) {
		var language = this.getLanguages()[ langCode ];
		if( language && language[2] ) {
			return language[2];
		}
		return '';
	};

} )( mediaWiki, jQuery );

window.wikibase = window.wb = wikibase; // global aliases
