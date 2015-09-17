/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( wb, $, mw ) {
	'use strict';

/**
 * Scrapes site links from static HTML in order to be sure the order in the static HTML matches the
 * order set on the widget initialized on the HTML structure since that widget is not supposed to
 * re-render the HTML for performance reasons.
 * @ignore
 *
 * @param {jQuery} $siteLinks
 * @param {wikibase.datamodel.SiteLinkSet} siteLinkSet
 * @return {Object}
 */
function scrapeSiteLinks( $siteLinks, siteLinkSet ) {
	var value = [];

	$siteLinks.find( '.wikibase-sitelinkgroupview' ).each( function() {
		var $sitelinkgroupview = $( this ),
			$sitelinklistview = $sitelinkgroupview.find( '.wikibase-sitelinklistview' ),
			group = $sitelinkgroupview.data( 'wb-sitelinks-group' ),
			siteIdsOfGroup = [],
			siteLinkIds = siteLinkSet.getKeys(),
			siteLinksOfGroup = [];

		$sitelinklistview.find( '.wikibase-sitelinkview' ).each( function() {
			siteIdsOfGroup.push( $( this ).data( 'wb-siteid' ) );
		} );

		for( var i = 0; i < siteIdsOfGroup.length; i++ ) {
			for( var j = 0; j < siteLinkIds.length; j++ ) {
				if( siteLinkIds[j] === siteIdsOfGroup[i] ) {
					siteLinksOfGroup.push( siteLinkSet.getItemByKey( siteLinkIds[j] ) );
					break;
				}
			}
		}

		value.push( {
			group: group,
			siteLinks: siteLinksOfGroup
		} );
	} );

	return value;
}

/**
 * Maps site links of a `wikibase.datamodel.SiteLinkSet` to their Wikibase site groups.
 * @ignore
 *
 * @param {wikibase.datamodel.SiteLinkSet} siteLinkSet
 * @return {Object}
 */
function orderSiteLinksByGroup( siteLinkSet ) {
	var value = [];

	siteLinkSet.each( function( siteId, siteLink ) {
		var site = wb.sites.getSite( siteId ),
			found = false;

		if( !site ) {
			throw new Error( 'Site with id ' + siteId + ' is not registered' );
		}

		for( var i = 0; i < value.length; i++ ) {
			if( value[i].group === site.getGroup() ) {
				value[i].siteLinks.push( siteLink );
				found = true;
				break;
			}
		}

		if( !found ) {
			value.push( {
				group: site.getGroup(),
				siteLinks: [siteLink]
			} );
		}
	} );

	return value;
}

var PARENT = $.ui.TemplatedWidget;

/**
 * Encapsulates multiple sitelinkgroupview widgets.
 * @since 0.5
 * @extends jQuery.ui.TemplatedWidget
 *
 * @option {wikibase.datamodel.Entity} value
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

		var value = this.element.is( ':empty' )
				? scrapeSiteLinks( this.element, this.options.value.getSiteLinks() )
				: orderSiteLinksByGroup( this.options.value.getSiteLinks() );

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
			value: value,
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

}( wikibase, jQuery, mediaWiki ) );
