/**
 * @file
 * @ingroup WikibaseLib
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 * @author Tobias Gritschacher
 * @author H. Snater < mediawiki@snater.com >
 * @author Marius Hoch < hoo@online.de >
 */
( function( mw, wb, $, undefined ) {
'use strict';

var PARENT = wb.EntityStore,
	REPO_API = mw.config.get( 'wbRepoUrl' ) + mw.config.get( 'wbRepoScriptPath' ) + '/api.php',
	LOCAL_API = mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php';

	if( LOCAL_API === REPO_API ) {
		// The current wiki *is* the repo so we can just use user.tokens to get the edit token
		mw.config.set( 'wbRepoEditToken', mw.user.tokens.get( 'editToken' ) );
	}

/**
 * Constructor to create an API object for interaction with the repo Wikibase API.
 * This implements the wikibase.EntityStore, so this represents the server sided store which will
 * be accessed via the API.
 * @constructor
 * @extends wb.EntityStore
 * @since 0.4 (since 0.3 as wb.Api without support for client usage)
 */
wb.RepoApi = wb.utilities.inherit( PARENT, {

	/**
	 * mediaWiki.Api object for internal usage. By having this initialized in the prototype, we can
	 * share one instance for all instances of the wikibase API.
	 * @type mw.Api
	 */
	_api: new mw.Api(),

	/**
	 * Creates a new entity with given data.
	 *
	 * @param {Object} [data] The entity data (may be omitted to create an empty entity)
	 * @return {jQuery.Promise}
	 */
	createEntity: function( data ) {
		var params = {
			action: 'wbeditentity',
			data: $.toJSON( data )
		};
		return this.post( params );
	},

	/**
	 * Edits an entity.
	 *
	 * @param {String} id Entity id
	 * @param {Number} baseRevId Revision id the edit shall be performed on
	 * @param {Object} data The entity's structure
	 * @param {Boolean} [clear] Whether to clear whole entity before editing (default: false)
	 * @return {jQuery.Promise}
	 */
	editEntity: function( id, baseRevId, data, clear ) {
		var params = {
			action: 'wbeditentity',
			id: id,
			baserevid: baseRevId,
			data: $.toJSON( data )
		};

		if ( clear ) {
			params.clear = true;
		}

		return this.post( params );
	},

	/**
	 * Gets one or more entities.
	 *
	 * @param {String[]|String} ids
	 * @param {String[]|String} [props] Key(s) of property/ies to retrieve from the API
	 *                          default: null (will return all properties)
	 * @param {String[]}        [languages]
	 *                          default: null (will return results in all languages)
	 * @param {String[]|String} [sort] Key(s) of property/ies to sort on
	 *                          default: null (unsorted)
	 * @param {String}          [dir] Sort direction may be 'ascending' or 'descending'
	 *                          default: null (ascending)
	 * @return {jQuery.Promise}
	 */
	getEntities: function( ids, props, languages, sort, dir ) {
		var params = {
			action: 'wbgetentities',
			ids: ids,
			props: this._normalizeParam( props ),
			languages: this._normalizeParam( languages ),
			sort: this._normalizeParam( sort ),
			dir: dir || undefined
		};

		return this.get( params );
	},

	/**
	 * Gets an entitiy which is linked with a given page.
	 *
	 * @param {String[]|String} [sites] Site(s) the given page is linked on
	 * @param {String[]|String} [titles] Linked page(s)
	 * @param {String[]|String} [props] Key(s) of property/ies to retrieve from the API
	 *                          default: null (will return all properties)
	 * @param {String[]}        [languages]
	 *                          default: null (will return results in all languages)
	 * @param {String[]|String} [sort] Key(s) of property/ies to sort on
	 *                          default: null (unsorted)
	 * @param {String}          [dir] Sort direction may be 'ascending' or 'descending'
	 *                          default: null (ascending)
	 * @return {jQuery.Promise}
	 */
	getEntitiesByPage: function( sites, titles, props, languages, sort, dir ) {
		var params = {
			action: 'wbgetentities',
			sites: this._normalizeParam( sites ),
			titles: this._normalizeParam( titles ),
			props: this._normalizeParam( props ),
			languages: this._normalizeParam( languages ),
			sort: this._normalizeParam( sort ),
			dir: dir || undefined
		};

		return this.get( params );
	},

	/**
	 * Sets the label of an entity via the API.
	 *
	 * @param {String} id entity id
	 * @param {Number} baseRevId revision id
	 * @param {String} label the label to set
	 * @param {String} language the language in which the label should be set
	 * @return {jQuery.Promise}
	 */
	setLabel: function( id, baseRevId, label, language ) {
		var params = {
			action: "wbsetlabel",
			id: id,
			value: label,
			language: language,
			baserevid: baseRevId
		};
		return this.post( params );
	},

	/**
	 * Sets the description of an entity via the API.
	 *
	 * @param {String} id entity id
	 * @param {Number} baseRevId revision id
	 * @param {String} description the description to set
	 * @param {String} language the language in which the description should be set
	 * @return {jQuery.Promise}
	 */
	setDescription: function( id, baseRevId, description, language ) {
		var params = {
			action: "wbsetdescription",
			id: id,
			value: description,
			language: language,
			baserevid: baseRevId
		};
		return this.post( params );
	},

	/**
	 * Adds and/or remove a number of aliases of an item via the API.
	 *
	 * @param {String} id entity id
	 * @param {Number} baseRevId revision id
	 * @param {String[]|String} add Alias(es) to add
	 * @param {String[]|String} remove Alias(es) to remove
	 * @param {String} language the language in which the aliases should be added/removed
	 * @return {jQuery.Promise}
	 */
	setAliases: function( id, baseRevId, add, remove, language ) {
		add = this._normalizeParam( add );
		remove = this._normalizeParam( remove );
		var params = {
			action: "wbsetaliases",
			id: id,
			add: add,
			remove: remove,
			language: language,
			baserevid: baseRevId
		};
		return this.post( params );
	},

	/**
	 * Creates a claim.
	 * @todo Needs testing. It would be necessary to create a property for creating a claim.
	 *       The API does not support setting a data type for an entity at the moment.
	 *
	 * @param {String} entityId Entity id
	 * @param {Number} baseRevId revision id
	 * @param {wb.Snak} mainSnak The new Claim's Main Snak.
	 * @return {jQuery.Promise} When resolved, the first parameter in callbacks is the saved
	 *         wb.Claim object which holds its final GUID.
	 *
	 * @throws {Error} If no Snak instance is given in the third parameter
	 */
	createClaim: function( entityId, baseRevId, mainSnak ) {
		return this._claimApiCall( baseRevId, mainSnak, {
			action: 'wbcreateclaim',
			entity: entityId
		} );
	},

	/**
	 * Removes an existing claim.
	 *
	 * @param {String} claimGuid The GUID of the Claim to be removed (wb.Claim.getGuid)
	 * @param {Number} baseRevId
	 * @return {jQuery.Promise} When resolved, the first parameter in callbacks is the related
	 *         page info which holds the revision ID of the related entity.
	 */
	removeClaim: function( claimGuid, baseRevId ) {
		var deferred = $.Deferred();

		this.post( {
			action: 'wbremoveclaims',
			claim: claimGuid
		} ).done( function( result ) {
			deferred.resolve( result.pageinfo );
		} ).fail( function() {
			deferred.reject.apply( deferred, arguments );
		} );

		return deferred.promise();
	},

	/**
	 * Changes the Main Snak of an existing claim.
	 * @todo Needs testing just like createClaim()!
	 *
	 * @param {String} claimGuid The GUID of the Claim to be changed (wb.Claim.getGuid)
	 * @param {Number} baseRevId
	 * @param {wb.Snak} mainSnak The new value to be set as the claims Main Snak.
	 * @return {jQuery.Promise} When resolved, the first parameter in callbacks is the changed
	 *         wb.Claim object with the updated Main Snak.
	 *
	 * @throws {Error} If no Snak instance is given in the third parameter
	 */
	setClaimValue: function( claimGuid, baseRevId, mainSnak ) {
		return this._claimApiCall( baseRevId, mainSnak, {
			action: 'wbsetclaimvalue',
			claim: claimGuid
		} );
	},

	/**
	 * Helper function for 'wbcreateclaim' and 'wbsetclaimvalue'. Both have very similar handling
	 * and both will return a $.Promise which returns information about the changed/created claim
	 * and the pageinfo in its callback.
	 *
	 * @param {Number} baseRevId
	 * @param {wb.Snak} mainSnak
	 * @param {Object} params 'action' and 'entity' or 'claim' parameter information
	 * @return {jQuery.Promise}
	 */
	_claimApiCall: function( baseRevId, mainSnak, params ) {
		if( !( mainSnak instanceof wb.Snak ) ) {
			throw new Error( 'A wikibase.Snak object is required as Main Snak' );
		}
		var snakJson = mainSnak.toJSON();

		$.extend( params, {
			baserevid: baseRevId,
			snaktype: mainSnak.getType(),
			// NOTE: currently 'wbsetclaimvalue' API allows to change snak type but not property,
			//  set it anyhow. Returned promise won't propagate the API warning we will get here.
			property: snakJson.property
		} );

		if( snakJson.datavalue !== undefined ) {
			params.value = $.toJSON( snakJson.datavalue.value );
		}

		return this._postAndPromiseWithAbstraction( params, function( result ) {
			return [
				wb.Claim.newFromJSON( result.claim ),
				result.pageinfo
			];
		} );
	},

	/**
	 * Will set a new Reference for a Statement.
	 *
	 * @since 0.4
	 *
	 * @param {string} statementGuid
	 * @param {wb.SnakList} snaks
	 * @param {number} baseRefId
	 * @param {string} [referenceHash] A hash of the reference that should be updated.
	 *        If not provided, a new reference is created.
	 * @return jQuery.Promise If resolved, this will get a wb.Reference object as first parameter
	 *         and the last base revision as second parameter.
	 */
	setReference: function( statementGuid, snaks, baseRevId, referenceHash ) {
		var params = {
			action: 'wbsetreference',
			statement: statementGuid,
			snaks: $.toJSON( snaks.toJSON() ),
			baserevid: baseRevId
		};

		if( referenceHash ) {
			params.reference = referenceHash;
		}

		return this._postAndPromiseWithAbstraction( params, function( result ) {
			return [
				wb.Reference.newFromJSON( result.reference ),
				result.pageinfo
			];
		} );
	},

	/**
	 * Will remove one or more existing References of a Statement.
	 *
	 * @since 0.4
	 *
	 * @param {string} statementGuid
	 * @param {string|string[]} referenceHashes One or more hashes of the References to be removed.
	 * @param {number} baseRefId
	 * @return jQuery.Promise Done callbacks will receive new base revision ID as first parameter.
	 */
	removeReferences: function( statementGuid, referenceHashes, baseRevId ) {
		var params = {
			action: 'wbremovereferences',
			statement: statementGuid,
			references: this._normalizeParam( referenceHashes ),
			baserevid: baseRevId
		};

		return this._postAndPromiseWithAbstraction( params, function( result ) {
			return [ result.pageinfo ];
		} );
	},

	/**
	 * Sets a site link for an item via the API.
	 *
	 * @param {String} id entity id
	 * @param {Number} baseRevId revision id
	 * @param {String} site the site of the link
	 * @param {String} title the title to link to
	 * @return {jQuery.Promise}
	 */
	setSitelink: function( id, baseRevId, site, title ) {
		var params = {
			action: "wbsetsitelink",
			id: id,
			linksite: site,
			linktitle: title,
			baserevid: baseRevId
		};
		return this.post( params );
	},

	/**
	 * Removes a sitelink of an item via the API.
	 *
	 * @param {String} id entity id
	 * @param {Number} baseRevId revision id
	 * @param {String} site the site of the link
	 * @return {jQuery.Promise}
	 */
	removeSitelink: function( id, baseRevId, site ) {
		return this.setSitelink( id, baseRevId, site, '' );
	},

	/**
	 * Set the required options and parameters for a repo call from a client, if needed
	 *
	 * @since 0.4
	 *
	 * @param {Object} params parameters for the API call
	 * @param {Object} ajax options
	 */
	_extendRepoCallParams: function( params, options ) {
		var localServerRaw = mw.config.get( 'wgServer' ).replace( /.*\/\//, '' ),
			repoServerRaw = mw.config.get( 'wbRepoUrl' ).replace( /.*\/\//, '' ).replace( /\/.*/, '' );

		options.url = REPO_API;

		if ( localServerRaw === repoServerRaw ) {
			// We don't need/ want CORS when on the same domain
			return;
		}

		var currentServer = mw.config.get( 'wgServer' );
		if ( currentServer.indexOf( '//' ) === 0 ) {
			// The origin parameter musn't be protocol relative
			currentServer = document.location.protocol + currentServer;
		}

		params.origin = currentServer;
	},

	/**
	 * Converts the given value into a string usable by the API
	 *
	 * @since 0.4
	 *
	 * @param {Mixed} value
	 * @return {string|undefined}
	 */
	_normalizeParam: function( value ) {
		return $.isArray( value ) ? value.join( '|' ) : ( value || undefined );
	},

	/**
	 * Submits the AJAX request to the API of the repo and triggers on the response. This will
	 * automatically add the required 'token' information for editing into the given parameters
	 * sent to the API.
	 * @see mw.Api.post
	 *
	 * @param {Object} params parameters for the API call
	 * @return {jQuery.Promise}
	 *
	 * @throws {Error} If a parameter is not specified properly
	 */
	post: function( params ) {
		var options = {};
		this._extendRepoCallParams( params, options );

		$.each( params, function( key, value ) {
			if ( value === undefined || value === null ) {
				throw new Error( 'Parameter "' + key + '" is not specified properly.' );
			}
		} );

		if ( mw.config.exists( 'wbRepoEditToken' ) ) {
			// Easy one: wbRepoEditToken is already set
			params.token = mw.config.get( 'wbRepoEditToken' );
			return this._api.post( params, options );
		} else {
			// Get wbRepoEditToken and go on with the actual request then
			var repoPostDeffered = new $.Deferred();
			var self = this;

			this.get( {
				action: 'query',
				intoken: 'edit',
				titles: 'Main page',
				prop: 'info',
				indexpageids: 1
			} )
			.fail( repoPostDeffered )
			.done( function( data ) {
				// Now we got wbRepoEditToken
				mw.config.set( 'wbRepoEditToken', data.query.pages[ data.query.pageids[0] ].edittoken );

				params.token = mw.config.get( 'wbRepoEditToken' );

				self._api.post( params, options )
					.fail( repoPostDeffered.reject )
					.done( repoPostDeffered.resolve );
			} );
			return repoPostDeffered.promise();
		}
	},

	/**
	 * Same as post() but will return a jQuery.Promise which will resolve with some different
	 * arguments, specified in a callback.
	 *
	 * @since 0.4
	 *
	 * @param params
	 * @param {Function} callbackForAbstraction Called when post request is resolved, will get all
	 *        parameters the original resolved post promise gets. Can return an array whose members
	 *        will then serve as parameters for the public promise.
	 * @return jQuery.Promise
	 */
	_postAndPromiseWithAbstraction: function( params, callbackForAbstraction ) {
		var deferred = $.Deferred(),
			self = this;

		this.post( params )
		.done( function() {
			var args = callbackForAbstraction.apply( self, arguments );
			deferred.resolve.apply( deferred, args );
		} )
		.fail( deferred.reject );

		return deferred.promise();
	},

	/**
	 * Performs an API get request.
	 * @see mw.Api.get
	 * @since 0.3
	 *
	 * @param {Array} params
	 */
	'get': function( params ) {
		var options = {};
		this._extendRepoCallParams( params, options );

		return this._api.get( params, options );
	}

} );

	// TODO: step by step implementation of the store, starting with basic claim stuff

}( mediaWiki, wikibase, jQuery ) );
