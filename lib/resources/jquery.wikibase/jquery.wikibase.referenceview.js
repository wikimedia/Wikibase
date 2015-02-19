/**
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	var PARENT = $.ui.TemplatedWidget;

/**
 * View for displaying and editing Wikibase Statements.
 * @since 0.4
 * @extends jQuery.ui.TemplatedWidget
 *
 * @option statementGuid {string} (REQUIRED) The GUID of the statement the reference belongs to.
 *
 * @option entityStore {wikibase.store.EntityStore}
 *
 * @option valueViewBuilder {wikibase.ValueViewBuilder}
 *
 * @option referencesChanger {wikibase.entityChangers.ReferencesChanger}
 *
 * @option helpMessage {string} End-user message explaining how to use the referenceview widget. The
 *         message is most likely to be used inside the tooltip of the toolbar corresponding to
 *         the referenceview.
 *         Default: mw.msg( 'wikibase-claimview-snak-new-tooltip' )
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
 * @event toggleerror: Triggered when an error occurred or is resolved.
 *        (1) {jQuery.Event} event
 *        (2) {wikibase.api.RepoApiError|undefined} RepoApiError object if an error occurred, undefined if
 *            the current error state is resolved.
 */
$.widget( 'wikibase.referenceview', PARENT, {
	/**
	 * (Additional) default options.
	 * @see jQuery.Widget.options
	 */
	options: {
		template: 'wikibase-referenceview',
		templateParams: [
			'', // additional css classes
			'' // snaklistview widget
		],
		templateShortCuts: {
			$listview: '.wikibase-referenceview-listview'
		},
		statementGuid: null,
		entityStore: null,
		valueViewBuilder: null,
		referencesChanger: null,
		helpMessage: mw.msg( 'wikibase-claimview-snak-new-tooltip' )
	},

	/**
	 * Reference object represented by this view.
	 * @type {wb.datamodel.Reference}
	 */
	_reference: null,

	/**
	 * Caches the snak list of the reference snaks the referenceview has been initialized with. The
	 * snaks are split into groups featuring the same property. Removing one of those groups results
	 * in losing the reference to those snaks. Therefore, _initialSnakList is used to rebuild the
	 * list of snaks when cancelling and is used to query whether the snaks represent the initial
	 * state.
	 * @type {wb.datamodel.SnakList}
	 */
	_initialSnakList: null,

	/**
	 * Whether the reference is currently in edit mode.
	 * @type {boolean}
	 */
	_isInEditMode: false,

	/**
	 * @see $.wikibase.snaklistview._create
	 *
	 * @throws {Error} if any required option is not specified.
	 */
	_create: function() {
		if(
			!this.options.statementGuid || !this.options.entityStore
			|| !this.options.valueViewBuilder || !this.options.referencesChanger
		) {
			throw new Error( 'Required option(s) missing' );
		}

		PARENT.prototype._create.call( this );

		var self = this;

		if ( this.option( 'value' ) ) {
			this._reference = this.option( 'value' );
			// Overwrite the value since listItemAdapter is the snaklistview prototype which
			// requires a wb.datamodel.SnakList object for initialization:
			// FIXME: Do not overwrite the value
			this._initialSnakList = this._reference.getSnaks();
			this.options.value = this._initialSnakList.getGroupedSnakLists();
		}

		if( !this._initialSnakList ) {
			this._initialSnakList = new wb.datamodel.SnakList();
		}

		this.$listview.listview( {
			listItemAdapter: new $.wikibase.listview.ListItemAdapter( {
				listItemWidget: $.wikibase.snaklistview,
				newItemOptionsFn: function( value ) {
					return {
						value: value || undefined,
						singleProperty: true,
						dataTypeStore: self.options.dataTypeStore,
						entityStore: self.options.entityStore,
						valueViewBuilder: self.options.valueViewBuilder
					};
				}
			} ),
			value: this.option( 'value' )
		} );

		this._updateReferenceHashClass( this.value() );
	},

	/**
	 * Attaches event listeners needed during edit mode.
	 */
	_attachEditModeEventHandlers: function() {
		var self = this,
			lia = this.$listview.data( 'listview' ).listItemAdapter();

		var changeEvents = [
			'snakviewchange.' + this.widgetName,
			'snaklistviewchange.' + this.widgetName,
			'listviewafteritemmove.' + this.widgetName,
			'listviewitemadded.' + this.widgetName,
			'listviewitemremoved.' + this.widgetName
		];

		this.$listview
		.on( changeEvents.join( ' ' ), function( event ) {
			if( event.type === 'listviewitemremoved' ) {
				// Check if last snaklistview item (snakview) has been removed and remove the
				// listview item (the snaklistview itself) if so:
				var $snaklistview = $( event.target ).closest( ':wikibase-snaklistview' ),
					snaklistview = $snaklistview.data( 'snaklistview' );

				if( snaklistview && !snaklistview.value() ) {
					self.$listview.data( 'listview' ).removeItem( snaklistview.element );
				}
			}

			// Propagate "change" event.
			self._trigger( 'change' );
		} )
		.one( lia.prefixedEvent( 'stopediting.' + this.widgetName ),
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
		var lia = this.$listview.data( 'listview' ).listItemAdapter(),
			events = [
				'snakviewchange.' + this.widgetName,
				'listviewafteritemmove.' + this.widgetName,
				'listviewitemadded.' + this.widgetName,
				'listviewitemremoved.' + this.widgetName,
				lia.prefixedEvent( 'change.' + this.widgetName ),
				lia.prefixedEvent( 'stopediting.' + this.widgetName )
			];
		this.$listview.off( events.join( ' ' ) );
	},

	/**
	 * Will update the 'wb-reference-<hash>' class on the widget's root element to a given
	 * reference's hash. If null is given or if the reference has no hash, 'wb-reference-new' will
	 * be added as class.
	 *
	 * @param {wb.datamodel.Reference|null} reference
	 */
	_updateReferenceHashClass: function( reference ) {
		var refHash = reference && reference.getHash() || 'new';

		this.element.removeClassByRegex( /wb-reference-.+/ );
		this.element.addClass( 'wb-reference-' + refHash );

		this.element.removeClassByRegex( new RegExp( this.widgetBaseClass + '-.+' ) );
		this.element.addClass( this.widgetBaseClass + '-' + refHash );
	},

	/**
	 * Sets/Returns the current reference represented by the view. In case of an empty reference
	 * view, without any snak values set yet, null will be returned.
	 * @see $.wikibase.snaklistview.value
	 * @since 0.4
	 *
	 * @param {wb.datamodel.Reference} [reference] New reference to be set
	 * @return {wb.datamodel.Reference|null}
	 */
	value: function( reference ) {
		if ( reference ) {
			if ( !( reference instanceof wb.datamodel.Reference ) ) {
				throw new Error( 'Value has to be an instance of wikibase.datamodel.Reference' );
			}
			this._reference = reference;
			return this._reference;
		} else {
			var snakList = new wb.datamodel.SnakList();

			$.each( this.$listview.data( 'listview' ).value(), function() {
				var snakListForProperty = this.value();
				if( snakListForProperty ) {
					snakList.merge( snakListForProperty );
				}
			} );

			if ( this._reference ) {
				return new wb.datamodel.Reference( snakList || [], this._reference.getHash() );
			} else if ( snakList.length ) {
				return new wb.datamodel.Reference( snakList );
			} else {
				return null;
			}
		}
	},

	/**
	 * Starts the widget's edit mode.
	 * @since 0.5
	 */
	startEditing: function() {
		if( this.isInEditMode() ) {
			return;
		}

		$.each( this.$listview.data( 'listview' ).value(), function() {
			this.startEditing();
		} );

		this._attachEditModeEventHandlers();

		this.element.addClass( 'wb-edit' );
		this._isInEditMode = true;

		this._trigger( 'afterstartediting' );
	},

	/**
	 * Stops the widget's edit mode.
	 * @since 0.5
	 */
	stopEditing: function( dropValue ) {
		if ( !this.isInEditMode() || ( !this.isValid() || this.isInitialValue() ) && !dropValue ) {
			return;
		}

		this._trigger( 'stopediting', null, [dropValue] );

		var self = this;

		this.element.removeClass( 'wb-error' );
		this._detachEditModeEventHandlers();
		this.disable();

		if( dropValue ) {
			this._stopEditingReferenceSnaks( dropValue );

			this.enable();
			this.element.removeClass( 'wb-edit' );
			this._isInEditMode = false;

			this._trigger( 'afterstopediting', null, [ dropValue ] );
		} else {
			this._saveReferenceApiCall()
			.done( function( savedObject ) {
				self._stopEditingReferenceSnaks( dropValue );

				self.enable();

				self.element.removeClass( 'wb-edit' );
				self._isInEditMode = false;

				self._trigger( 'afterstopediting', null, [ dropValue ] );
			} )
			.fail( function( error ) {
				self.enable();

				self._attachEditModeEventHandlers();
				self.setError( error );
			} );
		}
	},

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
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			$snaklistviews = listview.items(),
			i;

		if( !dropValue ) {
			// When saving the qualifier snaks, reset the initial qualifiers to the new ones.
			this._initialSnakList = new wb.datamodel.SnakList();
		}

		if( $snaklistviews.length ) {
			for( i = 0; i < $snaklistviews.length; i++ ) {
				var snaklistview = lia.liInstance( $snaklistviews.eq( i ) );
				snaklistview.stopEditing( dropValue );

				if( dropValue && !snaklistview.value() ) {
					// Remove snaklistview from referenceview if no snakviews are left in
					// that snaklistview:
					listview.removeItem( snaklistview.element );
				} else if ( !dropValue ) {
					// Gather all the current snaks in a single SnakList to set to reset the
					// initial qualifiers:
					this._initialSnakList.merge( snaklistview.value() );
				}
			}
		}

		this.clear();

		var snakLists = this._initialSnakList.getGroupedSnakLists();

		if( snakLists ) {
			for( i = 0; i < snakLists.length; i++ ) {
				listview.addItem( snakLists[i] );
			}
		}
	},

	/**
	 * Clears the referenceview's content.
	 * @since 0.5
	 */
	clear: function() {
		var listview = this.$listview.data( 'listview' ),
			items = listview.items();

		for( var i = 0; i < items.length; i++ ) {
			listview.removeItem( items.eq( i ) );
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
		var isValid = true;
		$.each( this.$listview.data( 'listview' ).value(), function() {
			if( !this.isValid() ) {
				isValid = false;
			}
			return isValid;
		} );
		return isValid;
	},

	/**
	 * Returns whether the `referenceview`'s current value matches the value it has been initialized
	 * with by checking the `Reference`'s `Snak`s.
	 * @since 0.5
	 *
	 * @return {boolean}
	 */
	isInitialValue: function() {
		var currentReference = this.value(),
			currentSnakList = currentReference
				? currentReference.getSnaks()
				: new wb.datamodel.SnakList();
		return currentSnakList.equals( this._initialSnakList );
	},

	/**
	 * Adds a pending `snaklistview` to the referenceview.
	 * @see jQuery.wikibase.listview.enterNewItem
	 * @since 0.5
	 *
	 * @return {Object} jQuery.Promise
	 * @return {Function} return.done
	 * @return {jQuery} return.done.$snaklistview
	 */
	enterNewItem: function() {
		var self = this,
			listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		this.startEditing();

		return listview.enterNewItem().done( function( $snaklistview ) {
			lia.liInstance( $snaklistview ).enterNewItem()
			.done( function() {
				// Since the new snakview will be initialized empty which invalidates the
				// snaklistview, external components using the snaklistview will be noticed via
				// the "change" event.
				self._trigger( 'change' );
			} );
		} );
	},

	/**
	 * Triggers the API call to save the reference.
	 * @since 0.4
	 *
	 * @return {jQuery.promise}
	 */
	_saveReferenceApiCall: function() {
		var self = this,
			guid = this.option( 'statementGuid' );

		return this.option( 'referencesChanger' ).setReference( guid, this.value() )
			.done( function( savedReference ) {
			self._reference = savedReference;
			self._updateReferenceHashClass( savedReference );
		} );
	},

	/**
	 * Sets/removes error state from the widget.
	 * @since 0.4
	 *
	 * @param {wikibase.api.RepoApiError} [error]
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
	 * @see jQuery.ui.TemplatedWidget._setOption
	 */
	_setOption: function( key, value ) {
		var response = PARENT.prototype._setOption.apply( this, arguments );

		if( key === 'disabled' ) {
			this.$listview.data( 'listview' ).option( key, value );
		}

		return response;
	},

	/**
	 * @see jQuery.ui.TemplatedWidget.focus
	 */
	focus: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			$items = listview.items();

		if( $items.length ) {
			lia.liInstance( $items.first() ).focus();
		} else {
			this.element.focus();
		}
	}
} );

}( mediaWiki, wikibase, jQuery ) );
