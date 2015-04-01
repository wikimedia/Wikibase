( function( $ ) {
	'use strict';

	var PARENT =  $.ui.TemplatedWidget;

/**
 * View for displaying and editing list items, each represented by a single random widget.
 * @class jQuery.wikibase.listview
 * @extends jQuery.ui.TemplatedWidget
 * @since 0.4
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 * @author H. Snater < mediawiki@snater.com >
 *
 * @constructor
 *
 * @param {Object} options
 * @param {*[]} [options.value=null]
 *        The values displayed by this view. More specifically, a list of each list item widget's
 *        value.
 * @param {jQuery.wikibase.listview.ListItemAdapter} options.listItemAdapter
 *        Interfaces the actual widget instances to be used by the `listview`. Cannot be changed
 *        after initialization.
 * @param {string} [options.listItemNodeName='DIV']
 *         Node name of the base node of new list items.
 */
/**
 * @event itemadded
 * Triggered after a list item got added to the list.
 * @param {jQuery.Event} event
 * @param {*|null} value The value the new list item is representing. `null` for empty value.
 * @param {jQuery} $li The DOM node of the widget representing the value.
 */
/**
 * @event itemremoved
 * Triggered after a list got removed from the list.
 * @param {jQuery.Event} event
 * @param {*|null} value The value of the list item which will be removed. `null` for empty value.
 * @param {jQuery} $li The list item's DOM node that was removed.
 */
/**
 * @event enternewitem
 * Triggered when initializing the process of adding a new item to the list.
 * @param {jQuery.Event} event
 * @param {jQuery} $li The DOM node pending to be added permanently to the list.
 */
/**
 * @event afteritemmove
 * Triggered when an item node is moved within the list.
 * @param {jQuery.Event} event
 * @param {number} The item node's new index.
 * @param {number} Number of items in the list.
 */
/**
 * @event destroy
 * Triggered when the widget has been destroyed.
 * @param {jQuery.Event} event
 */
$.widget( 'wikibase.listview', PARENT, {
	/**
	 * @inheritdoc
	 * @protected
	 */
	options: {
		template: 'wikibase-listview',
		templateParams: [
			'' // list items
		],
		value: null,
		listItemAdapter: null,
		listItemNodeName: 'DIV'
	},

	/**
	 * Short-cut for `this.options.listItemAdapter`.
	 * @property {jQuery.wikibase.listview.ListItemAdapter}
	 * @private
	 */
	_lia: null,

	/**
	 * The DOM elements this `listview`'s element contained when it was initialized. These DOM
	 * elements are reused in `this.addItem` until the array is empty.
	 * @property [HTMLElement[]]
	 * @private
	 */
	_reusedItems: [],

	/**
	 * @inheritdoc
	 * @protected
	 *
	 * @throws {Error} if a required option is not specified properly.
	 */
	_create: function() {
		this._lia = this.options.listItemAdapter;

		if( typeof this._lia !== 'object'
			|| !( this._lia instanceof $.wikibase.listview.ListItemAdapter )
		) {
			throw new Error( 'Option "listItemAdapter" has to be an instance of '
				+ 'jQuery.wikibase.listview.ListItemAdapter' );
		}

		this._reusedItems = $.makeArray( this.element.children( this.options.listItemNodeName ) );

		PARENT.prototype._create.call( this );

		this._createList();
	},

	/**
	 * @inheritdoc
	 */
	destroy: function() {
		this._lia = null;
		this._reusedItems = null;
		PARENT.prototype.destroy.call( this );
		this._trigger( 'destroy' );
	},

	/**
	 * @inheritdoc
	 * @protected
	 *
	 * @throws {Error} when trying to set `listItemAdapter` option.
	 */
	_setOption: function( key, value ) {
		var self = this;

		if( key === 'listItemAdapter' ) {
			throw new Error( 'Can not change the ListItemAdapter after initialization' );
		} else if( key === 'value' ) {
			this.items().each( function( i, node ) {
				var $node = $( node );
				self._lia.liInstance( $node ).destroy();
				$node.remove();
			} );

			for( var i = 0; i < value.length; i++ ) {
				this._addLiValue( value[i] );
			}
		}

		var response = PARENT.prototype._setOption.apply( this, arguments );

		if( key === 'disabled' ) {
			this.items().each( function() {
				var liInstance = self._lia.liInstance( $( this ) );
				// Check if instance got destroyed in the meantime:
				if( liInstance ) {
					liInstance.option( key, value );
				}
			} );
		}

		return response;
	},

	/**
	 * Fills the list element with DOM structure for each list item.
	 * @private
	 */
	_createList: function() {
		var i, items = this.option( 'value' );

		// initialize view for each of the list item values:
		for( i in items ) {
			this.addItem( items[i] );
		}
	},

	/**
	 * Sets the widget's value or gets the widget's current value. The widget's non-pending value
	 * (the value the widget was initialized with) may be retrieved via `this.option( 'value' )`.
	 *
	 * @param {*[]} [value] List containing a value for each list item widget.
	 * @return {*[]|undefined}
	 */
	value: function( value ) {
		if( value === undefined ) {
			var self = this,
				values = [];

			this.items().each( function() {
				values.push( self._lia.liInstance( $( this ) ) );
			} );

			return values;
		}

		this.option( 'value', value );
	},

	/**
	 * Returns all list item nodes. The `listItemAdapter` may be used to retrieve the list item
	 * instance.
	 *
	 * @return {jQuery}
	 */
	items: function() {
		return this.element.children( '.' + this.widgetName + '-item' );
	},

	/**
	 * Returns all list items which have a value not considered empty (not `null`).
	 *
	 * @return {jQuery}
	 */
	nonEmptyItems: function() {
		var lia = this._lia;
		return this.items().filter( function( i ) {
			return !!lia.liInstance( $( this ) ).value();
		} );
	},

	/**
	 * Returns the index of a given item node within the list managed by the `listview`. Returns
	 * `-1` if the node could not be found.
	 *
	 * @param {jQuery} $itemNode
	 * @return {number}
	 */
	indexOf: function( $itemNode ) {
		var $items = this.items(),
			itemNode = $itemNode.get( 0 );

		for( var i = 0; i < $items.length; i++ ) {
			if( $items.get( i ) === itemNode ) {
				return i;
			}
		}

		return -1;
	},

	/**
	 * Moves a list item to a new index.
	 *
	 * @param {jQuery} $itemNode
	 * @param {number} toIndex
	 */
	move: function( $itemNode, toIndex ) {
		var currIndex = this.indexOf( $itemNode ),
			items = this.items();

		// No need to move if the item has the index already or if it should be moved to after the
		// last item although it is at the end already:
		if(
			currIndex < 0
			|| currIndex === toIndex
			|| currIndex === items.length - 1 && toIndex >= items.length
		) {
			return;
		}

		if( toIndex >= items.length ) {
			$itemNode.insertAfter( items.last() );
		} else if( items.eq( toIndex ).prev().get( 0 ) === $itemNode.get( 0 ) ) {
			// Item already is at the position it shall be moved to.
			return;
		} else {
			$itemNode.insertBefore( items.eq( toIndex ) );
		}

		this._trigger( 'afteritemmove', null, [ this.indexOf( $itemNode ), items.length ] );
	},

	/**
	 * Moves an item node one index towards the top of the list.
	 *
	 * @param {jQuery} $itemNode
	 */
	moveUp: function( $itemNode ) {
		if( this.indexOf( $itemNode ) !== 0 ) {
			this.move( $itemNode, this.indexOf( $itemNode ) - 1 );
		}
	},

	/**
	 * Moves an item node one index towards the bottom of the list.
	 *
	 * @param {jQuery} $itemNode
	 */
	moveDown: function( $itemNode ) {
		// Adding 2 to the index to move the element to before the element after the next element:
		this.move( $itemNode, this.indexOf( $itemNode ) + 2 );
	},

	/**
	 * Returns the list item adapter object interfacing to this list's list items.
	 *
	 * @return {jQuery.wikibase.listview.ListItemAdapter}
	 */
	listItemAdapter: function() {
		return this._lia;
	},

	/**
	 * Adds one list item into the list and renders it in the view.
	 *
	 * @param {*} liValue One list item widget's value.
	 * @return {jQuery} New list item's node.
	 */
	addItem: function( liValue ) {
		var $li = this._addLiValue( liValue );
		this._trigger( 'itemadded', null, [liValue, $li] );
		return $li;
	},

	/**
	 * Adds one list item into the list and renders it in the view.
	 * @private
	 *
	 * @param {*} liValue One list item widget's value.
	 * @return {jQuery} New list item's node.
	 */
	_addLiValue: function( liValue ) {
		var $newLi = this._reusedItems.length > 0
			? $( this._reusedItems.shift() )
			: $( '<' + this.option( 'listItemNodeName' ) + '/>' );

		$newLi.addClass( this.widgetName + '-item' );

		if( !$newLi.parent( this.element ).length ) {
			// Insert DOM first, to allow events bubbling up the DOM tree.
			var items = this.items();

			if( items.length ) {
				items.last().after( $newLi );
			} else {
				this.element.append( $newLi );
			}
		}

		this._lia.newListItem( $newLi, liValue );

		return $newLi;
	},

	/**
	 * Removes one list item from the list and renders the update in the view.
	 *
	 * @param {jQuery} $li The list item's node to be removed.
	 *
	 * @throws {Error} if the node provided is not a list item.
	 */
	removeItem: function( $li ) {
		if( !$li.parent( this.element ).length ) {
			throw new Error( 'The given node is not an element in this list' );
		}

		var liValue = this._lia.liInstance( $li ).value();

		this._lia.liInstance( $li ).destroy();
		$li.remove();
		this._trigger( 'itemremoved', null, [liValue, $li] );
	},

	/**
	 * Inserts a new list item into the list. The new list item will be a widget instance of the
	 * type set on the list, but without any value.
	 *
	 * @return {Object} jQuery.Promise
	 * @return {Function} return.done
	 * @return {jQuery} return.done.$newLi The new list item node. Use
	 *         `listItemAdapter().liInstance( $newLi )` to receive the widget instance.
	 */
	enterNewItem: function() {
		var $newLi = this.addItem();
		this._trigger( 'enternewitem', null, [$newLi] );
		return $.Deferred().resolve( $newLi ).promise();
	},

	/**
	 * @inheritdoc
	 */
	focus: function() {
		var $items = this.items();

		if( $items.length ) {
			var item = this._lia.liInstance( $items.first() );
			if( item.focus ) {
				item.focus();
				return;
			}
		}

		this.element.focus();
	}

} );

}( jQuery ) );
