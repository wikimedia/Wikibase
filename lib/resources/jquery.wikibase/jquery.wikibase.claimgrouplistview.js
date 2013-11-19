/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	var PARENT = $.TemplatedWidget;

/**
 * View for displaying claim groups (claimlistviews).
 * @since 0.5
 * @extends jQuery.TemplatedWidget
 *
 * @option {wikibase.Claim[]|null} value The claims to be displayed by this view. If null, the view
 *         will display only an add button to add new claims.
 *         Default: null
 *
 * @option {string} entityType Type of the entity that the claimgrouplistview referes to.
 *         Default: wikibase.Item.type
 */
$.widget( 'wikibase.claimgrouplistview', PARENT, {
	widgetBaseClass: 'wb-claimgrouplistview',

	/**
	 * (Additional) default options.
	 * @see jQuery.Widget.options
	 */
	options: {
		template: 'wb-claimgrouplistview',
		templateParams: [
			'', // claimlistview widgets
			'' // toolbar
		],
		templateShortCuts: {
			$listview: '.wb-claimlists'
		},
		value: null,
		entityType: wikibase.Item.type
	},

	/**
	 * @see jQuery.Widget._create
	 */
	_create: function() {
		PARENT.prototype._create.call( this );

		var self = this,
			claims = this.option( 'value' );

		this._createClaimGroupListview();

		var listview = this.listview(),
			lia = listview.listItemAdapter(),
			startEditingEvent = lia.prefixedEvent( 'startediting.' + this.widgetName ),
			afterStopEditingEvent = lia.prefixedEvent( 'afterstopediting.' + this.widgetName ),
			afterRemoveEvent = lia.prefixedEvent( 'afterremove.' + this.widgetName ),
			errorEvent = lia.prefixedEvent( 'toggleerror.' + this.widgetName );

		this.element
		.on( 'listviewitemadded.' + this.widgetName, function( event, value, $claimlistview ) {
			// Build group title.
			var $listview = $( event.target );

			// Be sure to only react on the claimgrouplistview's listview event:
			if( $listview.parent().get( 0 ) !== self.element.get( 0 ) ) {
				return;
			}

			// Build group title (no need to when entering a new list item):
			if( value ) {
				var propertyId = value[0].getMainSnak().getPropertyId();

				$claimlistview.append( self._createGroupTitle( propertyId ) );
			}
		} )
		.on( errorEvent, function( event, dropValue ) {
			self._toggleGroupTitleClass( $( event.target ), 'wb-error' );
		} )
		.on( afterStopEditingEvent, function( event, dropValue ) {
			self._toggleGroupTitleClass( $( event.target ), 'wb-error' );
			self._removeGroupTitleClass( $( event.target ), 'wb-edit' );
		} )
		.on( startEditingEvent, function( event ) {
			self._addGroupTitleClass( $( event.target ), 'wb-edit' );
		} )
		.on( afterRemoveEvent, function( event, value, $claimview ) {
			// Check whether the whole claimlistview may be removed from the claimgrouplistview if
			// the last claimview has been removed from the claimlistview.
			var $claimlistview = $( event.target ),
				claimlistview = lia.liInstance( $claimlistview );

			if( !claimlistview.value() ) {
				self.listview().removeItem( $claimlistview );
			}
		} );

		if( claims ) {
			this._initClaims( claims  );
		}
	},

	/**
	 * Fills the listview with the initially passed claims by ordering the claims according to their
	 * property.
	 * @since 0.5
	 *
	 * @param {wikibase.Claim[]} claims
	 *
	 * @todo This method should not be necessary because this.option( 'value' ) should already
	 * contain proper order information to directly feed the listview.
	 */
	_initClaims: function( claims ) {
		var propertyOrder = [],
			claimsByProperty = {},
			i;

		for( i = 0; i < claims.length; i++ ) {
			var propertyId = claims[i].getMainSnak().getPropertyId();

			if( $.inArray( propertyId, propertyOrder ) === -1 ) {
				propertyOrder.push( propertyId );
				claimsByProperty[propertyId] = [];
			}

			claimsByProperty[propertyId].push( claims[i] );
		}

		for( i = 0; i < propertyOrder.length; i++ ) {
			this.listview().addItem( claimsByProperty[propertyOrder[i]] );
		}
	},

	/**
	 * @see jQuery.Widget.destroy
	 */
	destroy: function() {
		this.listview().destroy();
		PARENT.prototype.destroy.call( this );
	},

	/**
	 * Creates the listview that contains any number of claimlistviews.
	 * @since 0.5
	 */
	_createClaimGroupListview: function() {
		var self = this;

		function indexOf( claimsSubset, claims ) {
			if(
				!$.isArray( claimsSubset ) || !claimsSubset.length
				|| !$.isArray( claims ) || !claims.length
			) {
				return null;
			}

			var index = $.inArray( claimsSubset[0], claims );
			return ( index !== -1 ) ? index : null;
		}

		this.$listview.listview( {
			listItemAdapter: new $.wikibase.listview.ListItemAdapter( {
				listItemWidget: $.wikibase.claimlistview,
				listItemWidgetValueAccessor: 'value',
				newItemOptionsFn: function( value ) {
					return {
						value: value,
						entityType: self.option( 'entityType' ),
						firstClaimIndex: indexOf( value, self.option( 'value' ) )
					};
				}
			} )
		} );
	},

	/**
	 * Creates the title for a group of claimlists.
	 * @since 0.5
	 *
	 * @param {string} propertyId
	 * @return {jQuery}
	 */
	_createGroupTitle: function( propertyId ) {
		var fetchedProperty = wb.fetchedEntities[propertyId],
			$title;

		if( fetchedProperty ) {
			// Default: Create a link to the property to be used as group title.
			$title = wb.utilities.ui.buildLinkToEntityPage(
				fetchedProperty.getContent(),
				fetchedProperty.getTitle().getUrl()
			);
		} else {
			// The claims group features a property that has been deleted.
			$title = wb.utilities.ui.buildMissingEntityInfo( propertyId, wb.Property );
		}

		return mw.template( 'wb-claimgrouplistview-groupname', $title );
	},

	/**
	 * Toggles a specific css class on the group title node.
	 * @since 0.5
	 *
	 * @param {jQuery} $claimlistview
	 * @param {string} cssClass
	 */
	_toggleGroupTitleClass: function( $claimlistview, cssClass ) {
		var selector =  '.' + cssClass + ':not(.wb-claimgrouplistview-groupname)',
			action = $claimlistview.find( selector ).length
				? '_addGroupTitleClass'
				: '_removeGroupTitleClass';

		this[action]( $claimlistview, cssClass );
	},

	/**
	 * Adds a specific css class to the group title node.
	 * @since 0.5
	 *
	 * @param {jQuery} $claimlistview
	 * @param {string} cssClass
	 */
	_addGroupTitleClass: function( $claimlistview, cssClass ) {
		var $groupTitle = $claimlistview.children( '.wb-claimgrouplistview-groupname' );
		$groupTitle.addClass( cssClass );
	},

	/**
	 * Removes a specific css class from the group title node.
	 * @since 0.5
	 *
	 * @param {jQuery} $claimlistview
	 * @param {string} cssClass
	 */
	_removeGroupTitleClass: function( $claimlistview, cssClass ) {
		var $groupTitle = $claimlistview.children( '.wb-claimgrouplistview-groupname' );
		$groupTitle.removeClass( cssClass );
	},

	/**
	 * Returns the listview widget containing the claimlistviews managed by the claimgrouplistview.
	 * @since 0.5
	 *
	 * @return {jquery.wikibase.listview}
	 */
	listview: function() {
		return this.$listview.data( 'listview' );
	},

	/**
	 * Triggers entering a new claimlistview to the claimgrouplistview. This involves triggering
	 * the corresponding process for the new pending claimlistview by triggering the claimlistview's
	 * enterNewItem() method that instantiates a pending claimview to be added to the pending
	 * claimview which itself is added to the claimgrouplistview.
	 * @since 0.5
	 */
	enterNewItem: function() {
		var self = this,
			lia = this.listview().listItemAdapter();

		this.element
		.one( 'listviewenternewitem.' + this.widgetName, function( event, $claimlistview ) {
			var afterStopEditingEvent = lia.prefixedEvent( 'afterstopediting.' + self.widgetName );

			$claimlistview
			.addClass( 'wb-new' )
			.one( afterStopEditingEvent, function( event, dropValue ) {
				var $claimlistview = $( event.target );

				if( dropValue ) {
					self.listview().removeItem( $claimlistview );
					return;
				}

				// A new claim(list) has been saved successfully. If the new claimlist features a
				// property that already is represented by a claimlistview, the new claimlist's
				// claims have to be appended to it. If there is claimlistview featuring the
				// property yet, a new claimlistview is added.
				// TODO: Do not re-add the claimlistview to the claimgrouplistview if there is not
				// claimlistview featuring the specific property yet. Instead, use the already
				// existing pending claimlistview.
				// TODO: Assume that there are more than one item to be added.
				var newClaims = lia.liInstance( $claimlistview ).value(),
					newPropertyId = newClaims[0].getMainSnak().getPropertyId();

				self.listview().removeItem( $claimlistview );

				var correspondingClaimlistview = self._findClaimlistview( newPropertyId );

				if( correspondingClaimlistview ) {
					correspondingClaimlistview.listview().addItem( newClaims[0] );
				} else {
					self.listview().addItem( newClaims );
				}

			} );

			lia.liInstance( $claimlistview ).enterNewItem();
		} );

		this.listview().enterNewItem();
	},

	/**
	 * Finds the claimlistview that features a specific property. Returns null if no claimlist
	 * featuring that property exists.
	 * @since 0.5
	 *
	 * @param {string} propertyId
	 * @return {jquery.wikibase.claimlistview|null}
	 */
	_findClaimlistview: function( propertyId ) {
		var $claimlistviews = this.listview().items(),
			lia = this.listview().listItemAdapter();

		for( var i = 0; i < $claimlistviews.length; i++ ) {
			var claimlistview = lia.liInstance( $claimlistviews.eq( i ) ),
				claims = claimlistview.value();
			if( claims.length && claims[0].getMainSnak().getPropertyId() === propertyId ) {
				return claimlistview;
			}
		}

		return null;
	}

} );

