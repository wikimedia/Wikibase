/**
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	var PARENT = $.wikibase.listview;

/**
 * View for displaying and editing Wikibase Statements.
 *
 * @option statementGuid {string} (REQUIRED) The GUID of the statement the reference belongs to.
 *
 * @option index {number|null} The reference's index within the list of references (if the reference
 *         is contained within such a list).
 *         Default: null
 *         TODO: This option should be removed and a proper mechanism independent from referenceview
 *         should be implemented to manage and store the indices of references (bug #56050).
 *
 * @option helpMessage {string} End-user message explaining how to use the referenceview widget. The
 *         message is most likely to be used inside the tooltip of the toolbar corresponding to
 *         the referenceview.
 *         Default: mw.msg( 'wikibase-claimview-snak-new-tooltip' )
 *
 * @event startediting: Triggered when starting the referenceview's edit mode.
 *        (1) {jQuery.Event}
 *
 * @event afterstartediting: Triggered after having started the referenceview's edit mode.
 *        (1) {jQuery.Event}
 *
 * @event stopediting: Triggered when stopping the referenceview's edit mode.
 *        (1) {jQuery.Event}
 *        (2) {boolean} If true, the value from before edit mode has been started will be reinstated
 *            (basically a cancel/save switch).
 *
 * @event afterstopediting: Triggered after having stopped the referenceview's edit mode.
 *        (1) {jQuery.Event}
 *        (2) {boolean} If true, the value from before edit mode has been started will be reinstated
 *            (basically a cancel/save switch).
 *
 * @event change: Triggered whenever the referenceview's content is changed.
 *        (1) {jQuery.Event} event
 *
 * @event disable: Triggered whenever the referenceview gets disabled.
 *        (1) {jQuery.Event} event
 *
 * @event enable: Triggered whenever the referenceview gets enabled.
 *        (1) {jQuery.Event} event
 *
 * @event toggleerror: Triggered when an error occurred or is resolved.
 *        (1) {jQuery.Event} event
 *        (2) {wb.RepoApiError|undefined} wb.RepoApiError object if an error occurred, undefined if
 *            the current error state is resolved.
 *
 * @since 0.4
 * @extends jQuery.wikibase.listview
 */
