/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, $ ) {
	'use strict';

	var PARENT = $.ui.TemplatedWidget;

/**
 * Encapsulates a entitytermsforlanguagelistview widget.
 * @since 0.5
 * @extends jQuery.ui.TemplatedWidget
 *
 * @option {Object[]} value
 *         Object representing the widget's value.
 *         Structure: [
 *           {
 *             language: <{string]>,
 *             label: <{wikibase.datamodel.Term}>,
 *             description: <{wikibase.datamodel.Term}>
 *             aliases: <{wikibase.datamodel.MultiTerm}>
 *           }[, ...]
 *         ]
 *
 * @option {string} entityId
 *
 * @option {wikibase.entityChangers.EntityChangersFactory} entityChangersFactory
 *
 * @option {string} [helpMessage]
 *                  Default: 'Edit label, description and aliases per language.'
 *
 * @event change
 *        - {jQuery.Event}
 *
 * @event afterstartediting
 *       - {jQuery.Event}
 *
 * @event stopediting
 *        - {jQuery.Event}
 *        - {boolean} Whether to drop the value.
 *        - {Function} Callback function.
 *
 * @event afterstopediting
 *        - {jQuery.Event}
 *        - {boolean} Whether to drop the value.
 *
 * @event toggleerror
 *        - {jQuery.Event}
 *        - {Error|null}
 */
$.widget( 'wikibase.entitytermsview', PARENT, {
	options: {
		template: 'wikibase-entitytermsview',
		templateParams: [
			'', // label class
			'', // labelview
			'', // aliases class
			'', // aliasesview
			'', // description class
			'', // descriptionview
			'', // entitytermsforlanguagelistview
			'', // additional entitytermsforlanguagelistview container class(es)
			'' // toolbar placeholder
		],
		templateShortCuts: {
			$headingLabel: '.wikibase-entitytermsview-heading-label',
			$headingAliases: '.wikibase-entitytermsview-heading-aliases',
			$headingDescription: '.wikibase-entitytermsview-heading-description',
			$entitytermsforlanguagelistviewContainer:
				'.wikibase-entitytermsview-entitytermsforlanguagelistview'
		},
		value: [],
		entityId: null,
		entityChangersFactory: null,
		helpMessage: 'Edit label, description and aliases per language.'
	},

	/**
	 * @type {boolean}
	 */
	_isInEditMode: false,

	/**
	 * @type {jQuery}
	 */
	$entitytermsforlanguagelistview: null,

	/**
	 * @type {jQuery}
	 */
	$entitytermsforlanguagelistviewToggler: null,

	/**
	 * @see jQuery.ui.TemplatedWidget._create
	 */
	_create: function() {
		if(
			!$.isArray( this.options.value )
			|| !this.options.entityId
			|| !this.options.entityChangersFactory
		) {
			throw new Error( 'Required option(s) missing' );
		}

		PARENT.prototype._create.call( this );

		this.element.addClass( 'wikibase-entitytermsview' );

		this.$entitytermsforlanguagelistview
			= this.element.find( '.wikibase-entitytermsforlanguagelistview' );

		if( !this.$entitytermsforlanguagelistview.length ) {
			this.$entitytermsforlanguagelistview = $( '<div/>' )
				.appendTo( this.$entitytermsforlanguagelistviewContainer );
		}

		this._createEntitytermsforlanguagelistview();

		// TODO: Remove as soon as drop-down edit buttons are implemented. The language list may
		// then be shown (without directly switching to edit mode) using the drop down menu.
		this._createEntiytermsforlanguagelistviewToggler();

		var self = this;

		this.element
		.on(
			this.widgetEventPrefix + 'change.' + this.widgetName
				+ ' ' + this.widgetEventPrefix + 'afterstopediting.' + this.widgetName,
			function() {
				$.each( self.value(), function() {
					if( this.language === mw.config.get( 'wgUserLanguage' ) ) {
						var $labelChildren = self.$headingLabel.children();
						self.$headingLabel.text( this.label.getText() ).append( $labelChildren );
						self.$headingDescription.text( this.description.getText() );

						var aliasesTexts = this.aliases.getTexts(),
							$ul = self.$headingAliases.children( 'ul' ).empty();

						for( var i = 0; i < aliasesTexts.length; i++ ) {
							$ul.append(
								mw.wbTemplate( 'wikibase-entitytermsview-aliases-alias',
									aliasesTexts[i]
								)
							);
						}

						return false;
					}
				} );
			}
		);
	},

	/**
	 * @see jQuery.ui.TemplatedWidget.destroy
	 */
	destroy: function() {
		// When destroying a widget not initialized properly, entitytermsforlanguagelistview will
		// not have been created.
		if( this.$entitytermsforlanguagelistview ) {
			var entitytermsforlanguagelistview = this._getEntitytermsforlanguagelistview();

			if( entitytermsforlanguagelistview ) {
				entitytermsforlanguagelistview.destroy();
			}

			this.$entitytermsforlanguagelistview.remove();
		}

		this.$entitytermsforlanguagelistviewToggler.remove();

		this.element.off( '.' + this.widgetName );
		this.element.removeClass( 'wikibase-entitytermsview' );
		PARENT.prototype.destroy.call( this );
	},

	/**
	 * Creates the dedicated toggler for showing/hiding the list of entity terms. This function is
	 * supposed to be removed as soon as drop-down edit buttons are implemented with the mechanism
	 * toggling the list's visibility while not starting edit mode will be part of the drop-down
	 * menu.
	 * @private
	 */
	_createEntiytermsforlanguagelistviewToggler: function() {
		var api = new mw.Api();

		this.$entitytermsforlanguagelistviewToggler = $( '<div/>' )
			.addClass( 'wikibase-entitytermsview-entitytermsforlanguagelistview-toggler' )
			.text( mw.msg( 'wikibase-entitytermsview-entitytermsforlanguagelistview-toggler' ) )
			.toggler( {
				$subject: this.$entitytermsforlanguagelistviewContainer
			} )
			.on( 'toggleranimation.' + this.widgetName, function( event, params ) {
				if( mw.user.isAnon() ) {
					$.cookie(
						'wikibase-entiytermsview-showEntitytermslistview',
						params.visibility,
						{ expires: 365, path: '/' }
					);
				} else {
					api.postWithToken( 'options', {
						action: 'options',
						optionname: 'wikibase-entiytermsview-showEntitytermslistview',
						optionvalue: params.visibility ? '1' : '0'
					} )
					.done( function() {
						mw.user.options.set(
							'wikibase-entiytermsview-showEntitytermslistview',
							params.visibility ? '1' : '0'
						);
					} );
				}
			} );

		this.$entitytermsforlanguagelistviewContainer.before(
			this.$entitytermsforlanguagelistviewToggler
		);
	},

	/**
	 * @return {jQuery.wikibase.entitytermsforlanguagelistview}
	 * @private
	 */
	_getEntitytermsforlanguagelistview: function() {
		return this.$entitytermsforlanguagelistview.data( 'entitytermsforlanguagelistview' );
	},

	/**
	 * Creates and initializes the entitytermsforlanguagelistview widget.
	 */
	_createEntitytermsforlanguagelistview: function() {
		var self = this,
			prefix = $.wikibase.entitytermsforlanguagelistview.prototype.widgetEventPrefix;

		this.$entitytermsforlanguagelistview
		.on( prefix + 'change.' + this.widgetName, function( event ) {
			event.stopPropagation();
			self._trigger( 'change' );
		} )
		.on( prefix + 'toggleerror.' + this.widgetName, function( event, error ) {
			event.stopPropagation();
			self.setError( error );
		} )
		.on(
			[
				prefix + 'create.' + this.widgetName,
				prefix + 'afterstartediting.' + this.widgetName,
				prefix + 'stopediting.' + this.widgetName,
				prefix + 'afterstopediting.' + this.widgetName,
				prefix + 'disable.' + this.widgetName
			].join( ' ' ),
			function( event ) {
				event.stopPropagation();
			}
		)
		.entitytermsforlanguagelistview( {
			value: this.options.value,
			entityId: this.options.entityId,
			entityChangersFactory: this.options.entityChangersFactory
		} );
	},

	/**
	 * @return {boolean}
	 */
	isValid: function() {
		return this._getEntitytermsforlanguagelistview().isValid();
	},

	/**
	 * @return {boolean}
	 */
	isInitialValue: function() {
		return this._getEntitytermsforlanguagelistview().isInitialValue();
	},

	startEditing: function() {
		if( this._isInEditMode ) {
			return;
		}

		this._isInEditMode = true;
		this.element.addClass( 'wb-edit' );

		this._getEntitytermsforlanguagelistview().startEditing();

		this._trigger( 'afterstartediting' );
	},

	/**
	 * @param {boolean} [dropValue]
	 */
	stopEditing: function( dropValue ) {
		var self = this;

		if( !this._isInEditMode || ( !this.isValid() || this.isInitialValue() ) && !dropValue ) {
			return;
		}

		dropValue = !!dropValue;

		this._trigger( 'stopediting', null, [dropValue] );

		this.disable();

		this.$entitytermsforlanguagelistview
		.one(
			'entitytermsforlanguagelistviewafterstopediting.entitytermsviewstopediting',
			function( event, dropValue ) {
				self._afterStopEditing( dropValue );
				self.$entitytermsforlanguagelistview.off( '.entitytermsviewstopediting' );
			}
		)
		.one(
			'entitytermsforlanguagelistviewtoggleerror.entitytermsviewstopediting',
			function( event, dropValue ) {
				self.enable();
				self.$entitytermsforlanguagelistview.off( '.entitytermsviewstopediting' );
			}
		);

		this._getEntitytermsforlanguagelistview().stopEditing( dropValue );
	},

	/**
	 * @param {boolean} dropValue
	 */
	_afterStopEditing: function( dropValue ) {
		if( !dropValue ) {
			this.options.value = this.value();
		}
		this._isInEditMode = false;
		this.enable();
		this.element.removeClass( 'wb-edit' );
		this._trigger( 'afterstopediting', null, [dropValue] );
	},

	cancelEditing: function() {
		this.stopEditing( true );
	},

	/**
	 * @see jQuery.ui.TemplatedWidget.focus
	 */
	focus: function() {
		this._getEntitytermsforlanguagelistview().focus();
	},

	/**
	 * Applies/Removes error state.
	 *
	 * @param {Error} [error]
	 */
	setError: function( error ) {
		if( error ) {
			this.element.addClass( 'wb-error' );
			this._trigger( 'toggleerror', null, [error] );
		} else {
			this.removeError();
			this._trigger( 'toggleerror' );
		}
	},

	removeError: function() {
		this.element.removeClass( 'wb-error' );
		this._getEntitytermsforlanguagelistview().removeError();
	},

	/**
	 * @param {Object[]} [value]
	 * @return {Object[]|*}
	 */
	value: function( value ) {
		if( value !== undefined ) {
			return this.option( 'value', value );
		}

		return this._getEntitytermsforlanguagelistview().value();
	},

	/**
	 * @see jQuery.ui.TemplatedWidget._setOption
	 */
	_setOption: function( key, value ) {
		if( key === 'value' ) {
			throw new Error( 'Impossible to set value after initialization' );
		}

		var response = PARENT.prototype._setOption.apply( this, arguments );

		if( key === 'disabled' ) {
			this._getEntitytermsforlanguagelistview().option( key, value );
		}

		return response;
	}
} );

}( mediaWiki, jQuery ) );
