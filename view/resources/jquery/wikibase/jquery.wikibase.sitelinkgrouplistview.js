/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( $, mw ) {
	'use strict';

	var PARENT = $.ui.TemplatedWidget;

/**
 * Encapsulates multiple sitelinkgroupview widgets.
 * @since 0.5
 * @extends jQuery.ui.TemplatedWidget
 *
 * @option {Object[]} value
 *         Array of objects representing the widget's value.
 *         Structure: [{ group: <{string}>, siteLinks: <{wikibase.datamodel.SiteLink[]}> }[, ...]]
 *
 * @option {wikibase.entityChangers.SiteLinksChanger} siteLinksChanger
 *
 * @option {wikibase.store.EntityStore} entityStore
 */
$.widget( 'wikibase.sitelinkgrouplistview', PARENT, {
	options: {
		template: 'wikibase-sitelinkgrouplistview',
		templateParams: [
			'' // sitelinklistview(s)
		],
		templateShortCuts: {},
		value: null,
		siteLinksChanger: null,
		entityStore: null
	},

	/**
	 * @type {jQuery}
	 */
	$listview: null,

	/**
	 * @type {jQuery.util.EventSingletonManager}
	 */
	_eventSingletonManager: null,

	/**
	 * @see jQuery.ui.TemplatedWidget._create
	 */
	_create: function() {
		if( !this.options.value || !this.options.siteLinksChanger || !this.options.entityStore ) {
			throw new Error( 'Required option(s) missing' );
		}

		PARENT.prototype._create.call( this );

		this._eventSingletonManager = new $.util.EventSingletonManager();

		this._createListview();

		this.element.addClass( 'wikibase-sitelinkgrouplistview' );
	},

	/**
	 * @see jQuery.ui.TemplatedWidget.destroy
	 */
	destroy: function() {
		if( this.$listview ) {
			var listview = this.$listview.data( 'listview' );
			if( listview ) {
				listview.destroy();
			}
			this.$listview.remove();
			delete this.$listview;
		}
		this.element.removeClass( 'wikibase-sitelinkgrouplistview' );
		PARENT.prototype.destroy.call( this );
	},

	_createListview: function() {
		var self = this,
			prefix = $.wikibase.sitelinkgroupview.prototype.widgetEventPrefix;

		this.$listview = this.element.find( '.wikibase-listview' );

		if( !this.$listview.length ) {
			this.$listview = $( '<div/>' ).appendTo( this.element );
		}

		this.$listview
		.listview( {
			listItemAdapter: new $.wikibase.listview.ListItemAdapter( {
				listItemWidget: $.wikibase.sitelinkgroupview,
				newItemOptionsFn: function( value ) {
					return {
						value: value,
						siteLinksChanger: self.options.siteLinksChanger,
						entityStore: self.options.entityStore,
						eventSingletonManager: this._eventSingletonManager,
						helpMessage: mw.msg( 'wikibase-sitelinkgroupview-input-help-message' )
					};
				}
			} ),
			value: self.options.value || null,
			encapsulate: true
		} )
		.on( prefix + 'disable.' + this.widgetName, function( event ) {
			event.stopPropagation();
		} );
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
			$items = listview.items();

		if( $items.length ) {
			listview.listItemAdapter().liInstance( $items.first() ).focus();
		} else {
			this.element.focus();
		}
	}
} );

}( jQuery, mediaWiki ) );
