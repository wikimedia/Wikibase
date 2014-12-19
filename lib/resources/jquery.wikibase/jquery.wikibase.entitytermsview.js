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
			function() {
				return mw.msg( 'wikibase-terms' );
			},
			'', // entitytermsforlanguagelistview
			'' // edit section
		],
		templateShortCuts: {
			$h: 'h2'
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
			this.$entitytermsforlanguagelistview = $( '<table/>' ).appendTo( this.element );
		}

		this._createEntitytermsforlanguagelistview();
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

		this.element.removeClass( 'wikibase-entitytermsview' );
		PARENT.prototype.destroy.call( this );
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

$.wikibase.toolbarcontroller.definition( 'edittoolbar', {
	id: 'entitytermsview',
	selector: ':' + $.wikibase.entitytermsview.prototype.namespace
		+ '-' + $.wikibase.entitytermsview.prototype.widgetName,
	events: {
		entitytermsviewcreate: function( event, toolbarcontroller ) {
			var $entitytermsview = $( event.target ),
				entitytermsview = $entitytermsview.data( 'entitytermsview' ),
				$headingContainer = $entitytermsview.find(
					'.wikibase-entitytermsview-heading-container'
				),
				$container = $headingContainer.children( '.wikibase-toolbar-container' );

			if( !$container.length ) {
				$container = $( '<div/>' ).appendTo( $headingContainer );
			}

			$entitytermsview.edittoolbar( {
				$container: $container,
				interactionWidget: entitytermsview
			} );

			$entitytermsview.on( 'keyup.edittoolbar', function( event ) {
				if( entitytermsview.option( 'disabled' ) ) {
					return;
				}
				if( event.keyCode === $.ui.keyCode.ESCAPE ) {
					entitytermsview.stopEditing( true );
				} else if( event.keyCode === $.ui.keyCode.ENTER ) {
					entitytermsview.stopEditing( false );
				}
			} );
		},
		'entitytermsviewchange entitytermsviewafterstartediting': function( event ) {
			var $entitytermsview = $( event.target ),
				entitytermsview = $entitytermsview.data( 'entitytermsview' ),
				edittoolbar = $entitytermsview.data( 'edittoolbar' ),
				btnSave = edittoolbar.getButton( 'save' ),
				enable = entitytermsview.isValid() && !entitytermsview.isInitialValue();

			btnSave[enable ? 'enable' : 'disable']();
		},
		entitytermsviewdisable: function( event ) {
			var $entitytermsview = $( event.target ),
				entitytermsview = $entitytermsview.data( 'entitytermsview' ),
				edittoolbar = $entitytermsview.data( 'edittoolbar' ),
				btnSave = edittoolbar.getButton( 'save' ),
				enable = entitytermsview.isValid() && !entitytermsview.isInitialValue();

			btnSave[enable ? 'enable' : 'disable']();
		},
		toolbareditgroupedit: function( event, toolbarcontroller ) {
			var $entitytermsview = $( event.target ),
				entitytermsview = $entitytermsview.data( 'entitytermsview' );

			if( !entitytermsview ) {
				return;
			}

			entitytermsview.focus();
		}
	}
} );


}( mediaWiki, jQuery ) );
