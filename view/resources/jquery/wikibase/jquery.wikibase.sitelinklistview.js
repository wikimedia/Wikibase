/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	var PARENT = $.ui.EditableTemplatedWidget;

/**
 * Displays and allows editing multiple site links.
 * @since 0.5
 * @extends jQuery.ui.EditableTemplatedWidget
 *
 * @option {wikibase.datamodel.SiteLink[]} [value]
 *         Default: []
 *
 * @option {string[]} [allowedSiteIds]
 *         Default: []
 *
 * @option {wikibase.entityChangers.SiteLinksChanger} siteLinksChanger
 *
 * @option {wikibase.store.EntityStore} entityStore
 *
 * @option {jQuery.util.EventSingletonManager} [eventSingletonManager]
 *         Should be set when the widget instance is part of a sitelinkgroupview.
 *         Default: null (will be constructed automatically)
 *
 * @option {jQuery} [$counter]
 *         Node(s) that shall contain information about the number of site links.
 *
 * @option {boolean} [autoInput]
 *         Whether to automatically show and add new input fields to add a new value when in edit
 *         mode.
 *         Default: true
 */
$.widget( 'wikibase.sitelinklistview', PARENT, {
	options: {
		template: 'wikibase-sitelinklistview',
		templateParams: [
			'' // listview
		],
		templateShortCuts: {
			$listview: 'ul'
		},
		value: [],
		allowedSiteIds: [],
		siteLinksChanger: null,
		entityStore: null,
		eventSingletonManager: null,
		$counter: null,
		autoInput: true
	},

	/**
	 * @type {jQuery.util.EventSingletonManager}
	 */
	_eventSingletonManager: null,

	/**
	 * @see jQuery.ui.TemplatedWidget._create
	 */
	_create: function() {
		if( !this.options.siteLinksChanger || !this.options.entityStore ) {
			throw new Error( 'Required option(s) missing' );
		}

		PARENT.prototype._create.call( this );

		this._eventSingletonManager = this.options.eventSingletonManager
			|| new $.util.EventSingletonManager();

		this.draw();
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget.destroy
	 */
	destroy: function() {
		this.$listview.data( 'listview' ).destroy();
		this.$listview.off( '.' + this.widgetName );
		this.element.removeClass( 'wikibase-sitelinklistview' );

		this._eventSingletonManager.unregister( this, window, '.' + this.widgetName );

		PARENT.prototype.destroy.call( this );
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget.draw
	 */
	draw: function() {
		if( !this.$listview.data( 'listview' ) ) {
			this._createListView();
		}

		this._refreshCounter();

		if( this.options.autoInput && !this.isFull() ) {
			var self = this,
				event = this.widgetEventPrefix + 'afterstartediting.' + this.widgetName,
				updateAutoInput = function() {
					self._updateAutoInput();
				};

			this.element
			.off( event, updateAutoInput )
			.on( event, updateAutoInput );
		}

		return $.Deferred().resolve().promise();
	},

	/**
	 * Creates the listview widget managing the sitelinkview widgets
	 */
	_createListView: function() {
		var self = this,
			listItemWidget = $.wikibase.sitelinkview,
			prefix = listItemWidget.prototype.widgetEventPrefix;

		// Encapsulate sitelinkviews by suppressing their events:
		this.$listview
		.listview( {
			listItemAdapter: new $.wikibase.listview.ListItemAdapter( {
				listItemWidget: listItemWidget,
				newItemOptionsFn: function( value ) {
					return {
						value: value,
						getAllowedSites: function() {
							return $.map( self._getUnusedAllowedSiteIds(), function( siteId ) {
								return wb.sites.getSite( siteId );
							} );
						},
						entityStore: self.options.entityStore
					};
				}
			} ),
			value: self.options.value || null,
			listItemNodeName: 'LI',
			encapsulate: true
		} )
		.on( prefix + 'change.' + this.widgetName, function( event ) {
			event.stopPropagation();
			if( self.options.autoInput ) {
				self._updateAutoInput();
				self._refreshCounter();
			}
			self._trigger( 'change' );
		} )
		.on( prefix + 'toggleerror.' + this.widgetName, function( event, error ) {
			event.stopPropagation();
		} )
		.on( 'keydown.' + this.widgetName, function( event ) {
			if( event.keyCode === $.ui.keyCode.BACKSPACE ) {
				var $sitelinkview = $( event.target ).closest( ':wikibase-sitelinkview' ),
					sitelinkview = $sitelinkview.data( 'sitelinkview' );

				if( sitelinkview ) {
					self._removeSitelinkviewIfEmpty( sitelinkview, event );
				}
			}
		} )
		.on(
			[
				prefix + 'create.' + this.widgetName,
				prefix + 'afterstartediting.' + this.widgetName,
				prefix + 'afterstopediting.' + this.widgetName,
				prefix + 'disable.' + this.widgetName
			].join( ' ' ),
			function( event ) {
				event.stopPropagation();
			}
		)
		.on(
			'listviewitemremoved.' + this.widgetName
			+ ' listviewitemadded.' + this.widgetName,
			function( event, sitelinkview ) {
				self._refreshCounter();
				if( sitelinkview ) {
					// Do not trigger "change" event when handling empty elements.
					self._trigger( 'change' );
				}
			}
		);
	},

	/**
	 * @param {jQuery.wikibase.sitelinkview} sitelinkview
	 * @param {jQuery.Event} event
	 */
	_removeSitelinkviewIfEmpty: function( sitelinkview, event ) {
		var $sitelinkview = sitelinkview.element,
			listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			$items = listview.items(),
			isLast = $sitelinkview.get( 0 ) === $items.last().get( 0 ),
			isEmpty = sitelinkview.isEmpty()
				|| sitelinkview.option( 'value' ) && !sitelinkview.value();

		if( isEmpty ) {
			event.preventDefault();
			event.stopPropagation();

			// Shift focus to previous line or to following line if there is no previous:
			$items.each( function( i ) {
				if( this === $sitelinkview.get( 0 ) ) {
					if( i > 0 ) {
						lia.liInstance( $items.eq( i - 1 ) ).focus();
					} else if( $items.length > 1 ) {
						lia.liInstance( $items.eq( i + 1 ) ).focus();
					}
					return false;
				}
			} );

			if( !isLast ) {
				listview.removeItem( $sitelinkview );
			}
		}
	},

	_updateAutoInput: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			$items = listview.items(),
			$lastSitelinkview = $items.last(),
			lastSitelinkview = lia.liInstance( $lastSitelinkview ),
			secondToLast = $items.length > 1 && lia.liInstance( $items.eq( -2 ) ),
			secondToLastEmpty = secondToLast && secondToLast.isEmpty(),
			secondToLastInvalidPending
				= secondToLast && !secondToLast.isValid() && !secondToLast.option( 'value' );

		if(
			lastSitelinkview
			&& lastSitelinkview.isEmpty()
			&& ( secondToLastEmpty || secondToLastInvalidPending )
		) {
			listview.removeItem( $lastSitelinkview );
		} else if( !lastSitelinkview || lastSitelinkview.isValid() && !this.isFull() ) {
			this.enterNewItem();
		}
	},

	/**
	 * @return {string[]}
	 */
	_getUnusedAllowedSiteIds: function() {
		var representedSiteIds = $.map( this.value(), function( siteLink ) {
			return siteLink.getSiteId();
		} );

		return $.grep( this.option( 'allowedSiteIds' ), function( siteId ) {
			return $.inArray( siteId, representedSiteIds ) === -1;
		} );
	},

	/**
	 * Returns whether all allowed sites are linked or no more site links may be added.
	 *
	 * @return {boolean}
	 */
	isFull: function() {
		return !this._getUnusedAllowedSiteIds().length
			|| this.value().length === this.options.allowedSiteIds.length;
	},

	/**
	 * Refreshes any nodes featuring a counter.
	 */
	_refreshCounter: function() {
		if( !this.options.$counter ) {
			return;
		}

		this.options.$counter
		.addClass( this.widgetName + '-counter' )
		.empty()
		.append( this._getFormattedCounterText() );
	},

	/**
	 * Returns a formatted string with the number of site links.
	 *
	 * @return {jQuery}
	 */
	_getFormattedCounterText: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			$items = listview.items(),
			$pendingItems = $items.filter( '.wb-new' ).filter( function() {
				return !lia.liInstance( $( this ) ).isEmpty();
			} );

		var $counterMsg = wb.utilities.ui.buildPendingCounter(
			$items.length - $pendingItems.length,
			$pendingItems.length,
			'wikibase-propertyedittool-counter-entrieslabel',
			'wikibase-propertyedittool-counter-pending-tooltip'
		);

		// Counter result should be wrapped in parentheses, which is another message. Since the
		// message system does not return a jQuery object, a work-around is needed:
		var $parenthesesMsg = $(
			( '<div>' + mw.msg( 'parentheses', '__1__' ) + '</div>' ).replace( /__1__/g, '<span/>' )
		);
		$parenthesesMsg.find( 'span' ).replaceWith( $counterMsg );

		return $parenthesesMsg.contents();
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget.isEmpty
	 */
	isEmpty: function() {
		return !this.$listview.data( 'listview' ).items().length;
	},

	/**
	 * @return {boolean}
	 */
	isValid: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			isValid = true;

		listview.items().each( function() {
			// Site link views are regarded valid if they have a valid site. Invalid site links
			// (without a page name) and empty values (with no site id and page name input) are
			// supposed to be stripped when querying this widget for its value.
			// Put together, we consider sitelinkviews invalid only when they have something in
			// the siteId input field which does not resolve to a valid siteId and which is not
			// empty.
			var sitelinkview = lia.liInstance( $( this ) );
			isValid = sitelinkview.isValid()
				|| sitelinkview.isEmpty()
				// Previously existing values do always feature a valid site id:
				|| Boolean( sitelinkview.option( 'value' ) );
			return isValid === true;
		} );

		return isValid;
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget.isInitialValue
	 */
	isInitialValue: function() {
		var currentValue = this.value();

		if( currentValue.length !== this.options.value.length ) {
			return false;
		}

		// TODO: Use SiteLinkList.equals() as soon as implemented in DataModelJavaScript
		for( var i = 0; i < currentValue.length; i++ ) {
			if( currentValue[i] === null ) {
				// Ignore empty values.
				continue;
			}
			var found = false;
			for( var j = 0; j < this.options.value.length; j++ ) {
				if( currentValue[i].equals( this.options.value[j] ) ) {
					found = true;
					break;
				}
			}
			if( !found ) {
				return false;
			}
		}

		return true;
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget.startEditing
	 */
	startEditing: function() {
		var self = this;

		this._eventSingletonManager.register(
			this,
			window,
			namespaceEventNames( 'scroll touchmove resize', this.widgetName ),
			function( event, self ) {
				self._startEditingInViewport();
			},
			{
				throttle: 150
			}
		);

		self._startEditingInViewport();

		return PARENT.prototype.startEditing.call( this );
	},

	_startEditingInViewport: function() {
		/**
		 * @param {HTMLElement} node
		 * @return {boolean}
		 */
		function touchesViewport( node ) {
			var rect = node.getBoundingClientRect(),
				$window = $( window ),
				wHeight = $window.height(),
				wWidth = $window.width(),
				touchesViewportHorizontally = rect.right >= 0 && rect.right < wWidth
					|| rect.left >= 0 && rect.left < wWidth,
				touchesViewportVertically = rect.top >= 0 && rect.top < wHeight
					|| rect.bottom >= 0 && rect.bottom < wHeight;
			return touchesViewportHorizontally && touchesViewportVertically;
		}

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			foundOne = false;

		listview.items().each( function( i ) {
			if( touchesViewport( this ) ) {
				lia.liInstance( $( this ) ).startEditing();
				foundOne = true;
			}
		} );
		if( !foundOne && listview.items().length > 0 ) {
			lia.liInstance( $( listview.items()[0] ) ).startEditing();
		}
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget.stopEditing
	 */
	stopEditing: function( dropValue ) {
		var self = this;

		if( dropValue ) {
			self.$listview.data( 'listview' ).value( self.options.value );
		} else {
			this._removeIncompleteSiteLinks();
		}

		return PARENT.prototype.stopEditing.call( this, dropValue )
			.done( function() {
				self.$listview.data( 'listview' ).value( self.value() );

				self._eventSingletonManager.unregister(
					self,
					window,
					namespaceEventNames( 'scroll touchmove resize', self.widgetName )
				);
			} );
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget._save
	 */
	_save: function() {
		var self = this,
			deferred = $.Deferred(),
			listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		var $queue = $( {} );

		/**
		 * @param {jQuery} $queue
		 * @param {string} siteId
		 */
		function addRemoveToQueue( $queue, siteId ) {
			$queue.queue( 'stopediting', function( next ) {
				var emptySiteLink = new wb.datamodel.SiteLink( siteId, '' );
				self._saveSiteLink( emptySiteLink )
					.done( function() {
						self._afterRemove();

						// Avoid exceeding call stack size.
						setTimeout( next, 0 );
					} )
					.fail( function( error ) {
						$queue.clearQueue( 'stopediting' );
						self._resetEditMode();
						deferred.reject( error );
					} );
			} );
		}

		var removedSiteLinkIds = this._getRemovedSiteLinkIds();

		for( var i = 0; i < removedSiteLinkIds.length; i++ ) {
			addRemoveToQueue( $queue, removedSiteLinkIds[i] );
		}

		/**
		 * @param {jQuery} $queue
		 * @param {jQuery.wikibase.sitelinkview} sitelinkview
		 */
		function addStopEditToQueue( $queue, sitelinkview ) {
			$queue.queue( 'stopediting', function( next ) {
				sitelinkview.element
				.one( 'sitelinkviewstopediting.sitelinklistviewstopediting', function( event, dropValue, callback ) {
					event.stopPropagation();

					var $sitelinkview = $( event.target ),
						sitelinkview = $sitelinkview.data( 'sitelinkview' ),
						value = sitelinkview.value();

					if( !dropValue && !sitelinkview.isInitialValue() ) {
						sitelinkview.disable();

						self._saveSiteLink( value )
						.done( function( newSiteLink ) {
							sitelinkview.value( newSiteLink );
							callback();
						} )
						.fail( function( error ) {
							sitelinkview.setError( error );
						} )
						.always( function() {
							sitelinkview.enable();
						} );
					}
				} )
				.one( 'sitelinkviewafterstopediting.sitelinklistviewstopediting', function( event ) {
					sitelinkview.element.off( '.sitelinklistviewstopediting' );
					// Avoid exceeding call stack size.
					setTimeout( next, 0 );
				} )
				.one(
					'sitelinkviewtoggleerror.sitelinklistviewstopediting',
					function( event, error ) {
						sitelinkview.element.off( '.sitelinklistviewstopediting' );
						$queue.clearQueue( 'stopediting' );
						self._resetEditMode();
						deferred.reject( error );
					}
				);
				sitelinkview.stopEditing();
			} );
		}

		listview.items().each( function() {
			var sitelinkview = lia.liInstance( $( this ) );

			if( sitelinkview.isInitialValue() ) {
				sitelinkview.stopEditing( true );
			} else {
				addStopEditToQueue( $queue, sitelinkview );
			}
		} );

		$queue.queue( 'stopediting', function() {
			deferred.resolve();
		} );

		$queue.dequeue( 'stopediting' );

		return deferred.promise();
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget._afterStopEditing
	 */
	_afterStopEditing: function( dropValue ) {
		var self = this;

		return PARENT.prototype._afterStopEditing.call( this, dropValue )
			.done( function() {
				self.$listview.data( 'listview' ).value( self.options.value );
			} );
	},

	_removeIncompleteSiteLinks: function() {
		var listview = this.$listview.data( 'listview' );

		listview.items().not( listview.nonEmptyItems() ).each( function() {
			listview.removeItem( $( this ) );
		} );
	},

	_resetEditMode: function() {
		this.enable();

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		listview.items().each( function() {
			lia.liInstance( $( this ) ).startEditing();
		} );
	},

	/**
	 * @return {string[]}
	 */
	_getRemovedSiteLinkIds: function() {
		var currentSiteIds = $.map( this.value(), function( siteLink ) {
			return siteLink.getSiteId();
		} );

		var removedSiteLinkIds = [];

		for( var i = 0; i < this.options.value.length; i++ ) {
			var siteId = this.options.value[i].getSiteId();
			if( $.inArray( siteId, currentSiteIds ) === -1 ) {
				removedSiteLinkIds.push( siteId );
			}
		}

		return removedSiteLinkIds;
	},

	/**
	 * @see jQuery.ui.TemplatedWidget.focus
	 */
	focus: function() {
		// Focus first invalid/incomplete item or - if there is none - the first item.
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			$items = listview.items();

		if( !$items.length ) {
			this.element.focus();
			return;
		}

		/**
		 * @param {jQuery} $nodes
		 * @return {jQuery}
		 */
		function findFirstInViewPort( $nodes ) {
			var $window = $( window );
			var $foundNode = null;

			$nodes.each( function() {
				var $node = $( this );
				if( $node.is( ':visible' ) && $node.offset().top > $window.scrollTop() ) {
					$foundNode = $node;
				}
				return $foundNode === null;
			} );

			return $foundNode || $nodes.first();
		}

		if( !this.isValid() ) {
			$items = $items.filter( function() {
				var sitelinkview = lia.liInstance( $( this ) );
				return !sitelinkview.isValid();
			} );
		}
		$items = findFirstInViewPort( $items );

		if( $items.length ) {
			setTimeout( function() {
				lia.liInstance( $items ).focus();
			}, 10 );
		}
	},

	/**
	 * @see jQuery.ui.EditableTemplatedWidget.value
	 *
	 * @param {wikibase.datamodel.SiteLink[]} [value]
	 * @return {wikibase.datamodel.SiteLink[]|*}
	 */
	value: function( value ) {
		if( value !== undefined ) {
			return this.option( 'value', value );
		}

		value = [];

		if( !this.$listview ) {
			return this.options.value;
		}

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		listview.nonEmptyItems().each( function() {
			value.push( lia.liInstance( $( this ) ).value() );
		} );

		return value;
	},

	/**
	 * @see jQuery.ui.TemplatedWidget._setOption
	 */
	_setOption: function( key, value ) {
		var response = PARENT.prototype._setOption.apply( this, arguments );

		if( key === 'value' ) {
			this.$listview.data( 'listview' ).value( value );
			this._refreshCounter();
		} else if( key === 'disabled' ) {
			this.$listview.data( 'listview' ).option( key, value );
		}

		return response;
	},

	/**
	 * Issues the API action to save a site link.
	 *
	 * @param {wikibase.datamodel.SiteLink} siteLink
	 * @return {jQuery.Promise}
	 *         Resolved parameters:
	 *         - {Object}
	 *         Rejected parameters:
	 *         - {wikibase.api.RepoApiError}
	 */
	_saveSiteLink: function( siteLink ) {
		var self = this;

		return this.options.siteLinksChanger.setSiteLink( siteLink )
		.done( function( savedSiteLink ) {

			// Remove site link:
			self.options.value = $.grep( self.options.value, function( sl ) {
				return sl.getSiteId() !== siteLink.getSiteId();
			} );

			// (Re-)add (altered) site link when editing/adding a site link:
			if( siteLink.getPageName() !== '' ) {
				self.options.value.push( siteLink );
			}
		} );
	},

	/**
	 * Removes a sitelinkview instance.
	 *
	 * @param {jQuery.wikibase.sitelinkview} sitelinkview
	 * @return {jQuery.Promise}
	 *         Resolved parameters:
	 *         - {Object}
	 *         Rejected parameters:
	 *         - {wikibase.api.RepoApiError}
	 */
	remove: function( sitelinkview ) {
		var self = this,
			siteLink = sitelinkview.value(),
			emptySiteLink = new wb.datamodel.SiteLink( siteLink.getSiteId(), '' );

		this.disable();

		return this._saveSiteLink( emptySiteLink )
		.done( function() {
			self.$listview.data( 'listview' ).removeItem( sitelinkview.element );
			self._afterRemove();
		} )
		.fail( function( error ) {
			sitelinkview.setError( error );
		} )
		.always( function() {
			self.enable();
		} );
	},

	_afterRemove: function() {
		this._refreshCounter();
	},

	/**
	 * Adds a pending `sitelinkview` to the `sitelinklistview`.
	 * @see jQuery.wikibase.listview.enterNewItem
	 *
	 * @return {Object} jQuery.Promise
	 * @return {Function} return.done
	 * @return {jQuery} return.done.$sitelinkview
	 */
	enterNewItem: function() {
		var self = this,
			listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			afterStopEditingEvent = lia.prefixedEvent( 'afterstopediting.' + this.widgetName );

		return listview.enterNewItem().done( function( $sitelinkview ) {
			var sitelinkview = lia.liInstance( $sitelinkview );

			$sitelinkview
			.addClass( 'wb-new' )
			.on( afterStopEditingEvent, function( event, dropValue ) {
				var siteLink = sitelinkview.value();

				listview.removeItem( $sitelinkview );

				if( !dropValue && siteLink ) {
					listview.addItem( siteLink );
				}

				if( self.__pendingItems && --self.__pendingItems !== 0 ) {
					return;
				}

				self._refreshCounter();
			} );

			self._refreshCounter();

			if( !self.isInEditMode() ) {
				self.startEditing();
			} else {
				sitelinkview.startEditing();
			}

			this.__pendingItems = this.__pendingItems ? this.__pendingItems + 1 : 1;
		} );
	}
} );

/**
 * @param {string} eventNames
 * @param {string} namespace
 * @return {string}
 */
function namespaceEventNames( eventNames, namespace ) {
	return eventNames.split( ' ' ).join( '.' + namespace + ' ' ) + '.' + namespace;
}

}( mediaWiki, wikibase, jQuery ) );
