/**
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 */
( function( mw, wb, $ ) {
	'use strict';

	var MODULE = wb.store;

	/**
	 * Constructor for objects representing some kind of MediaWiki content fetched from a store.
	 * Holds the actual content as well as the related title where the content can be found on the wiki.
	 *
	 * TODO: This is just a very basic wrapper for contents right now. This could very will grow
	 *       into something more sophisticated system which is aware of different content models,
	 *       which would probably result into one constructor (or strategy) per content type.
	 *       E.g. "FetchedEntity", etc.
	 *
	 * @param {Object} data Should contain the following fields:
	 *        - content {*} The content which has been fetched
	 *        - title {mw.Title} The page the content is related to
	 *
	 * @constructor
	 * @since 0.4
	 */
	var SELF = MODULE.FetchedContent = function WbFetchedContent( data ) {
		if( typeof data !== 'object' || !data.hasOwnProperty( 'content' ) ) {
			throw new Error( 'Can not create fetched content without content in field "content"' );
		}
		if( !( data.title instanceof mw.Title ) ) {
			throw new Error( 'Can not create fetched content without related title information in field "title"' );
		}
		data = $.extend( {}, data ); // kill reference, prevent from outside modification

		this._data = data;
	};

	$.extend( SELF.prototype, {
		/**
		 * Returns the actual content.
		 *
		 * @since 0.4
		 *
		 * @return {*}
		 */
		getContent: function() {
			return this._data.content;
		},

		/**
		 * Returns the title on the wiki, related to the content.
		 *
		 * TODO: It is not clear from which Wiki the content comes. Currently it is assumed that the
		 *       content comes from the local wiki, so mw.Title can be used without any problems.
		 *       Design-wise it would be better if some context, pointing to the wiki, would be
		 *       injected into the constructor. Except for the sites table (which doesn't hold
		 *       enough information to create mw.Title objects for other wikis), there is no existing
		 *       system we could use for this and the sites table is not fully implemented in
		 *       JavaScript anyhow.
		 *
		 * @since 0.4
		 *
		 * @return mw.Title
		 */
		getTitle: function() {
			return this._data.title;
		}
	} );

}( mediaWiki, wikibase, jQuery ) );
