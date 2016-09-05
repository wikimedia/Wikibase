( function( wb, $ ) {
	'use strict';

	var PARENT = $.ui.TemplatedWidget;

/**
 * View for displaying and editing `wikibase.datamodel.Reference` objects.
 * @see wikibase.datamodel.Reference
 * @class jQuery.wikibase.referenceview
 * @extends jQuery.ui.TemplatedWidget
 * @since 0.4
 * @license GPL-2.0+
 * @author H. Snater < mediawiki@snater.com >
 *
 * @constructor
 *
 * @param {Object} options
 * @param {wikibase.datamodel.Reference|null} options.value
 * @param {jQuery.wikibase.listview.ListItemAdapter} options.listItemAdapter
 */
/**
 * @event afterstartediting
 * Triggered after having started the widget's edit mode and edit mode has been rendered.
 * @param {jQuery.Event} event
 */
/**
 * @event afterstopediting
 * Triggered after having stopped the widget's edit mode and non-edit mode is redrawn.
 * @param {boolean} dropValue
 *        Whether the widget's value has been be reset to the one from before starting edit mode.
 */
/**
 * @event change
 * Triggered whenever the `Reference` represented by the widget is changed.
 * @param {jQuery.Event} event
 */
/**
 * @event toggleerror
 * Triggered when an error occurred or has been resolved.
 * @param {jQuery.Event} event
 * @param {boolean} error Whether an error occurred
 */
$.widget( 'wikibase.referenceview', PARENT, {
	/**
	 * @inheritdoc
	 */
	options: {
		template: 'wikibase-referenceview',
		templateParams: [
			'', // additional css classes
			'' // snaklistview widget
		],
		templateShortCuts: {
			$heading: '.wikibase-referenceview-heading',
			$listview: '.wikibase-referenceview-listview'
		},
		value: null,
		listItemAdapter: null
	},

	/**
	 * Whether the widget is currently in edit mode.
	 * @property {boolean} [_isInEditMode=false]
	 * @private
	 */
	_isInEditMode: false,

	/**
	 * @inheritdoc
	 * @protected
	 *
	 * @throws {Error} if a required option is not specified properly.
	 */
	_create: function() {
		if ( !this.options.listItemAdapter ) {
			throw new Error( 'Required option not specified properly' );
		}

		PARENT.prototype._create.call( this );

		this.$listview.listview( {
			listItemAdapter: this.options.listItemAdapter,
			value: this.options.value ? this.options.value.getSnaks().getGroupedSnakLists() : []
		} );

		this._updateReferenceHashClass( this.value() );

		this._referenceRemover = this.options.getReferenceRemover( this.$heading );
	},

	/**
	 * Attaches event listeners needed during edit mode.
	 *
	 * @private
	 */
	_attachEditModeEventHandlers: function() {
		var self = this,
			listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		var changeEvents = [
			'snakviewchange.' + this.widgetName,
			lia.prefixedEvent( 'change.' + this.widgetName ),
			'listviewitemremoved.' + this.widgetName
		];

		this.$listview
		.on( changeEvents.join( ' ' ), function( event ) {
			if ( event.type === 'listviewitemremoved' ) {
				// Check if last snaklistview item (snakview) has been removed and remove the
				// listview item (the snaklistview itself) if so:
				var $snaklistview = $( event.target ).closest( ':wikibase-snaklistview' ),
					snaklistview = lia.liInstance( $snaklistview );

				if ( snaklistview && !snaklistview.value().length ) {
					listview.removeItem( $snaklistview );
				}
			}

			// Propagate "change" event.
			self._trigger( 'change' );
		} );
	},

	/**
	 * Detaches the event handlers needed during edit mode.
	 *
	 * @private
	 */
	_detachEditModeEventHandlers: function() {
		var lia = this.$listview.data( 'listview' ).listItemAdapter(),
			events = [
				'snakviewchange.' + this.widgetName,
				'listviewitemremoved.' + this.widgetName,
				lia.prefixedEvent( 'change.' + this.widgetName )
			];
		this.$listview.off( events.join( ' ' ) );
	},

	/**
	 * Will update the `wb-reference-<hash>` CSS class on the widget's root element to a given
	 * `Reference`'s hash. If `null` is given or if the `Reference` has no hash, `wb-reference-new`
	 * will be added as class.
	 *
	 * @private
	 *
	 * @param {wikibase.datamodel.Reference|null} reference
	 */
	_updateReferenceHashClass: function( reference ) {
		var refHash = reference && reference.getHash() || 'new';

		this.element.removeClassByRegex( /wb-reference-.+/ );
		this.element.addClass( 'wb-reference-' + refHash );

		this.element.removeClassByRegex( new RegExp( this.widgetBaseClass + '-.+' ) );
		this.element.addClass( this.widgetBaseClass + '-' + refHash );
	},

	/**
	 * Sets the `Reference` to be represented by the view or returns the `Reference` currently
	 * represented by the widget.
	 *
	 * @param {wikibase.datamodel.Reference|null} [reference]
	 * @return {wikibase.datamodel.Reference|null|undefined}
	 */
	value: function( reference ) {
		if ( reference ) {
			return this.option( 'value', reference );
		}

		if ( !this.$listview ) {
			return null;
		}

		var snakList = new wb.datamodel.SnakList();

		if ( !this.$listview.data( 'listview' ).value().every( function( snaklistview ) {
			var value = snaklistview.value();
			snakList.merge( value );
			return value;
		} ) ) {
			return null;
		}

		if ( this.options.value || snakList.length ) {
			return new wb.datamodel.Reference(
				snakList,
				this.options.value ? this.options.value.getHash() : undefined
			);
		}

		return null;
	},

	/**
	 * Starts the widget's edit mode.
	 *
	 * @since 0.5
	 */
	startEditing: function() {
		if ( this.isInEditMode() ) {
			return;
		}

		this.$listview.data( 'listview' ).startEditing();

		this._attachEditModeEventHandlers();

		this.element.addClass( 'wb-edit' );
		this._isInEditMode = true;

		this._snakListAdder = this.options.getAdder( this.enterNewItem.bind( this ), this.element );

		this._trigger( 'afterstartediting' );
	},

	/**
	 * Stops the widget's edit mode.
	 *
	 * @since 0.5
	 */
	stopEditing: function() {
		if ( !this.isInEditMode() ) {
			return;
		}

		this._snakListAdder.destroy();
		this._snakListAdder = null;

		this._isInEditMode = false;
		this.element.removeClass( 'wb-edit' );

		this._detachEditModeEventHandlers();

		// FIXME: There should be a listview::stopEditing method
		this._stopEditingReferenceSnaks();

		this._trigger( 'afterstopediting' );
	},

	/**
	 * @private
	 */
	_stopEditingReferenceSnaks: function() {
		var listview = this.$listview.data( 'listview' );
		listview.value( this.options.value ? this.options.value.getSnaks().getGroupedSnakLists() : [] );
	},

	/**
	 * Returns whether the widget is currently in edit mode.
	 *
	 * @since 0.5
	 *
	 * @return {boolean}
	 */
	isInEditMode: function() {
		return this._isInEditMode;
	},

	/**
	 * Adds a pending `snaklistview` to the widget.
	 *
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
			.done( function( $snakview ) {
				// Since the new snakview will be initialized empty which invalidates the
				// snaklistview, external components using the snaklistview will be noticed via
				// the "change" event.
				self._trigger( 'change' );
				$snakview.data( 'snakview' ).focus();
			} );
		} );
	},

	/**
	 * Sets/removes error state from the widget.
	 *
	 * @param {boolean} error
	 */
	setError: function( error ) {
		this.element.toggleClass( 'wb-error', error );
		this._trigger( 'toggleerror', null, [ error ] );
	},

	/**
	 * @inheritdoc
	 * @protected
	 *
	 * @throws {Error} when trying to set the value to something different that a
	 *         `wikibase.datamodel.Reference` object.
	 */
	_setOption: function( key, value ) {
		if ( key === 'value' ) {
			if ( !( value instanceof wb.datamodel.Reference ) ) {
				throw new Error( 'Value has to be an instance of wikibase.datamodel.Reference' );
			}
			// TODO: Redraw
			this._updateReferenceHashClass( value );
		}

		var response = PARENT.prototype._setOption.apply( this, arguments );

		if ( key === 'disabled' ) {
			this.$listview.data( 'listview' ).option( key, value );
			this._referenceRemover[ value ? 'disable' : 'enable' ]();
			if ( this._snakListAdder ) {
				this._snakListAdder[ value ? 'disable' : 'enable' ]();
			}
		}

		return response;
	},

	/**
	 * @inheritdoc
	 */
	focus: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			$items = listview.items();

		if ( $items.length ) {
			lia.liInstance( $items.first() ).focus();
		} else {
			this.element.focus();
		}
	}

} );

}( wikibase, jQuery ) );
