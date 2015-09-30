( function( $, mw ) {
	'use strict';

var PARENT = $.wikibase.entityview;

/**
 * View for displaying a Wikibase `Property`.
 * @see wikibase.datamodel.Property
 * @class jQuery.wikibase.propertyview
 * @extends jQuery.wikibase.entityview
 * @since 0.5
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 *
 * @param {Object} options
 * @param {Function} options.statementGroupListViewBuilder
 *
 * @constructor
 *
 */
$.widget( 'wikibase.propertyview', PARENT, {
	/**
	 * @inheritdoc
	 * @protected
	 */
	options: {
		statementGroupListViewBuilder: null
	},

	/**
	 * @property {jQuery}
	 * @readonly
	 */
	$dataType: null,

	/**
	 * @property {jQuery}
	 * @readonly
	 */
	$statements: null,

	/**
	 * @inheritdoc
	 * @protected
	 */
	_create: function() {
		this._createEntityview();

		this.$statements = $( '.wikibase-statementgrouplistview', this.element );
		if( this.$statements.length === 0 ) {
			this.$statements = $( '<div/>' ).appendTo( this.element );
		}

		this._createDataType();
	},

	/**
	 * @inheritdoc
	 * @protected
	 */
	_init: function() {
		if( !this.options.statementGroupListViewBuilder ) {
			throw new Error( 'Required option(s) missing' );
		}

		this._initStatements();
		PARENT.prototype._init.call( this );
	},

	/**
	 * @protected
	 */
	_createDataType: function() {
		// TODO: Implement propertyview template to have static HTML rendered by the back-end match
		// the HTML rendered here without having to invoke templating mechanism here.

		if( this.$dataType ) {
			return;
		}

		this.$dataType = $( '.wikibase-propertyview-datatype', this.element );

		if( !this.$dataType.length ) {
			this.$dataType = mw.wbTemplate( 'wikibase-propertyview-datatype',
				this.options.value.getDataTypeId()
			).appendTo( this.element );
		}
	},

	/**
	 * @protected
	 */
	_initStatements: function() {
		this.options.statementGroupListViewBuilder( this.options.value, this.$statements );

		// This is here to be sure there is never a duplicate id:
		$( '.wikibase-statementgrouplistview' )
		.prev( '.wb-section-heading' )
		.first()
		.attr( 'id', 'claims' );
	},

	/**
	 * @inheritdoc
	 * @protected
	 */
	_attachEventHandlers: function() {
		PARENT.prototype._attachEventHandlers.call( this );

		var self = this;

		this.element
		.on( [
			'statementviewafterstartediting.' + this.widgetName,
			'referenceviewafterstartediting.' + this.widgetName
		].join( ' ' ),
		function( event ) {
			self._trigger( 'afterstartediting' );
		} );

		this.element
		.on( [
			'statementlistviewafterremove.' + this.widgetName,
			'statementviewafterstopediting.' + this.widgetName,
			'statementviewafterremove.' + this.widgetName,
			'referenceviewafterstopediting.' + this.widgetName
		].join( ' ' ),
		function( event, dropValue ) {
			self._trigger( 'afterstopediting', null, [dropValue] );
		} );
	},

	/**
	 * @inheritdoc
	 * @protected
	 */
	_setState: function( state ) {
		PARENT.prototype._setState.call( this, state );

		this.$statements.data( 'statementgrouplistview' )[state]();
		// TODO: Resolve integration of referenceviews
		this.$statements.find( '.wb-statement-references' ).each( function() {
			var $listview = $( this ).children( ':wikibase-listview' );
			if( $listview.length ) {
				$listview.data( 'listview' )[state]();
			}
		} );
	}
} );

$.wikibase.entityview.TYPES.push( $.wikibase.propertyview.prototype.widgetName );

}( jQuery, mediaWiki ) );
