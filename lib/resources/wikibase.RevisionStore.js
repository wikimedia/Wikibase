/**
 * JavaScript storing revision ids about different sections.
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Tobias Gritschacher
 */
( function( mw, wb, $ ) {
'use strict';

/**
 * Offers access to stored revision ids
 * @constructor
 * @since 0.1
 */
wb.RevisionStore = function( baseRev ) {
	this._revisions = {
		baseRevision: baseRev,
		labelRevision: baseRev,
		descriptionRevision: baseRev,
		aliasesRevision: baseRev,
		sitelinksRevision: [],
		claimRevisions: []
	};
};

wb.RevisionStore.prototype = {
	/**
	 * Returns the base revision id.
	 */
	getBaseRevision: function() {
		return this._revisions.baseRevision;
	},

	/**
	 * Returns the label revision id.
	 */
	getLabelRevision: function() {
		return this._revisions.labelRevision;
	},

	/**
	 * Returns the description revision id.
	 */
	getDescriptionRevision: function() {
		return this._revisions.descriptionRevision;
	},

	/**
	 * Returns the aliases revision id.
	 */
	getAliasesRevision: function() {
		return this._revisions.aliasesRevision;
	},

	/**
	 * Returns the sitelinks revision id.
	 */
	getSitelinksRevision: function( lang ) {
		if( this._revisions.sitelinksRevision[lang] === undefined ) {
			return this._revisions.baseRevision;
		}
		return this._revisions.sitelinksRevision[lang];
	},

	/**
	 * Returns the claim revision id.
	 */
	getClaimRevision: function( claimGuid ) {
		if( this._revisions.claimRevisions[claimGuid] === undefined ) {
			return this._revisions.baseRevision;
		}
		return this._revisions.claimRevisions[claimGuid];
	},

	/**
	 * Saves the label revision id.
	 */
	setLabelRevision: function( rev ) {
		this._revisions.labelRevision = rev;
	},

	/**
	 * Saves the description revision id.
	 */
	setDescriptionRevision: function( rev ) {
		this._revisions.descriptionRevision = rev;
	},

	/**
	 * Saves the aliases revision id.
	 */
	setAliasesRevision: function( rev ) {
		this._revisions.aliasesRevision = rev;
	},

	/**
	 * Saves the sitelinks revision id.
	 */
	setSitelinksRevision: function( rev, lang ) {
		this._revisions.sitelinksRevision[lang] = rev;
	},

	/**
	 * Saves the claim revision id.
	 */
	setClaimRevision: function( rev, claimGuid ) {
		this._revisions.claimRevisions[claimGuid] = rev;
	}

};
} )( mediaWiki, wikibase, jQuery );
