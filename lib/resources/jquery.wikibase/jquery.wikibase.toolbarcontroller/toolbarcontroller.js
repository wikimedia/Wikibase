/**
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	/**
	 * The toolbar types we have.
	 * TODO: create a registry for allowing adding additional toolbar types
	 *
	 * @type {string[]}
	 */
	var TOOLBAR_TYPES = ['addtoolbar', 'edittoolbar'];

	/**
	 * Toolbar controller widget
	 *
	 * The toolbar controller initializes and manages toolbar widgets. Toolbar definitions are
	 * registered via the jQuery.toolbarcontroller.definition() method. When initializing the
	 * toolbar controller, the ids or widget names of the registered toolbar definitions that the
	 * controller shall initialize are passed as options.
	 *
	 * @since 0.4
	 *
	 * @option addtoolbar {string[]} List of toolbar definition ids/widget names that are registered
	 *         as "addtoolbars" and shall be initialized.
	 *         Default: []
	 *
	 * @option edittoolbar {string[]} List of toolbar definition ids/widget names that are
	 *         registered as "edittoolbars" and shall be initialized.
	 *         Default: []
	 */
	$.widget( 'wikibase.toolbarcontroller', {
		/**
		 * Options
		 * @type {Object}
		 */
		options: {
			addtoolbar: [],
			edittoolbar: []
		},

		/**
		 * @see jQuery.Widget._create
		 */
		_create: function() {
			this.initToolbars();
		},

		/**
		 * Initializes the toolbars for the nodes that are descendants of the node the toolbar
		 * controller is initialized on.
		 *
		 * @since 0.4
		 * @param {boolean} [isPending] Whether element that triggered the toolbar
		 *        (re-)initialization is in a pending state.
		 */
		initToolbars: function( isPending ) {
			var self = this;

			$.each( TOOLBAR_TYPES, function( i, type ) {
				$.each( self.options[type], function( j, id ) {
					var def = $.wikibase.toolbarcontroller.definition( type, id ),
						options = ( type === 'edittoolbar' && isPending ) ?
							$.extend( {}, def.options, { enableRemove: !isPending } )
							: def.options;

					self.element
					.find( def.selector || ':' + def.widget.fullName )[type]( options );
				} );
			} );

			this.initEventListeners();
		},

		/**
		 * Initializes event listeners for all toolbars defined in the options. This will make sure
		 * that when a new widget toolbars are defined for is initialized, its toolbar(s) will
		 * be initialized as well.
		 * @since 0.4
		 */
		initEventListeners: function() {
			var self = this;

			this.element.off( '.' + this.widgetName );

			$.each( TOOLBAR_TYPES, function( i, type ) {
				$.each( self.options[type], function( j, definitionId ) {
					var def = $.wikibase.toolbarcontroller.definition( type, definitionId ),
						eventPrefix = def.eventPrefix || def.widget.prototype.widgetEventPrefix,
						baseClass = def.baseClass || def.widget.prototype.widgetBaseClass;

					// Listen to widget's native "create" event in order to initialize toolbars
					// corresponding to the widget just instantiated.
					self.element.on( eventPrefix + 'create.' + self.widgetName, function( event ) {
						var $target = $( event.target ),
							isPending = $target.hasClass( baseClass + '-new' )
								|| $target.find( baseClass + '-new' ).length > 0;

						if ( type === 'addtoolbar' ) {
							// Initialize toolbars that are not initialized already:
							self.initToolbars( isPending );
						} else if ( type === 'edittoolbar' ) {
							$( event.target ).edittoolbar(
								$.extend( {}, def.options, { enableRemove: !isPending } )
							);
						}
					} );

				} );
			} );

		}

	} );

}( mediaWiki, wikibase, jQuery ) );