$.widget( 'wikibase.referenceview', PARENT, {
	widgetBaseClass: 'wb-referenceview',

	/**
	 * (Additional) default options.
	 * @see jQuery.Widget.options
	 */
	options: {
		template: 'wb-referenceview',
		templateParams: [
			'' // snaklistview widget
		],
		statementGuid: null,
		listItemAdapter: new $.wikibase.listview.ListItemAdapter( {
			listItemWidget: $.wikibase.snaklistview,
			listItemWidgetValueAccessor: 'value',
			newItemOptionsFn: function( value ) {
				return {
					value: value || null,
					singleProperty: true
				};
			}
		} ),
		index: null,
		helpMessage: mw.msg( 'wikibase-claimview-snak-new-tooltip' )
	},

	/**
	 * Reference object represented by this view.
	 * @type {wb.Reference}
	 */
	_reference: null,

	/**
	 * Caches the snak list of the reference snaks the referenceview has been initialized with. The
	 * snaks are split into groups featuring the same property. Removing one of those groups results
	 * in losing the reference to those snaks. Therefore, _initialSnakList is used to rebuild the
	 * list of snaks when cancelling and is used to query whether the snaks represent the initial
	 * state.
	 * @type {wikibase.SnakList}
	 */
	_initialSnakList: null,

	/**
	 * The reference's initial index within the list of references (if it is contained within a list
	 * of references). The initial index is stored to be able to detect whether the index has
	 * changed and the reference does not feature its initial value.
	 * @type {number|null}
	 */
	_initialIndex: null,

	/**
	 * @see jQuery.wikibase.snaklistview._create
	 */
	_create: function() {
		var self = this;

		if ( !this.option( 'statementGuid' ) ) {
			throw new Error( 'Statement GUID required to initialize a reference view.' );
		}

		if ( this.option( 'value' ) ) {
			this._reference = this.option( 'value' );
			// Overwrite the value since listItemAdapter is the snakview prototype which requires a
			// wb.SnakList object for initialization:
			this._initialSnakList = this._reference.getSnaks();
			this.options.value = this._initialSnakList.getGroupedSnakLists();
		}

		if( !this._initialSnakList ) {
			this._initialSnakList = new wb.SnakList();
		}

		this._initialIndex = this.option( 'index' );

		PARENT.prototype._create.call( this );

		// Whenever entering a new referenceview item, a single snaklistview needs to be created
		// along. This creates a snakview ready to edit.
		this.element
		.on( 'referenceviewenternewitem.' + this.widgetName, function( event, $newLi ) {
			self.options.listItemAdapter.liInstance( $newLi ).enterNewItem();
		} );

		this._updateReferenceHashClass( this.value() );
	},

	/**
	 * @see jQuery.Widget.option
	 *
	 * @triggers change
	 */
	option: function( key, value ) {
		var val = PARENT.prototype.option.apply( this, arguments );

		if( key === 'index' && value !== undefined ) {
			this._trigger( 'change' );
		}

		return val;
	},

	/**
	 * Returns the reference's initial index within the list of references (if in any).
	 * @since 0.5
	 *
	 * @return {number|null}
	 */
	getInitialIndex: function() {
		return this._initialIndex;
	},

	/**
	 * Attaches event listeners needed during edit mode.
	 */
	_attachEditModeEventHandlers: function() {
		var self = this;

		var changeEvents = [
			'snakviewchange.' + this.widgetName,
			'snaklistviewchange.' + this.widgetName,
			'referenceviewafteritemmove.' + this.widgetName,
			'listviewitemadded.' + this.widgetName,
			'listviewitemremoved.' + this.widgetName
		];

		this.element
		.on( changeEvents.join( ' ' ), function( event ) {
			if( event.type === 'listviewitemremoved' ) {
				// Check if last snaklistview item (snakview) has been removed and remove the
				// listview item (the snaklistview itself) if so:
				var $snaklistview = $( event.target ).closest( ':wikibase-snaklistview' ),
					snaklistview = $snaklistview.data( 'snaklistview' );

				if( snaklistview && !snaklistview.value() ) {
					self.removeItem( snaklistview.element );
				}
			}

			// Propagate "change" event.
			self._trigger( 'change' );
		} )
		.one( this.options.listItemAdapter.prefixedEvent( 'stopediting.' + this.widgetName ),
			function( event, dropValue ) {
				event.stopPropagation();
				event.preventDefault();
				self.stopEditing( dropValue );
		} );
	},

	/**
	 * Detaches the event handlers needed during edit mode.
	 */
	_detachEditModeEventHandlers: function() {
		var events = [
			'snakviewchange.' + this.widgetName,
			'snaklistviewchange.' + this.widgetName,
			'referenceviewafteritemmove.' + this.widgetName,
			'listviewitemadded.' + this.widgetName,
			'listviewitemremoved.' + this.widgetName,
			this.options.listItemAdapter.prefixedEvent( 'stopediting.' + this.widgetName )
		];
		this.element.off( events.join( ' ' ) );
	},

	/**
	 * Will update the 'wb-reference-<hash>' class on the widget's root element to a given
	 * reference's hash. If null is given or if the reference has no hash, 'wb-reference-new' will
	 * be added as class.
	 *
	 * @param {wb.Reference|null} reference
	 */
	_updateReferenceHashClass: function( reference ) {
		var refHash = reference && reference.getHash() || 'new';

		this.element.removeClassByRegex( /wb-reference-.+/ );
		this.element.addClass( 'wb-reference-' + refHash );

		this.element.removeClassByRegex( new RegExp( this.widgetBaseClass ) + '-.+' );
		this.element.addClass( this.widgetBaseClass + '-' + refHash );
	},

	/**
	 * Sets/Returns the current reference represented by the view. In case of an empty reference
	 * view, without any snak values set yet, null will be returned.
	 * @see jQuery.wikibase.snaklistview.value
	 * @since 0.4
	 *
	 * @param {wb.Reference} [reference] New reference to be set
	 * @return {wb.Reference|null}
	 */
	value: function( reference ) {
		if ( reference ) {
			if ( !( reference instanceof wb.Reference ) ) {
				throw new Error( 'Value has to be an instance of wikibase.Reference' );
			}
			this._reference = reference;
			return this._reference;
		} else {
			var snaklistviews = this.items(),
				snakList = new wb.SnakList();

			for( var i = 0; i < snaklistviews.length; i++ ) {
				var snak = this.options.listItemAdapter.liValue( snaklistviews.eq( i ) );
				if( snak ) {
					snakList.add( snak );
				}
			}

			if ( this._reference ) {
				return new wb.Reference( snakList || [], this._reference.getHash() );
			} else if ( snakList.length ) {
				return new wb.Reference( snakList );
			} else {
				return null;
			}
		}
	},

	/**
	 * Starts the referenceview's edit mode.
	 * @since 0.5
	 *
	 * @triggers startediting
	 * @triggers afterstartediting
	 */
	startEditing: $.NativeEventHandler( 'startediting', {
		initially: function( e ) {
			if( this.isInEditMode() ) {
				e.cancel();
			}
		},
		natively: function( e ) {
			var $snaklistviews = this.items();

			for( var i = 0; i < $snaklistviews.length; i++ ) {
				this.options.listItemAdapter.liInstance( $snaklistviews.eq( [i] ) ).startEditing();
			}

			this._attachEditModeEventHandlers();

			this.element.addClass( 'wb-edit' );
			this._isInEditMode = true;

			this._trigger( 'afterstartediting' );
		}
	} ),

	/**
	 * Stops the referenceview's edit mode.
	 * @since 0.5
	 *
	 * @triggers stopediting
	 * @triggers afterstopediting
	 */
	stopEditing: $.NativeEventHandler( 'stopediting', {
		initially: function( e, dropValue ) {
			if (
				!this.isInEditMode() || ( !this.isValid() || this.isInitialValue() ) && !dropValue
			) {
				e.cancel();
			}

			this.element.removeClass( 'wb-error' );
		},
		natively: function( e, dropValue ) {
			var self = this;

			this._detachEditModeEventHandlers();

			this.disable();

			if( dropValue ) {
				this._stopEditingReferenceSnaks( dropValue );

				this.enable();
				this.element.removeClass( 'wb-edit' );
				this._isInEditMode = false;

				this._attachEditModeEventHandlers();

				this._trigger( 'afterstopediting', null, [ dropValue ] );
			} else {

				this._saveReferenceApiCall()
				.done( function( savedObject, pageInfo ) {
					self._stopEditingReferenceSnaks( dropValue );

					self.enable();

					self.element.removeClass( 'wb-edit' );
					self._isInEditMode = false;

					self._trigger( 'afterstopediting', null, [ dropValue ] );
				} )
				.fail( function( errorCode, details ) {
					var error = wb.RepoApiError.newFromApiResponse( errorCode, details, 'save' );

					self.enable();

					self._attachEditModeEventHandlers();

					self.setError( error );
				} );

			}

		}
	} ),

	/**
	 * Cancels edit mode.
	 * @since 0.5
	 */
	cancelEditing: function() {
		this.stopEditing( true );
	},

	/**
	 * Stops all the referenceview's snaklistviews' edit mode and regenerates the referenceview's
	 * content.
	 *
	 * @param {boolean} dropValue
	 */
	_stopEditingReferenceSnaks: function( dropValue ) {
		var $snaklistviews = this.items(),
			i;

		if( !dropValue ) {
			// When saving the qualifier snaks, reset the initial qualifiers to the new ones.
			this._initialSnakList = new wb.SnakList();
		}

		if( $snaklistviews.length ) {
			for( i = 0; i < $snaklistviews.length; i++ ) {
				var snaklistview = this.options.listItemAdapter.liInstance( $snaklistviews.eq( i ) );
				snaklistview.stopEditing( dropValue );

				if( dropValue && !snaklistview.value() ) {
					// Remove snaklistview from referenceview if no snakviews are left in
					// that snaklistview:
					this.removeItem( snaklistview.element );
				} else if ( !dropValue ) {
					// Gather all the current snaks in a single SnakList to set to reset the
					// initial qualifiers:
					this._initialSnakList.add( snaklistview.value() );
				}
			}
		}

		this.clear();

		var snakLists = this._initialSnakList.getGroupedSnakLists();

		if( snakLists ) {
			for( i = 0; i < snakLists.length; i++ ) {
				this.addItem( snakLists[i] );
			}
		}
	},

	/**
	 * Clears the referenceview's content.
	 * @since 0.5
	 */
	clear: function() {
		var items = this.items();

		for( var i = 0; i < items.length; i++ ) {
			this.removeItem( items.eq( i ) );
		}
	},

	/**
	 * Returns whether the referenceview currently is in edit mode.
	 * @since 0.5
	 *
	 * @return {boolean}
	 */
	isInEditMode: function() {
		return this._isInEditMode;
	},

	/**
	 * Returns whether the referenceview (all its snaklistviews) currently is valid.
	 * @since 0.5
	 *
	 * @return {boolean}
	 */
	isValid: function() {
		var $snaklistviews = this.items();

		for( var i = 0; i < $snaklistviews.length; i++ ) {
			if( !this.options.listItemAdapter.liInstance( $snaklistviews.eq( i ) ).isValid() ) {
				return false;
			}
		}

		return true;
	},

	/**
	 * Returns whether the referenceview's current value matches the value it has been initialized
	 * with.
	 * @since 0.5
	 *
	 * @return {boolean}
	 */
	isInitialValue: function() {
		if( this.option( 'index' ) !== this._initialIndex ) {
			return false;
		}

		var $snaklistviews = this.items(),
			snakList = new wb.SnakList();

		// Generate a SnakList object featuring all current reference snaks to be able to compare it
		// to the SnakList object the referenceview has been initialized with:
		if( $snaklistviews.length ) {
			for( var i = 0; i < $snaklistviews.length; i++ ) {
				var snakview = this.options.listItemAdapter.liInstance( $snaklistviews.eq( i ) );
				if( snakview.value() ) {
					snakList.add( snakview.value() );
				}
			}
		}

		return snakList.equals( this._initialSnakList );
	},

	/**
	 * Initialize entering a new item to the referenceview.
	 * @since 0.5
	 *
	 * @triggers change
	 */
	enterNewItem: function() {
		PARENT.prototype.enterNewItem.call( this );
		this.startEditing();

		// Since the new snakview will be initialized empty which invalidates the snaklistview,
		// external components using the snaklistview will be noticed via the "change" event.
		this._trigger( 'change' );
	},

	/**
	 * Triggers the API call to save the reference.
	 * @since 0.4
	 *
	 * @return {jQuery.promise}
	 */
	_saveReferenceApiCall: function() {
		var self = this,
			guid = this.option( 'statementGuid' ),
			abstractedApi = new wb.AbstractedRepoApi(),
			revStore = wb.getRevisionStore();

		return abstractedApi.setReference(
			guid,
			this.value().getSnaks(),
			revStore.getClaimRevision( guid ),
			this.value().getHash() || null,
			this.option( 'index' )
		).done( function( savedReference, pageInfo ) {
			// update revision store
			revStore.setClaimRevision( pageInfo.lastrevid, guid );

			self._reference = savedReference;
			self._snakList = self._reference.getSnaks();
			self._updateReferenceHashClass( savedReference );
		} );
	},

	/**
	 * Sets/removes error state from the widget.
	 * @since 0.4
	 *
	 * @param {wb.RepoApiError} [error]
	 */
	setError: function( error ) {
		if ( error ) {
			this.element.addClass( 'wb-error' );
			this._trigger( 'toggleerror', null, [ error ] );
		} else {
			this.element.removeClass( 'wb-error' );
			this._trigger( 'toggleerror' );
		}
	},

	/**
	 * Disables the referenceview.
	 * @since 0.5
	 *
	 * @triggers disable
	 */
	disable: function() {
		var $snaklistviews = this.items();
		for( var i = 0; i < $snaklistviews.length; i++ ) {
			this.options.listItemAdapter.liInstance( $snaklistviews.eq( i ) ).disable();
		}
		this._trigger( 'disable' );
	},

	/**
	 * Enables the referenceview.
	 * @since 0.5
	 *
	 * @triggers enable
	 */
	enable: function() {
		var $snaklistviews = this.items();
		for( var i = 0; i < $snaklistviews.length; i++ ) {
			this.options.listItemAdapter.liInstance( $snaklistviews.eq( i ) ).enable();
		}
		this._trigger( 'enable' );
	}

} );

