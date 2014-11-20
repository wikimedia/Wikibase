/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( $ ) {
	'use strict';

var $window = $( window ),
	eventSingleton = new $.util.EventSingletonManager(),
	PLUGIN_NAME = 'sticknode';

/**
 * jQuery sticknode plugin.
 * Sticks a node with "position: fixed" when vertically scrolling it out of the viewport.
 *
 * @param {Object} [options]
 *        - {jQuery} $container
 *          Node specifying the bottom boundary for the node the plugin is initialized on. If the
 *          node the plugin is initialized on clips out of the container, it is reset to static
 *          position.
 * @return {jQuery}
 *
 * @event sticknodeupdate
 *        Triggered when the node the widget is initialized on updates its positioning behaviour.
 *        - {jQuery.Event}
 */
$.fn.sticknode = function( options ) {
	options = options || {};

	this.each( function() {
		var $node = $( this );

		if( $node.data( PLUGIN_NAME ) ) {
			return;
		}

		var stickyNode = new StickyNode( $( this ), options );

		eventSingleton.register(
			stickyNode,
			window,
			'scroll.' + PLUGIN_NAME + ' ' + 'touchmove.' + PLUGIN_NAME,
			function( event, stickyNode ) {
				if( stickyNode.update( $window.scrollTop() ) ) {
					stickyNode.$node.triggerHandler( PLUGIN_NAME + 'update' );
				}
			},
			{
				throttle: 150
			}
		);

		eventSingleton.register(
			stickyNode,
			window,
			'resize.' + PLUGIN_NAME,
			function( event, stickyNode ) {
				if( stickyNode.update( $window.scrollTop(), true ) ) {
					stickyNode.$node.triggerHandler( PLUGIN_NAME + 'update' );
				}
			},
			{
				throttle: 150
			}
		);
	} );

	return this;
};

/**
 * @constructor
 *
 * @param {jQuery} $node
 * @param {Object} options
 */
var StickyNode = function( $node, options ) {
	var self = this;

	this.$node = $node;
	this.$node.data( PLUGIN_NAME, this );
	this.$node.on( 'resize', function() {
		self.refresh();
	} );

	this._options = $.extend( {
		$container: null
	}, options );

	this._initialAttributes = {};
};

$.extend( StickyNode.prototype, {
	/**
	 * @type {jQuery}
	 */
	$node: null,

	/**
	 * @type {jQuery|null}
	 */
	_$clone: null,

	/**
	 * @type {Object}
	 */
	_options: null,

	/**
	 * @type {Object}
	 */
	_initialAttributes: null,

	/**
	 * @type {boolean}
	 */
	_changesDocumentHeight: false,

	/**
	 * Destroys and unregisters the plugin.
	 */
	destroy: function() {
		eventSingleton.unregister(
			this.$node.data( PLUGIN_NAME ),
			window,
			'.' + PLUGIN_NAME
		);
		this.$node.removeData( PLUGIN_NAME );
		this._$clone.remove();
		this._$clone = null;
	},

	/**
	 * @return {boolean}
	 */
	_clipsContainer: function() {
		if( !this._options.$container || !this.isFixed() ) {
			return false;
		}

		var nodeBottom = this.$node.offset().top + this.$node.outerHeight();

		var containerBottom = this._options.$container.offset().top
			+ this._options.$container.outerHeight();

		return nodeBottom > containerBottom;
	},

	/**
	 * @return {boolean}
	 */
	_isScrolledAfterContainer: function() {
		if( !this._options.$container ) {
			return false;
		}

		var containerBottom = this._options.$container.offset().top
			+ this._options.$container.outerHeight();

		return $window.scrollTop() + this.$node.outerHeight() > containerBottom;
	},

	/**
	 * @param {number} scrollTop
	 * @return {boolean}
	 */
	_isScrolledBeforeContainer: function( scrollTop ) {
		if( !this._initialAttributes.offset ) {
			return false;
		}

		var initTopOffset = this._initialAttributes.offset.top;

		return !this._changesDocumentHeight && scrollTop < initTopOffset
			|| this._changesDocumentHeight && scrollTop < initTopOffset - this.$node.outerHeight();
	},

	_fix: function() {
		if( this.isFixed() ) {
			return;
		}

		this._initialAttributes = {
			offset: this.$node.offset(),
			position: this.$node.css( 'position' ),
			top: this.$node.css( 'top' ),
			left: this.$node.css( 'left' ),
			width: this.$node.css( 'width' )
		};

		// Cannot fix the clone instead of the original node since the clone does not feature event
		// bindings.
		this._$clone = this.$node.clone()
			.css( 'visibility', 'hidden' )
			.insertBefore( this.$node );

		this.$node
		.css( 'left', this._initialAttributes.offset.left + 'px' )
		.css( 'top', this.$node.outerHeight() - this.$node.outerHeight( true ) )
		.css( 'width', this.$node.width() )
		.css( 'position', 'fixed' );
	},

	_unfix: function() {
		this._$clone.remove();
		this._$clone = null;

		this.$node
		.css( 'left', this._initialAttributes.left )
		.css( 'top', this._initialAttributes.top )
		.css( 'width', this._initialAttributes.width )
		.css( 'position', this._initialAttributes.position );

		this._initialAttributes.offset = null;
	},

	/**
	 * Returns whether the node the plugin is initialized on is in "fixed" position.
	 *
	 * @return {boolean}
	 */
	isFixed: function() {
		return this.$node.css( 'position' ) === 'fixed';
	},

	/**
	 * Updates the node's positioning behaviour according to a specific scroll offset.
	 *
	 * @param {number} scrollTop
	 * @param {boolean} force
	 * @return {boolean}
	 */
	update: function( scrollTop, force ) {
		var changedState = false;

		if( force && this.isFixed() ) {
			this._unfix();
		}

		if(
			!this.isFixed()
			&& scrollTop > this.$node.offset().top
			&& !this._isScrolledAfterContainer()
		) {
			this._fix();
			changedState = true;
		}

		if(
			this.isFixed() && this._isScrolledBeforeContainer( scrollTop )
			|| this._clipsContainer()
		) {
			this._unfix();
			changedState = !changedState;
		}

		return changedState;
	},

	/**
	 * Re-fixes the node if it is fixed, properly updating scroll position. Should be called
	 * whenever the node's content has been updated.
	 */
	refresh: function() {
		if( this.isFixed() ) {
			this._unfix();
			this._fix();
		}
	}
} );

}( jQuery ) );