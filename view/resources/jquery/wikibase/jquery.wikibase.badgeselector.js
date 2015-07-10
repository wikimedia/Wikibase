/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( $, mw ) {
	'use strict';

var PARENT = $.ui.TemplatedWidget;

/**
 * References one single $menu instance that is reused for all badgeselector instances.
 * @type {jQuery}
 */
var $menu = null;

/**
 * Selector for toggling badges.
 * @since 0.5
 * @extends jQuery.ui.TemplatedWidget
 *
 * @option {string[]} [value]
 *         Item ids of badges currently assigned.
 *         Default: []
 *
 * @option {Object} [badges]
 *         All badges that may be assigned.
 *         Structure: {<{string} item id>: <{string} custom badge css classes>}
 *         Default: {}
 *
 * @option {wikibase.store.EntityStore} entityStore
 *
 * @option {string} languageCode
 *
 * @option {boolean} [isRTL]
 *         Whether the widget is displayed in right-to-left context.
 *         Default: false
 *
 * @option {Object} [messages]
 *         - badge-placeholder-title
 *           HTML title attribute of the placeholder displayed when no badge is selected.
 *
 * @event change
 *        - {jQuery.Event}
 *
 * @event afterstartediting
 *       - {jQuery.Event}
 *
 * @event afterstopediting
 *        - {jQuery.Event}
 *        - {boolean} Whether to drop the value.
 */
$.widget( 'wikibase.badgeselector', PARENT, {
	/**
	 * @see jQuery.ui.TemplatedWidget.options
	 */
	options: {
		template: 'wikibase-badgeselector',
		templateParams: [
			''
		],
		templateShortCuts: {},
		value: [],
		badges: {},
		entityStore: null,
		languageCode: null,
		isRtl: false,
		messages: {
			'badge-placeholder-title': 'Click to assign a badge.'
		}
	},

	/**
	 * @see jQuery.Widget._create
	 */
	_create: function() {
		if( !this.options.entityStore || !this.options.languageCode ) {
			throw new Error( 'Required option(s) missing' );
		}

		PARENT.prototype._create.call( this );

		this._createBadges();
		this._attachEventHandlers();
	},

	/**
	 * @see jQuery.Widget.destroy
	 */
	destroy: function() {
		if( $( '.' + this.widgetBaseClass ).length === 0 ) {
			this._detachMenuEventListeners();

			$menu.data( 'menu' ).destroy();
			$menu.remove();
			$menu = null;
		} else if( $menu && ( $menu.data( this.widgetName ) === this ) ) {
			this._detachMenuEventListeners();
		}
		this.element.removeClass( 'ui-state-active' );

		PARENT.prototype.destroy.call( this );
	},

	_attachEventHandlers: function() {
		var self = this;

		this.element
		.on( 'click.' + this.widgetName, function( event ) {
			if( !self.isInEditMode() || self.option( 'disabled' ) ) {
				return;
			}

			// If the menu is already visible, hide it
			if( $menu && $menu.data( self.widgetName ) === self && $menu.is( ':visible' ) ) {
				self._hideMenu();
				return;
			}

			self._initMenu()
			.done( function() {
				if( self.option( 'disabled' ) || $menu.is( ':visible' ) ) {
					$menu.hide();
					return;
				}

				$menu.data( self.widgetName, self );
				$menu.show();
				self.repositionMenu();
				self._attachMenuEventListeners();

				self.element.addClass( 'ui-state-active' );
			} );
		} );
	},

	_hideMenu: function() {
		$menu.hide();
		this._detachMenuEventListeners();

		this.element.removeClass( 'ui-state-active' );
	},

	_attachMenuEventListeners: function() {
		var self = this;
		var degrade = function( event ) {
			if( !$( event.target ).closest( self.element ).length &&
				!$( event.target ).closest( $menu ).length ) {
				self._hideMenu();
			}
		};

		$( document ).on( 'mouseup.' + this.widgetName, degrade );
		$( window ).on( 'resize.' + this.widgetName, function( event ) { self.repositionMenu(); } );

		$menu.on( 'click.' + this.widgetName, function( event ) {
			var $li = $( event.target ).closest( 'li' ),
				badge = $li.data( self.widgetName + '-menuitem-badge' );

			if( badge ) {
				self._toggleBadge( badge, $li.hasClass( 'ui-state-active' ) );
				$li.toggleClass( 'ui-state-active' );
			}
		} );
	},

	_detachMenuEventListeners: function() {
		$( document ).add( $( window ) ).off( '.' + this.widgetName );
		$menu.off( 'click.' + this.widgetName );
	},

	/**
	 * Creates the individual badges' DOM structures.
	 *
	 * @return {jQuery.Promise}
	 *         No resolved parameters.
	 *         No rejected parameters.
	 */
	_createBadges: function() {
		var deferred = $.Deferred();

		if( this.element.children( '.wb-badge' ).length ) {
			return deferred.resolve().promise();
		}

		if( !this.options.value.length && this.isInEditMode() ) {
			this._addEmptyBadge();
			return deferred.resolve().promise();
		}

		var self = this;

		for( var i = 0; i < this.options.value.length; i++ ) {
			this._addPlaceholderBadge( this.options.value[i] );
		}

		this.options.entityStore.getMultiple( this.options.value )
		.done( function( items ) {
			for( var i = 0; i < items.length; i++ ) {
				self._addBadge( items[ i ].getContent() );
			}
			deferred.resolve();
		} )
		.fail( function() {
			// TODO: Display error message
			deferred.reject();
		} );

		return deferred.promise();
	},

	/**
	 * Returns the static $menu including its instantiation if it has not been performed already.
	 *
	 * @return {jQuery}
	 */
	_getMenu: function() {
		if( $menu ) {
			return $menu;
		}

		$menu = $( '<ul/>' )
			.text( '...' )
			.addClass( this.widgetFullName + '-menu' )
			.appendTo( 'body' );

		return $menu.menu();
	},

	/**
	 * Fills the menu and activates the menu items of the badges already assigned.
	 *
	 * @return {jQuery.Promise}
	 *         No resolved parameters.
	 *         No rejected parameters.
	 */
	_initMenu: function() {
		var self = this,
			deferred = $.Deferred(),
			$menu = this._getMenu();

		self.repositionMenu();

		this._fillMenu()
		.done( function() {
			$menu.children( 'li' ).each( function() {
				var $li = $( this ),
					badgeId = $li.data( self.widgetName + '-menuitem-badge' );

				$li
				.addClass( 'ui-menu-item' )
				.toggleClass( 'ui-state-active', $.inArray( badgeId, self.value() ) !== -1 );
			} );

			$menu.hide();

			deferred.resolve();
		} )
		.fail( function() {
			deferred.reject();
		} );

		return deferred.promise();
	},

	/**
	 * Fills the menu with a menu item for each badge that may be assigned.
	 *
	 * @return {jQuery.Promise}
	 *         No resolved parameters.
	 *         No rejected parameters.
	 */
	_fillMenu: function() {
		var self = this,
			deferred = $.Deferred(),
			badgeIds = $.map( this.options.badges, function( cssClasses, itemId ) {
				return itemId;
			} );

		this.options.entityStore.getMultiple( badgeIds )
		.done( function( badges ) {
			$menu.empty();

			$.each( badgeIds, function( index, itemId ) {
				var item = badges[index] && badges[index].getContent();

				if( !item ) {
					return true;
				}

				var data = self._getBadgeDataForItem( item );
				var $item = $( '<a/>' )
					.on( 'click.' + self.widgetName, function( event ) {
						event.preventDefault();
					} )
					.text( data.label );

				$( '<li/>' )
				.addClass( self.widgetFullName + '-menuitem-' + itemId )
				.data( self.widgetName + '-menuitem-badge', itemId )
				.append( $item
					.prepend( mw.wbTemplate( 'wb-badge',
						data.cssClasses,
						data.label,
						itemId
					) )
				)
				.appendTo( $menu );
			} );

			deferred.resolve();
		} )
		.fail( function() {
			// TODO: Display error message.
			deferred.reject();
		} );

		return deferred;
	},

	/**
	 * @param {wikibase.datamodel.Item} item
	 *
	 * @return {Object} A plain object with values for keys label and cssClasses
	 */
	_getBadgeDataForItem: function( item ) {
		var term = item.getFingerprint().getLabelFor( this.options.languageCode ),
			itemId = item.getId();

		return {
			label: term ? term.getText() : itemId,
			cssClasses: itemId + ' ' + this.options.badges[ itemId ]
		};
	},

	/**
	 * (De-)Activates a badge.
	 *
	 * @param {string} badgeId
	 * @param {boolean} targetState
	 */
	_toggleBadge: function( badgeId, targetState ) {
		var self = this;
		if( targetState ) {
			this.element.children( '.wb-badge-' + badgeId ).remove();
			if( !this.element.children( '.wb-badge' ).length ) {
				this._addEmptyBadge();
			}

			this._trigger( 'change' );
		} else {
			this.options.entityStore.get( badgeId ).done( function( badgeItem ) {
				self._addBadge( badgeItem.getContent() );
				self._getEmptyBadge().remove();
				self._trigger( 'change' );
			} );
		}
	},

	/**
	 * Creates a placeholder badge to be displayed while loading the actual badge information. The
	 * placeholder will be replaced when calling this._addBadge() with the same badge id.
	 *
	 * @param {string} badgeId
	 */
	_addPlaceholderBadge: function( badgeId ) {
		if( this.element.children( '[data-wb-badge="' + badgeId + '"]' ).length ) {
			return;
		}
		this.element.append(
			mw.wbTemplate( 'wb-badge',
				badgeId + ' ' + this.options.badges[badgeId],
				badgeId,
				badgeId
			)
		);
	},

	/**
	 * @param {wikibase.datamodel.Item} badgeItem
	 */
	_addBadge: function( badgeItem ) {
		var badgeId = badgeItem.getId(),
			badgeData = this._getBadgeDataForItem( badgeItem ),
			$placeholderBadge = this.element.children( '[data-wb-badge="' + badgeId + '"]' );

		var $badge = mw.wbTemplate( 'wb-badge',
			badgeData.cssClasses,
			badgeData.label,
			badgeId
		);

		if( $placeholderBadge.length ) {
			$placeholderBadge.replaceWith( $badge );
		} else {
			this.element.append( $badge );
		}
	},

	/**
	 * Creates an empty badge to be displayed as menu anchor when no badges are selected.
	 */
	_addEmptyBadge: function() {
		this.element.append( mw.wbTemplate( 'wb-badge',
			'empty',
			this.options.messages['badge-placeholder-title'],
			''
		) );
	},

	/**
	 * @return {jQuery}
	 */
	_getEmptyBadge: function() {
		return this.element.children( '[data-wb-badge=""]' );
	},

	startEditing: function() {
		if( this.isInEditMode() ) {
			return;
		}

		if( !this.options.value.length ) {
			this._addEmptyBadge();
		}

		this.element.addClass( 'wb-edit' );

		this._trigger( 'afterstartediting' );
	},

	/**
	 * @param {boolean} dropValue
	 */
	stopEditing: function( dropValue ) {
		var self = this;

		if( !this.isInEditMode() ) {
			return;
		}

		this._getEmptyBadge().remove();

		if( $menu ) {
			$menu.hide();
		}

		this.element.removeClass( 'wb-edit' );

		if( !dropValue ) {
			self._trigger( 'afterstopediting', null, [dropValue] );
		} else {
			this.element.empty();

			for( var i = 0; i < this.options.value.length; i++ ) {
				this._addPlaceholderBadge( this.options.value[i] );
			}

			// Since the widget might have been initialized on pre-existing DOM, badges need to be
			// fetched to ensure their data is available for resetting:
			this.options.entityStore.getMultiple( this.options.value )
			.done( function( items ) {
				for( var i = 0; i < items.length; i++ ) {
					self._addBadge( items[ i ].getContent() );
				}
				self._trigger( 'afterstopediting', null, [dropValue] );
			} );
		}
	},

	/**
	 * @return {boolean}
	 */
	isInEditMode: function() {
		return this.element.hasClass( 'wb-edit' );
	},

	/**
	 * @param {string[]} value
	 * @return {string[]|*}
	 */
	value: function( value ) {
		if( value !== undefined ) {
			return this.option( 'value', value );
		}

		value = [];

		this.element.children( '.wb-badge' ).not( this._getEmptyBadge() ).each( function() {
			value.push( $( this ).data( 'wb-badge' ) );
		} );

		return value;
	},

	/**
	 * @see jQuery.ui.TemplatedWidget._setOption
	 */
	_setOption: function( key, value ) {
		var response = PARENT.prototype._setOption.apply( this, arguments );

		if( key === 'disabled' && $menu && $menu.data( this.widgetName ) === this ) {
			$menu.hide();
		} else if ( key === 'value' && this.isInEditMode() ) {
			this._initMenu();
		}

		return response;
	},

	/**
	 * Aligns the menu to the element the widget is initialized on.
	 */
	repositionMenu: function() {
		$menu.position( {
			of: this.element,
			my: ( this.options.isRtl ? 'right' : 'left' ) + ' top',
			at: ( this.options.isRtl ? 'right' : 'left' ) + ' bottom',
			offset: '0 1',
			collision: 'none'
		} );
	}
} );

}( jQuery, mediaWiki ) );
