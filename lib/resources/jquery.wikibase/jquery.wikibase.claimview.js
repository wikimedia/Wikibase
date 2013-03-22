/**
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	var PARENT = $.TemplatedWidget;

/**
 * View for displaying and editing Wikibase Claims.
 * @since 0.3
 *
 * @option value {wb.Claim|null} The claim displayed by this view. This can only be set initially,
 *         the value function doesn't work as a setter in this view. If this is null, this view will
 *         start in edit mode, allowing the user to define the claim.
 *
 * @option predefined {Object} Allows to pre-define certain aspects of the Claim to be created.
 *         Basically, when creating a new Claim, what really is created first is the Main Snak. So,
 *         this requires a field 'mainSnak' which can have all fields which can be defined in
 *         jQuery.snakview's option 'predefined'. E.g. "predefined.mainSnak.property = 'q42'"
 *         TODO: also allow pre-defining aspects of qualifiers. Implementation and whether this
 *               makes sense here might depend on whether we will have one or several edit buttons.
 *
 * @option locked {Object} Elements that shall be locked (disabled).
 *
 * @option helpMessage {string} End-user message explaining how to use the claimview widget. The
 *         message is most likely to be used inside the tooltip of the toolbar corresponding to
 *         the claimview.
 *
 * @event startediting: Triggered when starting the Claim's edit mode.
 *        (1) {jQuery.Event}
 *
 * @event afterstartediting: Triggered after having started the Claim's edit mode.
 *        (1) {jQuery.Event}
 *
 * @event stopediting: Triggered when stopping the Claim's edit mode.
 *        (1) {jQuery.Event}
 *        (2) {boolean} If true, the value from before edit mode has been started will be reinstated
 *            (basically a cancel/save switch).
 *
 * @event afterstopediting: Triggered after having stopped the Claim's edit mode.
 *        (1) {jQuery.Event}
 *        (2) {boolean} If true, the value from before edit mode has been started will be reinstated
 *            (basically a cancel/save switch).
 *
 * @event remove: Triggered when removing the claim.
 *        (1) {jQuery.Event} event
 *
 * @event change: Triggered whenever the claimview's content is changed.
 *        (1) {jQuery.Event} event
 *
 * @event toggleerror: Triggered when an error occurred or is resolved.
 *        (1) {jQuery.Event} event
 *        (2) {wb.RepoApiError|undefined} wb.RepoApiError object if an error occurred, undefined if
 *            the current error state is resolved.
 */
