/**
 * JavaScript for 'wikibase' extension
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
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
 *        (1) {jQuery.Event} event
 *        (2) {wb.ui.PropertyEditTool.EditableValue|jQuery} origin Object which triggered the event.
 *            If the origin of the event is one of the new (jQuery.wikibase) widgets, then this will
 *            be the widget's DOM node.
 *        (3) {Object} options An object with any of the following properties:
 *            {boolean|string} exclusive Whether action shall influence sub-toolbars of origin.
 *            {string} wbCopyrightWarningGravity Direction, defaults to "nw".
 *
 * @event newItemCreated: Triggered after an item has been created and the necessary API request has
 *        returned.
 *        (1) {jQuery.Event} event
 *        (2) {Object} item The new item returned by the API request. | FIXME: this should be an
 *            'Item' object!
 *
 * @event stopItemPageEditMode: Triggered when any edit mode on the item page is stopped.
 *        (1) {jQuery.Event} event
 *        (2) {wb.ui.PropertyEditTool.EditableValue|jQuery} origin Object which triggered the event.
 *            If the origin of the event is one of the new (jQuery.wikibase) widgets, then this will
 *            be the widget's DOM node.
 *        (3) {Object} options An object with any of the following properties:
 *            {boolean} save Whether the change got saved or canceled and dropped. Defaults to true.
 *            {boolean} wasPending Whether value was a previously not existent/new value that has
 *            just been added. Defaults to false.
 *
 * @event restrictEntityPageActions: Triggered when editing is not allowed for the user.
 *        (see TODO/FIXME in wikibase.ui.entityViewInit - handle edit restrictions)
 *        (1) {jQuery.Event} event
 *
 * @event blockEntityPageActions: Triggered when editing is not allowed for the user because he is
 *        blocked from the page.
 *        (see TODO/FIXME in wikibase.ui.entityViewInit - handle edit restrictions)
 *        (1) {jQuery.Event} event
 */
this.wikibase = this.wb = new ( function Wb( mw, $ ) {
	'use strict';

	/**
	 * Will hold a list of all the sites after getSites() was called. This will cache the result.
	 *
	 * TODO: This should go together with the old UI.
	 *
	 * @var wikibase.Site[]
	 */
	this._siteList = null;

	/**
	 * Holds a RevisionStore object to have access to stored revision ids.
	 *
	 * TODO: Should go with the implementation of proper store interface (see fetchedEntities todo)
	 *
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
	 * @return {Object} Global site IDs as field names, related wb.Site objects as value.
	 */
	this.getSites = function() {
		if( this._siteList !== null ) {
			// get cached list since this can be an expensive job to do
			return this._siteList;
		}

		// get all the details about all the sites:
		var sitesDetails = mw.config.get( 'wbSiteDetails' ),
			siteDefinition,
			site;

		this._siteList = {};

		for( var globalSiteId in sitesDetails ) {
			siteDefinition = sitesDetails[ globalSiteId ];
			site = new this.Site( siteDefinition );

			this._siteList[ globalSiteId ] = site;
		}

		return this._siteList;
	};

	/**
	 * Returns an array with all the known sites of one group.
	 *
	 * @since 0.4
	 *
	 * @return {Object} Global site IDs as field names, related wb.Site objects as value.
	 */
	this.getSitesOfGroup = function( groupId ) {
		var sitesOfGroup = {},
			sites = this.getSites();

		for( var siteId in sites ) {
			var site = sites[ siteId ];

			if( site.getGroup() === groupId ) {
				sitesOfGroup[ siteId ] = site;
			}
		}
		return sitesOfGroup;
	};

	/**
	 * Returns an array with all known site groups.
	 *
	 * @since 0.4
	 *
	 * @return string[]
	 */
	this.getSiteGroups = function() {
		var groups = [],
			sites = this.getSites();

		for( var siteId in sites ) {
			var site = sites[ siteId ],
				group = site.getGroup();

			if( $.inArray( group, groups ) === -1 ) {
				groups.push( group );
			}
		}
		return groups;
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
	 * Returns a wikibase.Site object with details about a site by the sites ID. If there is no site
	 * related to the given ID, null will be returned.
	 *
	 * @param {int} siteId
	 * @return wikibase.Site|null
	 */
	this.getSite = function( siteId ) {
		var sites = this.getSites(),
			site = sites[ siteId ];

		if( site === undefined ) {
			return null;
		}
		return site;
	};

	/**
	 * Returns a wikibase.Site object with details about a site by the sites global ID. If there is
	 * no site related to the given ID, null will be returned.
	 *
	 * @deprecated Use getSite() instead. The meaning of the site ID has changed in 0.4, also see
	 *             the Site object.
	 *
	 * @since 0.4
	 *
	 * @param {string} globalSiteId
	 * @return wikibase.Site|null
	 */
	this.getSiteByGlobalId = function( globalSiteId ) {
		return this.getSite( globalSiteId );
	};

	/**
	 * Tries to retrieve Universal Language Selector's set of languages.
	 *
	 * TODO: Further decouple this from ULS. Make the languages known to Wikibase a config thing
	 *  and use ULS as source for that language information, then inject it into Wikibase upon
	 *  initialization. This way, everything beyond extension initialization doesn't have to know
	 *  about ULS.
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
