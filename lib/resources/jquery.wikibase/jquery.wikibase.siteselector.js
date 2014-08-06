/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki at snater.com >
 */
( function( $ ) {
	'use strict';

	/**
	 * Site selector
	 * Enhances an input box with auto-complete and auto-suggestion functionality for site ids.
	 * @since 0.2
	 *
	 * @example $( 'input' ).siteselector( { source: <{wb.Site[]}> } );
	 *
	 * @event selected
	 *        Triggered whenever a site is selected or de-selected.
	 *        (1) {jQuery.Event}
	 *        (2) {string|null}
	 */
	$.widget( 'wikibase.siteselector', $.ui.suggester, {
		/**
		 * @see jQuery.ui.suggester.options
		 */
		options: {
			delay: 0
		},

		/**
		 * @type {wikibase.Site}
		 */
		_selectedSite: null,

		/**
		 * @see jQuery.ui.suggester._create
		 */
		_create: function() {
			var self = this;

			$.ui.suggester.prototype._create.apply( this, arguments );

			this.element
			.on( 'keydown.' + this.widgetName, function( event ) {
				if( event.keyCode === $.ui.keyCode.TAB ) {
					$( self.options.menu )
					.one( 'selected', function( event, item ) {
						self.element.val( item.getValue() );
					} );
				}
			} )
			.on( 'eachchange.' + this.widgetName, function( event, previousValue ) {
				self._selectedSite = null;
				self._term = self.element.val();

				clearTimeout( self.__searching );
				self.search()
				.done( function( suggestions ) {
					if( self.options.menu.element.is( ':visible' ) ) {
						self._selectFirstSite();
					} else {
						self._trigger( 'selected', null, [null] );
					}
				} );
			} )
			.on( 'siteselectoropen.' + this.widgetName, function() {
				self._selectFirstSite();
			} );
		},

		/**
		 * @see jQuery.ui.suggester.destroy
		 */
		destroy: function() {
			$( this.options.menu ).off( 'siteselector' );
			$.ui.suggester.prototype.destroy.call( this );
		},

		/**
		 * Implicitly selects the first site from the suggested sites.
		 */
		_selectFirstSite: function() {
			var menu = this.options.menu,
				menuItems = menu.option( 'items' );

			if( menuItems.length > 0 && menu.element.is( ':visible' ) ) {
				this.options.menu.activate( menuItems[0] );

				if( this._selectedSite && this._selectedSite === menuItems[0].getSite() ) {
					return;
				}

				this._selectedSite = menuItems[0].getSite();
			}

			this._trigger(
				'selected',
				null,
				menuItems.length ? [menuItems[0].getSite().getId()] : null
			);
		},

		/**
		 * @see jQuery.ui.suggester._initMenu
		 */
		_initMenu: function( ooMenu ) {
			var self = this;

			$.ui.suggester.prototype._initMenu.apply( this, arguments );

			this.options.menu.element.addClass( 'wikibase-siteselector-list' );

			$( this.options.menu )
			.on( 'selected.siteselector', function( event, item ) {
				if( item instanceof $.wikibase.siteselector.Item ) {
					self._selectedSite  = item.getSite();
				}
			} )
			.on( 'blur.siteselector', function() {
				if( self.element.val() !== '' ) {
					self._selectFirstSite();
				}
			} );

			this.options.menu.element
			.on( 'mouseleave', function() {
				if( self.options.menu.element.is( ':visible' ) ) {
					self._selectedSite = null;
					self._selectFirstSite();
				}
			} );

			return ooMenu;
		},

		/**
		 * @see jQuery.ui.suggester._move
		 */
		_move: function( direction, activeItem, allItems ) {
			$.ui.suggester.prototype._move.apply( this, arguments );
			if( this._selectedSite === this.options.menu.getActiveItem().getSite() ) {
				this.element.val( this._term );
			}
		},

		/**
		 * @see jQuery.ui.suggester._moveOffEdge
		 */
		_moveOffEdge: function( direction ) {
			if( direction === 'previous' ) {
				var menu = this.options.menu,
					items = menu.option( 'items' );
				menu.activate( items[items.length - 1] );
				this.element.val( items[items.length - 1].getValue() );
			} else {
				$.ui.suggester.prototype._moveOffEdge.apply( this, arguments );
				this._selectedSite = null;
				this._selectFirstSite();
			}
		},

		/**
		 * @see jQuery.ui.suggester._getSuggestionsFromArray
		 */
		_getSuggestionsFromArray: function( term, source ) {
			var self = this,
				deferred = $.Deferred();

			if( term === '' ) {
				return deferred.resolve( [] ).promise;
			}

			var suggestedSites = $.grep( source, function( site ) {
				var check = [
					site.getId(),
					site.getShortName(),
					site.getName(),
					site.getShortName() + ' (' + site.getId() + ')'
				];

				for( var i = 0; i < check.length; i++ ) {
					if( check[i].toLowerCase().indexOf( self._term.toLowerCase() ) === 0 ) {
						return true;
					}
				}

				return false;
			} );

			return deferred.resolve( suggestedSites ).promise();
		},

		/**
		 * @see jQuery.ui.suggester._getSuggestionsFromArray
		 */
		_createMenuItemFromSuggestion: function( suggestion ) {
			var value = suggestion.getShortName() + ' (' + suggestion.getId() + ')';
			return new $.wikibase.siteselector.Item( value, value, suggestion );
		},

		/**
		 * Returns the currently selected site.
		 *
		 * @return {wikibase.Site|null}
		 */
		getSelectedSite: function() {
			return this._selectedSite;
		},

		/**
		 * Sets the selected site.
		 *
		 * @param {wikibase.Site} site
		 */
		setSelectedSite: function( site ) {
			this._selectedSite = site;
		}

	} );

/**
 * Default siteselector suggestion menu item.
 * @constructor
 * @extends jQuery.ui.ooMenu.Item
 *
 * @param {string|jQuery} label
 * @param {string} value
 * @param {wikibase.Site} site
 *
 * @throws {Error} if a required parameter is not specified.
 */
var Item = function( label, value, site ) {
	if( !label || !value || !site ) {
		throw new Error( 'Required parameter(s) not specified' );
	}

	this._label = label;
	this._value = value;
	this._site = site;
};

Item = util.inherit(
	$.ui.ooMenu.Item,
	Item,
	{
		/**
		 * @type {wikibase.Site}
		 */
		_site: null,

		/**
		 * @return {wikibase.Site}
		 */
		getSite: function() {
			return this._site;
		}
	}
);

$.extend( $.wikibase.siteselector, {
	Item: Item
} );

} )( jQuery );