$.widget( 'wikibase.claimview', PARENT, {
	widgetName: 'wikibase-claimview',
	widgetBaseClass: 'wb-claimview',

	/**
	 * (Additional) default options
	 * @see jQuery.Widget.options
	 */
	options: {
		template: 'wb-claim',
		templateParams: [
			function() { // class='wb-claim-$2'
				return ( this._claim && this._claim.getGuid() ) || 'new';
			},
			'wb-last', // class: wb-first|wb-last
			'' // .wb-claim-mainsnak
		],
		templateShortCuts: {
			'$mainSnak': '.wb-claim-mainsnak'
		},
		value: null,
		predefined: {
			mainSnak: false
		},
		locked: {
			mainSnak: false
		},
		helpMessage: mw.msg( 'wikibase-claimview-snak-new-tooltip' )
	},

	/**
	 * The node representing the main snak, displaying it in a jQuery.snakview
	 * @type jQuery
	 */
	$mainSnak: null,

	/**
	 * The anchor object of an error tooltip when one is set.
	 * @type Object
	 */
	errorTooltipAnchor: null,

	/**
	 * The claim represented by this view or null if this is a view for a user to enter a new claim.
	 * @type wb.Claim|null
	 */
	_claim: null,

	/**
	 * Whether the Claim is currently in edit mode.
	 * @type {boolean}
	 */
	_isInEditMode: false,

	/**
	 * Whether the widget is currently valid according to its contents.
	 * @type {boolean}
	 */
	_isValid: false,

	/**
	 * @see jQuery.Widget._create
	 */
	_create: function() {
		var self = this;
		this._claim = this.option( 'value' );

		// call template creation, this will require this._claim in template params callback!
		PARENT.prototype._create.call( this );

		// set up event listeners:
		this.$mainSnak
		.on ( 'snakviewchange', function( event, status ) {
			var snakview = self.$mainSnak.data( 'snakview' );
			self._isValid = ( snakview.isValid() && !snakview.isInitialSnak() );
			self._trigger( 'change' );
		} )
		.on( 'snakviewstopediting', function( event, dropValue ) {
			// React on key stroke events (e.g. pressing enter or ESC key)
			if ( !self.isValid() && !dropValue ) {
				event.preventDefault();
				return;
			}

			if ( !self.__continueStopEditing ) {
				// Do not exit snakview's edit mode yet; Let any API request be performed first.
				event.preventDefault();
				self.stopEditing( dropValue );
			} else {
				self.__continueStopEditing = false;
			}
		} )
		.on( 'valueviewchange', function( e ) {
			if ( self.errorTooltipAnchor ) {
				self.errorTooltipAnchor.getTooltip().hide();
			}
		} );

		this.$mainSnak.snakview( {
			value: this.mainSnak() || {},
			locked: this.option( 'locked' ).mainSnak,
			autoStartEditing: false // manually, after toolbar is there, so events can access toolbar
		} );

		if ( this._claim || this.options.predefined.mainSnak ) {
			var property = this._claim
				? this.mainSnak().getPropertyId()
				: this.options.predefined.mainSnak.property;

			var fetchedProperty = wb.fetchedEntities[ property ];
			if( fetchedProperty ) {
				this.options.helpMessage = mw.msg(
					'wikibase-claimview-snak-tooltip', fetchedProperty.getContent().getLabel() );
			}
		}
	},

	/**
	 * Returns whether the claimview is valid according to its current contents.
	 *
	 * @return {boolean}
	 */
	isValid: function() {
		return this._isValid;
	},

	/**
	 * Starts the Claim's edit mode.
	 * @since 0.4
	 *
	 * @return {undefined} (allows chaining widget calls)
	 */
	startEditing: $.NativeEventHandler( 'startEditing', {
		// don't start edit mode or trigger event if in edit mode already:
		initially: function( e ) {
			if( this.isInEditMode() ) {
				e.cancel();
			}
		},
		// start edit mode if event doesn't prevent default:
		natively: function( e ) {
			var snakview = this.$mainSnak.data( 'snakview' );

			this.$mainSnak.data( 'snakview' ).startEditing();

			this.element.addClass( 'wb-edit' );
			this._isInEditMode = true;

			this._isValid = ( snakview.isValid() && !snakview.isInitialSnak() );

			this._trigger( 'afterstartediting' );
		}
	} ),

	/**
	 * Exits the Claim's edit mode.
	 * @since 0.4
	 *
	 * @param {boolean} [dropValue] If true, the value from before edit mode has been started will
	 *        be reinstated - basically a cancel/save switch. "false" by default. Consider using
	 *        cancelEditing() instead.
	 * @return {undefined} (allows chaining widget calls)
	 */
	stopEditing: $.NativeEventHandler( 'stopEditing', {
		// don't stop edit mode or trigger event if not in edit mode currently:
		initially: function( e, dropValue ) {
			if( !this.isInEditMode() || !this.isValid() && !dropValue ) {
				e.cancel();
			}

			this.__continueStopEditing = true;

			this.element.removeClass( 'wb-error' );
		},
		// start edit mode if custom event handlers didn't prevent default:
		natively: function( e, dropValue ) {
			var self = this;

			this.disable();

			if ( dropValue ) {
				// nothing to update
				if ( this.$mainSnak.data( 'snakview' ) ) {
					this.$mainSnak.data( 'snakview' ).stopEditing( dropValue );
				}

				self.enable();
				self.element.removeClass( 'wb-edit' );
				self._isInEditMode = false;

				self._trigger( 'afterstopediting', null, [ dropValue ] );
			} else if ( this._claim ) {
				// editing an existing claim
				self._saveClaimApiCall()
				.done( function( savedClaim, pageInfo ) {
					self.$mainSnak.data( 'snakview' ).stopEditing( dropValue );

					self.enable();

					if ( !self._claim ) {
						// claim must be newly entered, create a new claim:
						self._claim = new wb.Claim(
							self.$mainSnak.data( 'snakview' ).value()
						);
					}

					self.element.removeClass( 'wb-edit' );
					self._isInEditMode = false;

					// transform toolbar and snak view after save complete
					self._trigger( 'afterstopediting', null, [ dropValue ] );
				} )
				.fail( function( errorCode, details ) {
					var error = wb.RepoApiError.newFromApiResponse(
							errorCode, details, 'save'
						);

					self.enable();
					self.element.addClass( 'wb-error' );

					self._trigger( 'toggleError', null, [ error ] );

					self.__continueStopEditing = false;
				} );
			} else {
				// Adding a new claim is managed in claimlistview and will end up in here after
				// having performed the API call adding the claim.
				self.element.removeClass( 'wb-edit' );
				this._isInEditMode = false;
				self._trigger( 'afterstopediting', null, [ dropValue ] );
			}
		}
	} ),

	/**
	 * Short-cut for stopEditing( false ). Exits edit mode and restores the value from before the
	 * edit mode has been started.
	 * @since 0.4
	 *
	 * @return {undefined} (allows chaining widget calls)
	 */
	cancelEditing: function() {
		return this.stopEditing( true ); // stop editing and drop value
	},

	/**
	 * Removes the claimview.
	 */
	remove: $.NativeEventHandler( 'remove', {
		// TODO: Removing should probably be managed by the object containing the claimview.
		// ($.claimview may be used stand-alone)
		initially: function( e ) {
			// Do not trigger event if not in edit mode.
			if( !this.isInEditMode() ) {
				e.cancel();
			}
		},
		// start edit mode if event doesn't prevent default:
		natively: function( e ) {
			var self = this;

			this._removeClaimApiCall()
			.done( function( savedClaim, pageInfo ) {
				// NOTE: we don't update rev store here! If we want uniqueness for Claims, this
				//  might be an issue at a later point and we would need a solution then

				// update model of represented Claim
				self._trigger( 'afterremove' );
			} ).fail( function( errorCode, details ) {
				var error = wb.RepoApiError.newFromApiResponse( errorCode, details, 'remove' );

				self.enable();
				self.element.addClass( 'wb-error' );

				self._trigger( 'toggleError', null, [ error ] );
			} );
		}
	} ),

	/**
	 * Returns whether the Claim is editable at the moment.
	 * @since 0.4
	 *
	 * @return {boolean}
	 */
	isInEditMode: function() {
		return this._isInEditMode;
	},

	/**
	 * Triggers the API call to save the claim.
	 * @since 0.4
	 *
	 * TODO: would be nice to have all API related stuff out of here to allow concentrating on
	 *       MVVM relation.
	 *
	 * @return {jQuery.Promise}
	 */
	_saveClaimApiCall: function() {
		var self = this,
			api = new wb.RepoApi(),
			revStore = wb.getRevisionStore(),
			guid = this.value().getGuid(),
			claim = new wb.Claim(
				self.$mainSnak.data( 'snakview' ).snak(),
				new wb.SnakList(), // TODO: Qualifiers
				guid
			);

		return api.setClaim( claim, revStore.getClaimRevision( guid ) )
		.done( function( savedClaim, pageInfo ) {
			// Update revision store:
			revStore.setClaimRevision( pageInfo.lastrevid, savedClaim.getGuid() );

			// Update model of represented Claim:
			self._claim = savedClaim;
		} );
	},

	/**
	 * Triggers the API call to remove the Claim.
	 * @since 0.4
	 *
	 * TODO: same as for _saveMainSnakApiCall(), get API related stuff out of here!
	 *
	 * @return jQuery.Promise
	 */
	_removeClaimApiCall: function() {
		var guid = this.value().getGuid(),
			api = new wb.RepoApi(),
			revStore = wb.getRevisionStore();

		return api.removeClaim(
			guid,
			revStore.getClaimRevision( guid )
		);
	},

	/**
	 * Disables the Claim view.
	 * @since 0.4
	 */
	disable: function() {
		this.$mainSnak.data( 'snakview' ).disable();
	},

	/**
	 * Enables the Claim view.
	 * @since 0.4
	 */
	enable: function() {
		this.$mainSnak.data( 'snakview' ).enable();
	},

	/**
	 * @see $.widget.destroy
	 */
	destroy: function() {
		this.$mainSnak.snakview( 'destroy' );
		PARENT.prototype.destroy.call( this );
	},

	/**
	 * Returns the current Claim represented by the view. If null is returned, than this is a
	 * fresh view where a new Claim is being constructed.
	 * @since 0.3
	 *
	 * @return wb.Claim|null
	 */
	value: function() {
		return this._claim;
	},

	/**
	 * Returns the current Claim's main snak or null if no Claim is represented by the view
	 * currently (because Claim not yet constructed). This is a short cut to value().getMainSnak().
	 *
	 * NOTE: this function has been introduced for the big referenceview hack, where we let the
	 *  referenceview widget inherit from the claimview until qualifiers will be implemented - and
	 *  therefore a more generic base widget which will serve as base for both - claimview and
	 *  referenceview.
	 *
	 * @deprecated Use .value() instead.
	 *
	 * @since 0.4
	 *
	 * @return wb.Snak|null
	 */
	mainSnak: function() {
		return this._claim
			? this._claim.getMainSnak()
			: ( this.option( 'predefined' ).mainSnak || null );
	},

	/**
	 * @see jQuery.widget._setOption
	 * We are using this to disallow changing the value option afterwards
	 */
	_setOption: function( key, value ) {
		if( key === 'value' ) {
			throw new Error( 'Can not set value after initialization' );
		}
		$.Widget.prototype._setOption.call( key, value );
	}
} );

}( mediaWiki, wikibase, jQuery ) );