// Register toolbars:
$.wikibase.toolbarcontroller.definition( 'addtoolbar', {
	id: 'referenceview-snakview',
	selector: '.wb-statement-references .wb-referenceview',
	events: {
		referenceviewstartediting: function( event ) {
			var $referenceview = $( event.target );

			$referenceview.addtoolbar( {
				addButtonAction: function() {
					$referenceview.data( 'referenceview' ).enterNewItem();
				}
			} );
		},
		referenceviewafterstopediting: function( event, toolbarController ) {
			toolbarController.destroyToolbar( $( event.target ).data( 'addtoolbar' ) );
		},
		referenceviewchange: function( event ) {
			var $referenceview = $( event.target ).closest( ':wikibase-referenceview' ),
				referenceview = $referenceview.data( 'referenceview' ),
				addToolbar = $referenceview.data( 'addtoolbar' );
			if ( addToolbar ) {
				addToolbar.toolbar[referenceview.isValid() ? 'enable' : 'disable']();
			}
		},
		referenceviewdisable: function( event ) {
			$( event.target ).data( 'addtoolbar' ).toolbar.disable();
		},
		referenceviewenable: function( event ) {
			var addToolbar = $( event.target ).data( 'addtoolbar' );
			// "add" toolbar might be remove already.
			if ( addToolbar ) {
				addToolbar.toolbar.enable();
			}
		}
	}
} );

