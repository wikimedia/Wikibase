/**
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 */
( function( mw, wb, $ ) {
	'use strict';

	/**
	 * Class for toggle elements icons
	 * @type {String} 'ui-icon-triangle-1-e' or 'ui-icon-triangle-1-w'
	 */
	var IS_RTL = null,
		CLS_TOGGLE_HIDDEN = 'ui-icon-triangle-1-w',
		CLS_TOGGLE_VISIBLE = 'ui-icon-triangle-1-s';

	$( document ).ready( function() {
		// have to wait for document to be loaded for this, otherwise 'rtl' might not yet be there!
		IS_RTL = $( 'body' ).hasClass( 'rtl' );
		CLS_TOGGLE_HIDDEN = 'ui-icon-triangle-1-' + ( IS_RTL ? 'w' : 'e' );
	} );

	/**
	 * Whether the user client supports CSS3 transformation.
	 * @type boolean
	 */
	var browserSupportsTransform;

	$( document ).ready( function() {
		// have to wait for document to be loaded for this, otherwise 'rtl' might not yet be there!
		CLS_TOGGLE_HIDDEN = 'ui-icon-triangle-1-' + ( $( 'body' ).hasClass( 'rtl' ) ? 'w' : 'e' );

		// check for support of transformation (see https://gist.github.com/1031421)
		var img = (new Image).style;
		browserSupportsTransform = 'transition' in img // general
			|| 'msTransform' in img
			|| 'webkitTransition' in img // Webkit
			|| 'MozTransition' in img // Gecko
			|| 'OTransition' in img; // Opera
	} );

	/**
	 * UI related utilities required by 'Wikibase' extension.
	 * @type {Object}
	 */
	wb.utilities.ui = {};

	/**
	 * Serves the DOM for a simple toggle. Will wrap a given node or text within a new div which
	 * will then be returned. When clicking the div, a node given in the second argument will be
	 * hidden, clicking the toggle again will make it visible again.
	 * The toggle considers the toggle subject's current 'display' style, so if it is set to 'none',
	 * it is considered invisible initially.
	 *
	 * @since 0.4
	 *
	 * @param {jQuery|string} toggleLabel The node or a message which will act as toggle
	 * @param {jQuery} $toggleSubject The element which should be toggled
	 * @return jQuery
	 */
	wb.utilities.ui.buildToggle = function( toggleLabel, $toggleSubject ) {
		var $toggle = $( '<a/>', { href: 'javascript:void(0);', 'class': 'wb-toggle' } );
		var $toggleIcon = $( '<span/>', { 'class': 'ui-icon' } );
		var $toggleLabel = (  toggleLabel instanceof $ )
			? toggleLabel
			: $( '<span/>', { text: toggleLabel } );

		$toggleLabel.addClass( 'wb-toggle-label' );

		var fnReflectVisibilityOnToggleIcon = function( inverted ) {
			// don't use is( ':visible' ) which would be misleading if  element not yet in DOM!
			var makeVisible = $toggleSubject.css( 'display' ) !== 'none';
			if( inverted ) {
				makeVisible = !makeVisible;
			}
			// add classes displaying rotated icon. If CSS3 transform is available, use it!
			$toggleIcon.removeClass( CLS_TOGGLE_HIDDEN + ' wb-toggle-icon3dtrans ' + CLS_TOGGLE_VISIBLE );
			if( !browserSupportsTransform ) {
				$toggleIcon.addClass( makeVisible ? CLS_TOGGLE_VISIBLE : CLS_TOGGLE_HIDDEN );
			} else {
				$toggleIcon.addClass( 'wb-toggle-icon3dtrans ' + CLS_TOGGLE_VISIBLE );
			}
			$toggle[ makeVisible ? 'removeClass' : 'addClass' ]( 'wb-toggle-collapsed' );
		};
		// consider content being invisible initially:
		fnReflectVisibilityOnToggleIcon();

		$toggle
		.on( 'click', function( event ) {
			fnReflectVisibilityOnToggleIcon( true );
			$toggleSubject.slideToggle();
			// change toggle icon to reflect current state of toggle subject visibility:

		} )
		.append( $toggleIcon )
		.append( $toggleLabel );

		return $toggle;
	};

	/**
	 * Creates a pretty link to a entity's page. Expects information about the Entity as a plain
	 * Object with 'id', 'url' and 'label' fields. If the label is not set or empty, then the link
	 * will show the entity's ID and some explanatory text describing that the label hast not been
	 * set yet.
	 *
	 * @since 0.4
	 *
	 * @param {Object} entityData Requires 'url', 'id' and optionally 'label' fields
	 * @return jQuery 'a' element
	 */
	wb.utilities.ui.buildEntityLink = function( entityData ) {
		var $propertyLink = $( '<a/>', {
			href: entityData.url,
			text: entityData.label || entityData.id
		} );

		if( !entityData.label ) {
			$propertyLink.append( $( '<span/>', {
				'class': 'wb-entity-undefinedinfo',
				'text': ' (' + mw.msg( 'wikibase-label-empty' ) + ')'
			} ) );
		}

		return $propertyLink;
	};

	/**
	 * Creates a counter suited for displaying a number of a fixed quantity plus a number of a
	 * pending quantity whereas the quantity can be 0 or higher. If the pending quantity is 0, it
	 * will not be shown and only the fixed quantity will be displayed, otherwise it will be
	 * displayed as "fixedQuantity +pendingQuantity kindOfQuantity", e.g. "32 +2"
	 *
	 * @since 0.4
	 *
	 * @param {number} fixedQuantity
	 * @param {number} pendingQuantity
	 * @param {string} kindOfQuantityMessage Message string of a message expressing the nature of
	 *        the quantity, e.g. a message which would return 'items' for displaying something like
	 *        "3 +1 items". The message will receive as parameter $1 the total quantity (fixed +
	 *        pending).
	 * @param {string} pendingQuantityTooltipMessage Message string of a message which will be
	 *        displayed in the tooltip which will be appended to the number of pending quantity.
	 *        Parameter $1 will be the number of pending quantity, $2 will be the fixed quantity
	 *        and $3 the sum of both.
	 * @return {jQuery} The formatted counter output. Does not have a root node, collection of
	 *         multiple DOM elements.
	 */
	wb.utilities.ui.buildPendingCounter = function(
		fixedQuantity, pendingQuantity, kindOfQuantityMessage, pendingQuantityTooltipMessage
	) {
		var fqNumMsg = mw.language.convertNumber( fixedQuantity ),
			pqNumMsg = mw.language.convertNumber( pendingQuantity ),
			tqNumMsg = mw.language.convertNumber( fixedQuantity + pendingQuantity ),
			qTypeLabel = kindOfQuantityMessage ? mw.msg( kindOfQuantityMessage, tqNumMsg ) : '';

		var msg = !pendingQuantity || pendingQuantity === '0'
			? mw.msg( 'wikibase-ui-pendingquantitycounter-nonpending', qTypeLabel, fqNumMsg )
			: mw.msg( 'wikibase-ui-pendingquantitycounter-pending',
				qTypeLabel,
				fqNumMsg,
				'__3__' // can't insert html here since it would be escaped!
			);

		// replace __3__ with a span we can grab next
		var $msg = $( ( '<span>' + msg + '</span>' ).replace( /__3__/g, '<span/>' ) ),
			$msgSpan = $msg.children( 'span' );

		if( $msgSpan.length > 0 ) {
			$msgSpan.attr(
				'title', // the message displayed in the tooltip
				mw.msg( pendingQuantityTooltipMessage, pqNumMsg, fqNumMsg, tqNumMsg )
			);
			$msgSpan.text( // the '+1' part, displaying the pending quantity
				mw.msg( 'wikibase-ui-pendingquantitycounter-pending-pendingsubpart', pqNumMsg )
			);
			$msgSpan.tipsy( {
				'gravity': ( IS_RTL ? 'ne' : 'nw' )
			} );
		}

		$msg.addClass( 'wb-ui-pendingcounter' );
		return $msg;
	};

}( mediaWiki, wikibase, jQuery ) );