$.wikibase.toolbarcontroller.definition( 'addtoolbar', {
	id: 'claimgrouplistview',
	selector: ':' + $.wikibase.claimgrouplistview.prototype.namespace
		+ '-' + $.wikibase.claimgrouplistview.prototype.widgetName,
	events: {
		claimgrouplistviewcreate: function( event ) {
			var $claimgrouplistview = $( event.target );

			$claimgrouplistview.addtoolbar( {
				addButtonAction: function() {
					$claimgrouplistview.data( 'claimgrouplistview' ).enterNewItem();
				}
			} );
		},
		claimgrouplistviewdestroy: function( event, toolbarController ) {
			toolbarController.destroyToolbar( $( event.target ).data( 'addtoolbar' ) );
		}
	}
} );

// TODO: The following toolbar manages claimviews/statementviews as well as claimgrouplistviews.
//   There should be separate toolbars for managing the content of these two widgets.
$.wikibase.toolbarcontroller.definition( 'movetoolbar', {
	id: 'claimlistview-claimview',
	selector: '.wb-claimview',
	events: {
		'claimviewstartediting statementviewstartediting': function( event ) {
			// Initialize movetoolbar.

			var $claimview = $( event.target ),
				$claimlistview = $claimview.closest( ':wikibase-claimlistview' ),
				claimlistview = $claimlistview.data( 'claimlistview' ),
				claimlistviewListview = claimlistview.$listview.data( 'listview' ),
				$claimviews = claimlistviewListview.items(),
				$claimgrouplistview = $claimlistview.closest( ':wikibase-claimgrouplistview' ),
				claimgrouplistview = $claimgrouplistview.data( 'claimgrouplistview' ),
				claimgrouplistviewListview = claimgrouplistview.$listview.data( 'listview' ),
				$claimlistviews = claimgrouplistviewListview.items();

			$claimview.movetoolbar();

			// If the claimview is the topmost claimview in the topmost claimgroup, the "move up"
			// button needs to be disabled; Same for the "move down" button if the claimview is
			// the bottommost claimview in the bottommost claimgroup.
			var isInTopmostClaimgroup = $claimlistviews.first().get( 0 ) === $claimlistview.get( 0 ),
				isTopmostInClaimlistview = $claimviews.first().get( 0 ) === $claimview.get( 0 ),
				isInBottommostClaimgroup = $claimlistviews.last().get( 0 ) === $claimlistview.get( 0 ),
				isBottommostInClaimlistview = $claimviews.last().get( 0 ) === $claimview.get( 0 );

			if ( isInTopmostClaimgroup && isTopmostInClaimlistview ) {
				$claimview.data( 'movetoolbar' ).$btnMoveUp.data( 'toolbarbutton' ).disable();
			}

			if( isInBottommostClaimgroup && isBottommostInClaimlistview ) {
				$claimview.data( 'movetoolbar' ).$btnMoveDown.data( 'toolbarbutton' ).disable();
			}
		},
		'claimviewafterstopediting statementviewafterstopediting': function( event ) {
			// Destroy movetoolbar.

			var $claimview = $( event.target ),
				movetoolbar = $claimview.data( 'movetoolbar' );

			if( movetoolbar ) {
				// Toolbar might be destroyed already by cancelling a pending claimview.
				movetoolbar.destroy();
			}
		},
		'movetoolbarup movetoolbardown': function( event ) {
			var $claimview = $( event.target ),
				claimview = $claimview.data( 'claimview' ) || $claimview.data( 'statementview' );

			if( !claimview ) {
				// Not the event of the corresponding toolbar but of some other movetoolbar.
				return;
			}

			var action = ( event.type === 'movetoolbarup' ) ? 'moveUp' : 'moveDown',
				$claimlistview = $claimview.closest( ':wikibase-claimlistview' ),
				claimlistview = $claimlistview.data( 'claimlistview' ),
				claimlistviewListview = claimlistview.$listview.data( 'listview' ),
				$claimviews = claimlistviewListview.items(),
				$claimgrouplistview = $claimlistview.closest( ':wikibase-claimgrouplistview' ),
				claimgrouplistview = $claimgrouplistview.data( 'claimgrouplistview' ),
				claimgrouplistviewListview = claimgrouplistview.$listview.data( 'listview' ),
				$claimlistviews = claimgrouplistviewListview.items();

			// Determine whether the whole group has to be moved:
			var isTopmostInClaimlistview = $claimview.get( 0 ) === $claimviews.first().get( 0 ),
				isBottommostInClaimlistview = $claimview.get( 0 ) === $claimviews.last().get( 0 );

			if(
				isTopmostInClaimlistview && action === 'moveUp'
				|| isBottommostInClaimlistview && action === 'moveDown'
			) {
				claimgrouplistviewListview[action]( $claimlistview );
			} else {
				claimlistviewListview[action]( $claimview );
			}

			// Reflect the position change in the button state:
			$claimviews = claimlistviewListview.items();
			$claimlistviews = claimgrouplistviewListview.items();
			isTopmostInClaimlistview = $claimview.get( 0 ) === $claimviews.first().get( 0 );
			isBottommostInClaimlistview = $claimview.get( 0 ) === $claimviews.last().get( 0 );

			var $btnMoveUp = $claimview.data( 'movetoolbar' ).$btnMoveUp,
				btnMoveUp = $btnMoveUp.data( 'toolbarbutton' ),
				$btnMoveDown = $claimview.data( 'movetoolbar' ).$btnMoveDown,
				btnMoveDown = $btnMoveDown.data( 'toolbarbutton' ),
				isInTopmostClaimgroup = $claimlistviews.first().get( 0 ) === $claimlistview.get( 0 ),
				isInBottommostClaimgroup = $claimlistviews.last().get( 0 ) === $claimlistview.get( 0 ),
				isTopmost = isTopmostInClaimlistview && isInTopmostClaimgroup,
				isBottommost = isBottommostInClaimlistview && isInBottommostClaimgroup;

			btnMoveUp[isTopmost ? 'disable' : 'enable' ]();
			btnMoveDown[isBottommost ? 'disable' : 'enable' ]();

			// Update claimview indices:
			var index = 0;

			for( var i = 0; i < $claimlistviews.length; i++ ) {
				var claimlistviewInstance = claimgrouplistviewListview.listItemAdapter().liInstance(
						$claimlistviews.eq( i )
					);

				var claimlistviewInstanceLv = claimlistviewInstance.$listview.data( 'listview' ),
					$claimviewNodes = claimlistviewInstanceLv.items();

				for( var j = 0; j < $claimviewNodes.length; j++ ) {
					var claimviewInstance = claimlistviewInstanceLv.listItemAdapter().liInstance(
						$claimviewNodes.eq( j )
					);

					claimviewInstance.option( 'index', index );

					index++;
				}
			}

		}
	}
} );

}( mediaWiki, wikibase, jQuery ) );