$.wikibase.toolbarcontroller.definition( 'removetoolbar', {
	id: 'referenceview-snakview-remove',
	selector: '.wb-statement-references .wb-referenceview',
	events: {
		'snakviewstartediting snakviewchange referenceviewitemremoved': function( event ) {
			var $target = $( event.target ),
				$referenceview = $target.closest( ':wikibase-referenceview' ),
				referenceview = $referenceview.data( 'referenceview' );

			if( !referenceview ) {
				return;
			}

			if ( event.type === 'snakviewstartediting' ) {
				var $snaklistview = $target.closest( ':wikibase-snaklistview' ),
					snaklistview = $snaklistview.data( 'snaklistview' ),
					snakviewPropertyGroupListview = snaklistview._listview;

				$target.removetoolbar( {
					action: function( event ) {
						snakviewPropertyGroupListview.removeItem( $target );
					}
				} );
			}

			// If there is only one snakview widget, disable its "remove" link:
			if( referenceview.items().length === 0 ) {
				return;
			}

			var $snaklistviews = referenceview.items(),
				$firstSnaklistview = $snaklistviews.first(),
				referenceviewLia = referenceview.options.listItemAdapter,
				firstSnaklistview = referenceviewLia.liInstance( $firstSnaklistview ),
				$firstSnakview = firstSnaklistview.$listview.data( 'listview' ).items().first(),
				removetoolbar = $firstSnakview.data( 'removetoolbar' ),
				numberOfSnakviews = 0;

			for( var i = 0; i < $snaklistviews.length; i++ ) {
				var snaklistviewWidget = referenceviewLia.liInstance( $snaklistviews.eq( i ) ),
					snaklistviewListview = snaklistviewWidget._listview,
					snaklistviewListviewLia = snaklistviewListview.listItemAdapter(),
					$snakviews = snaklistviewWidget._listview.items();

				for( var j = 0; j < $snakviews.length; j++ ) {
					var snakview = snaklistviewListviewLia.liInstance( $snakviews.eq( j ) );
					if( snakview.snak() ) {
						numberOfSnakviews++;
					}
				}
			}

			if( removetoolbar ) {
				removetoolbar.toolbar[( event.type === 'snakviewstartediting' && numberOfSnakviews > 0 || numberOfSnakviews > 1 ) ? 'enable' : 'disable']();
			}
		},
		referenceviewafterstopediting: function( event, toolbarcontroller ) {
			// Destroy the snakview toolbars:
			var $referenceviewNode = $( event.target );
			$.each( $referenceviewNode.find( '.wb-snakview' ), function( i, snakviewNode ) {
				toolbarcontroller.destroyToolbar( $( snakviewNode ).data( 'removetoolbar' ) );
			} );
		},
		'referenceviewdisable referenceviewenable': function( event ) {
			var referenceview = $( event.target ).data( 'referenceview' ),
				$snaklistviews = referenceview.items(),
				lia = referenceview.options.listItemAdapter,
				action = ( event.type.indexOf( 'disable' ) !== -1 ) ? 'disable' : 'enable';

			for( var i = 0; i < $snaklistviews.length; i++ ) {
				var snaklistview = lia.liInstance( $snaklistviews.eq( i ) );

				// Item might be about to be removed not being a list item instance.
				if( snaklistview ) {
					var $snakviews = snaklistview._listview.items();

					for( var j = 0; j < $snakviews.length; j++ ) {
						var removetoolbar = $snakviews.eq( j ).data( 'removetoolbar' );

						if( removetoolbar ) {
							removetoolbar.toolbar[action]();
						}
					}
				}
			}
		}
	}
} );

