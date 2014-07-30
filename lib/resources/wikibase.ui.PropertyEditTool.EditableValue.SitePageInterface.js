/**
 * @licence GNU GPL v2+
 * @author H. Snater <mediawiki@snater.com>
 * @author Daniel Werner
 */
( function( mw, wb, util, $ ) {
'use strict';
/* jshint camelcase: false */

var PARENT = wb.ui.PropertyEditTool.EditableValue.Interface;

/**
 * Serves the input interface to choose a wiki page from some MediaWiki installation as part of an
 * editable value
 * @constructor
 * @see wb.ui.PropertyEditTool.EditableValue.Interface
 * @since 0.1
 *
 * @param jQuery subject
 */
wb.ui.PropertyEditTool.EditableValue.SitePageInterface = util.inherit( PARENT, {
	/**
	 * Url the AJAX request will point to (if ajax should be used to define a result set).
	 * @type {string}
	 */
	url: null,

	/**
	 * Additional params for the AJAX request (if ajax should be used to define a result set)
	 * @type {Object}
	 */
	ajaxParams: null,

	/**
	 * Information for which site this autocomplete interface should serve input suggestions
	 * @var wikibase.Site
	 */
	_site: null,

	/**
	 * Current result set of strings used for validation.
	 * @type {string[]}
	 */
	_currentResults: null,

	/**
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._init
	 *
	 * @param {jQuery} subject
	 * @param {Object} options
	 * @param {wikibase.Site} site as source for the page suggestions
	 */
	_init: function( subject, options, site ) {
		PARENT.prototype._init.apply( this, arguments );

		this._currentResults = [];

		if( site ) {
			this.setSite( site );
		}
	},

	/**
	 * Creates the input element.
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface._buildInputElement
	 */
	_buildInputElement: function() {
		var self = this;

		var $input = PARENT.prototype._buildInputElement.call( this );

		$input.suggester( {
			source: this._request()
		} );

		this._initBadgeEditing();

		$input
		.on( 'keyup close', function( event ) {
			// The input element might have been removed already with the event still being in the
			// loop:
			if ( !self.isDisabled() && self._inputElem !== null ) {
				self._onInputRegistered();
			}
		} )
		.on( 'suggesterresponse suggesterselect', function( event, results ) {
			self._currentResults = results;
			if ( !self.isDisabled() ) {
				self._onInputRegistered();
			}
		} )
		.on( 'suggestererror', function( event, textStatus, errorThrown ) {
			if ( textStatus !== 'abort' ) {
				var error = {
					code: textStatus,
					message: mw.msg( 'wikibase-error-autocomplete-connection' ),
					detailedMessage: mw.msg( 'wikibase-error-autocomplete-response', errorThrown )
				};
				self._inputElem.wbtooltip( {
					content: error,
					permanent: true,
					gravity: 'nw'
				} );
				self._inputElem.data( 'wbtooltip' ).show();
			}
		} );

		return $input;
	},

	/**
	 * Make it possible to change badges.
	 *
	 * 1. If badges exist for the current sitelink, link them to Special:SetSiteLink
	 *     where badges can be changed.
	 * 2. If no badges exist for the given sitelink, then add an empty dummy badge
	 *     that also links to Special:SetSiteLink where badges can be added.
	 */
	_initBadgeEditing: function() {
		if ( $.isEmptyObject( mw.config.get( 'wbBadgeItems' ) ) ) {
			return;
		}

		var setSiteLinkUrl = this._getSetSiteLinkUrl(),
			$badgeSpan = this._subject.prev( '.wb-sitelinks-badges' );

		if ( !setSiteLinkUrl ) {
			// For some reason we can't create a URL to Special:SetSiteLink
			// probably we're in some kind of incomplete state.
			// This happens when adding a new sitelink.
			return;
		}

		if ( !$badgeSpan.find( 'span' ).length ) {
			// Insert an empty dummy badge
			$badgeSpan.append(
				$( '<span>' )
					.addClass( 'wb-badge wb-badge-empty' )
					.attr( 'title', mw.msg( 'wikibase-add-badges' ) )
			);
		}

		$badgeSpan.find( 'span' ).each( function( i, badge ) {
			$( badge ).wrap(
				$( '<a>' ).attr( 'href', setSiteLinkUrl )
			);
		} );

	},

	/**
	 * Disable editing badges
	 */
	_disableBadgeEditing: function() {
		if ( $.isEmptyObject( mw.config.get( 'wbBadgeItems' ) ) ) {
			return;
		}

		var $badgeSpan = this._subject.prev( '.wb-sitelinks-badges' );

		$badgeSpan.find( 'a > span' ).each( function( i, badge ) {
			$( badge ).unwrap();
		} );

		$badgeSpan.find( 'span.wb-badge-empty' ).remove();
	},

	/**
	 * Generates the suggester source function.
	 */
	_request: function() {
		var self = this;

		return function( term ) {
			var deferred = $.Deferred();

			$.ajax( {
				url: self._site.getApi(),
				dataType: 'jsonp',
				data: {
					search: term,
					action: 'opensearch'
				},
				timeout: 8000
			} )
			.done( function( response ) {
				deferred.resolve( response[1], response[0] );
			} )
			.fail( function( jqXHR, textStatus ) {
				// Since this is a JSONP request, this will always fail with a timeout...
				deferred.reject( textStatus );
			} );

			return deferred.promise();
		};
	},

	/**
	 * Allows to set the site, the pages should be selected from.
	 *
	 * @param wb.Site site
	 */
	setSite: function( site ) {
		if( this._site !== null && this._site.getId() === site.getId() ) {
			return; // no change
		}

		this._site = site;

		this._currentResults = []; // empty current suggestions...
	},

	/**
	 * @see wb.ui.PropertyEditTool.EditableValue.Interface.updateLanguageAttributes
	 */
	updateLanguageAttributes: function() {
		PARENT.prototype.updateLanguageAttributes.call( this );
		// apply input's language attributes or attributes according to user language
		if ( this._inputElem !== null ) {
			var lang = this._inputElem.attr( 'lang' );
			if ( lang === undefined ) {
				lang = mw.config.get( 'wgUserLanguage' );
			}
			var dir = this._inputElem.attr( 'dir' );
			if ( dir === undefined ) {
				if ( wb.getLanguages()[lang] !== undefined ) {
					dir = $.uls.data.getDir( lang );
				}
			}
			if ( dir === undefined ) {
				dir = 'auto'; // Shouldn't happen, but go figure
			}
			this._inputElem.data( 'suggester' ).options.menu.element
				.attr( 'lang', lang ).attr( 'dir', dir );
		}
	},

	/**
	 * Just a dummy to override the validation of the page
	 * For now the validation is done by the API only
	 * @param value String page
	 * @return String page
	 */
	validate: function( value ) {
		return value;
	},

	/**
	 * Checks whether a value is in the list of current suggestions (case-insensitive) and returns
	 * the value in the normalized form from the list.
	 * @see wikibase.ui.PropertyEditTool.EditableValue.Interface.normalize
	 *
	 * @param {string} value
	 * @return {string|null} Actual string found within the result set or null for invalid values.
	 */
	normalize: function( value ) {
		return this._normalize_fromCurrentResults( value );
	},

	/**
	 * Checks whether the value is in the list of current suggestions (case-insensitive).
	 *
	 * @param {string} value
	 * @return {string|null}
	 */
	_normalize_fromCurrentResults: function( value ) {
		var match = this._getResultSetMatch( value );

		if( match === null ) {
			// Not found, return string "unnormalized" but do not return null since it could still
			// be valid.
			return value;
		} else {
			return match;
		}
	},

	/**
	 * Returns a value from the result set if it equals the one given.
	 * null in case the value doesn't exist within the result set.
	 *
	 * @return {string|null}
	 */
	_getResultSetMatch: function( value ) {
		value = $.trim( value ).toLowerCase();

		for( var i in this._currentResults ) {
			if( $.trim( this._currentResults[i] ).toLowerCase() === value ) {
				// Return the original from suggestions:
				return this._currentResults[i];
			}
		}
		return null;
	},

	/**
	 * Returns the site set to select pages from.
	 *
	 * @return wb.Site site
	 */
	getSite: function() {
		return this._site;
	},

	_setValue_inNonEditMode: function( value ) {
		this._getValueContainer()
		.empty()
		.append( // insert link to site in site
			this._site && value !== '' ? this._site.getLinkTo( value ) : ''
		);
	},

	/**
	 * @see wb.ui.PropertyEditTool.EditableValue.Interface._destroy
	 */
	_destroy: function() {
		PARENT.prototype._destroy.call( this );
		this._site = null;
	},

	/**
	 * @see wikibase.utilities.ui.StatableObject._setState
	 *
	 * @param {number} state (see wikibase.ui.PropertyEditTool.EditableValue.STATE)
	 * @return {boolean} Whether the desired state has been applied (or had been applied already).
	 */
	_setState: function( state ) {
		var success = PARENT.prototype._setState.call( this, state );
		if( this._inputElem !== null ) {
			if( state === this.STATE.DISABLED ) {
				this._inputElem.data( 'suggester').disable();
			} else {
				this._inputElem.data( 'suggester').enable();
			}
		}
		return success;
	},

	/**
	 * Get the URL for editing the site link on Special:SetSiteLink.
	 *
	 * @return {string}
	 */
	_getSetSiteLinkUrl: function() {
		var entityId = this._subject.parents( '.wb-entity' ).attr( 'id' ).replace( 'wb-item-', '' );

		if ( !entityId || !this.getSite() ) {
			return '';
		}

		return mw.util.getUrl(
			'Special:SetSiteLink/' + entityId + '/' + this.getSite().getGlobalSiteId()
		);
	},

	/**
	 * Destroys the edit box and displays the original text or the inputs new value.
	 *
	 * @param {bool} save whether to save the new user given value
	 * @return bool whether the value has changed compared to the original value
	 */
	stopEditing: function( save ) {
		this._disableBadgeEditing();

		return PARENT.prototype.stopEditing.call( this, save );
	}

} );

} )( mediaWiki, wikibase, util, jQuery );