$.wikibase.toolbarcontroller.definition( 'movetoolbar', {
	id: 'referenceview-snakview',
	selector: '.wb-statement-references .wb-referenceview',
	events: {
		'snakviewstartediting': function( event ) {
			var $snakview = $( event.target ),
				$snaklistview = $snakview.closest( ':wikibase-snaklistview' ),
				$referenceview = $snakview.closest( ':wikibase-referenceview' ),
				referenceview = $referenceview.data( 'referenceview' );

			if( !referenceview ) {
				return;
			}

			var snakList = referenceview.options.listItemAdapter.liValue( $snaklistview );

			// Prevent creating the toolbar for pending values.
			if( snakList !== null ) {
				// Since snakviewstartediting is triggered for every snakview, this creates the
				// toolbar for each snakview widget:
				$snakview.movetoolbar();

				// Disable "move up" button of topmost and "move down" button of bottommost
				// snakview:

				var $topMostSnakview = referenceview.items().first().data( 'snaklistview' )
					._listview.items().first();
				var $bottomMostSnakview = referenceview.items().last().data( 'snaklistview' )
					._listview.items().last();

				if ( $topMostSnakview.get( 0 ) === $snakview.get( 0 ) ) {
					$snakview.data( 'movetoolbar' ).$btnMoveUp.data( 'toolbarbutton' ).disable();
				}

				if( $bottomMostSnakview.get( 0 ) === $snakview.get( 0 ) ) {
					$snakview.data( 'movetoolbar' ).$btnMoveDown.data( 'toolbarbutton' ).disable();
				}
			}
		},
		'referenceviewafterstopediting': function( event, toolbarcontroller ) {
			// Destroy the snakview toolbars:
			var $referenceview = $( event.target ),
				referenceview = $referenceview.data( 'referenceview' );

			if( !referenceview ) {
				// Stopped edit mode of a pending referenceview which does not feature any
				// movetoolbar.
				return;
			}

			var referenceviewLia = referenceview.options.listItemAdapter;

			$.each( referenceview.items(), function( i, snaklistviewNode ) {
				var $snaklistview = $( snaklistviewNode ),
					snaklistview = referenceviewLia.liInstance( $snaklistview ),
					snaklistviewLia = snaklistview._listview.listItemAdapter();

				$.each( snaklistview._listview.items(), function( j, snakviewNode ) {
					var $snakview = $( snakviewNode ),
						snakview = snaklistviewLia.liInstance( $snakview );
					toolbarcontroller.destroyToolbar( snakview.element.data( 'movetoolbar' ) );
				} );

			} );

			// Remove obsolete event handlers attached to the node the toolbarcontroller has been
			// initialized on:
			$referenceview.off( '.movetoolbar' );
		},
		'movetoolbarup movetoolbardown': function( event ) {
			var $snakview = $( event.target ),
				$snaklistview = $snakview.closest( ':wikibase-snaklistview' );

			if( !$snaklistview.length ) {
				// Event belongs to another movetoolbar.
				return;
			}

			var $referenceview = $snaklistview.closest( ':wikibase-referenceview' ),
				referenceview = $referenceview.data( 'referenceview' ),
				snaklistview = referenceview.options.listItemAdapter.liInstance( $snaklistview ),
				snakview = snaklistview._listview.listItemAdapter().liInstance( $snakview ),
				snak = snakview.snak(),
				snakList = snaklistview.value(),
				action = ( event.type === 'movetoolbarup' ) ? 'moveUp' : 'moveDown';

			if( action === 'moveUp' && snakList.indexOf( snak ) !== 0 ) {
				// Move up snakview within a snaklistview.
				snaklistview.moveUp( snak );
			} else if( action === 'moveDown' && snakList.indexOf( snak ) !== snakList.length - 1 ) {
				// Move down snakview within a snaklistview.
				snaklistview.moveDown( snak );
			} else {
				// When issuing "move up" on a snak on top of a snak list, the whole snaklistview
				// has to be move; Same for "move down" on a snak at the bottom of a snak list.
				referenceview[action]( $snaklistview );
			}
		},
		'movetoolbarup movetoolbardown referenceviewitemadded referenceviewitemremoved': function( event ) {
			// Dis- and enable movetoolbar buttons:

			var $target = $( event.target );

			var referenceview = ( event.type.indexOf( 'referenceview' ) === 0 )
				? $target.data( 'referenceview' )
				: $target.closest( ':wikibase-referenceview' ).data( 'referenceview' );

			var referenceviewLia = referenceview.options.listItemAdapter,
				$snaklistviews = referenceview.items();

			$snaklistviews.each( function( i, snaklistviewNode ) {
				var snaklistview = referenceviewLia.liInstance( $( snaklistviewNode ) );

				if( !snaklistview.value() || !snaklistview.isInEditMode() ) {
					// Do not handle pending values.
					return;
				}

				snaklistview._listview.items().each( function( j, snakviewNode ) {
					var $snakview = $( snakviewNode ),
						toolbar = $snakview.data( 'movetoolbar' );

					if( !toolbar ) {
						// Continue if the movetoolbar is not present (the snakview is pending).
						return true;
					}

					var isOverallFirst = ( i === 0 && j === 0 ),
						isLastSnaklistview = ( i === $snaklistviews.length - 1 ),
						isLastSnakview = ( j === snaklistview._listview.items().length - 1 ),
						isOverallLast = ( isLastSnaklistview && isLastSnakview ),
						hasFollowingSnaklistview = ( $snaklistviews.eq( i + 1 ).length > 0 ),
						isBeforePending = false;

					if( hasFollowingSnaklistview ) {
						var nextSnakList = referenceviewLia.liValue( $snaklistviews.eq( i + 1 ) );
						isBeforePending = !nextSnakList;
					}

					toolbar.$btnMoveUp.data( 'toolbarbutton' ).enable();
					toolbar.$btnMoveDown.data( 'toolbarbutton' ).enable();

					if( isOverallFirst ) {
						toolbar.$btnMoveUp.data( 'toolbarbutton' ).disable();
					}

					if( isOverallLast || isBeforePending ) {
						toolbar.$btnMoveDown.data( 'toolbarbutton' ).disable();
					}
				} );
			} );
		}
	}
} );

}( mediaWiki, wikibase, jQuery ) );
