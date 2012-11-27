/**
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 */
( function( mw, wb, dv, dt, $ ) {
	'use strict';

/**
 * View for displaying an entire wikibase entity.
 * @since 0.3
 *
 * TODO: this is far from complete, right now this only serves the functionality to display an
 *       entity's claims (and statements in case of an item).
 */
$.widget( 'wikibase.entityview', {
	widgetName: 'wikibase-entityview',
	widgetBaseClass: 'wb-entityview',

	/**
	 * Section node containing the list of claims of the entity
	 * @type jQuery
	 */
	$claims: null,

	/**
	 * (Additional) default options
	 * @see jQuery.Widget.options
	 */
	options: {
		value: null
	},

	/**
	 * @see jQuery.Widget._create
	 */
	_create: function() {
		this.element.empty();

		var $claimsHeading =
			$( mw.template( 'wb-section-heading', mw.msg( 'wikibase-statements' ) ) );

		this._createClaims(); // build this.$claims

		// append all the stuff:
		this.element.append( $claimsHeading ).append( this.$claims );
	},

	/**
	 * Will build this.$claims but dosn't append it to the widgets main node yet.
	 * @since 0.3
	 */
	_createClaims: function() {
		var i, self = this;

		this.$claims = $( '<div/>', {
			'class': this.widgetBaseClass + '-claims'
		} );

		var claims = this.option( 'value' ).claims;

		// initialize view for each of the claims:
		for( i in claims ) {
			$( '<div/>' ).claimview( { 'value': claims[i] } ).appendTo( this.$claims );
		}
		// TODO: 'claim groups', claims should be grouped by their property

		// display 'add' button at the end of the claims:
		var toolbar = new wb.ui.Toolbar();

		toolbar.innerGroup = new wb.ui.Toolbar.Group();
		toolbar.btnAdd = new wb.ui.Toolbar.Button( mw.msg( 'wikibase-add' ) );
		$( toolbar.btnAdd ).on( 'action', function( event ) {
			self.enterNewClaim();
		} );
		toolbar.innerGroup.addElement( toolbar.btnAdd );
		toolbar.addElement( toolbar.innerGroup );
		toolbar.appendTo( this.$claims );
	},

	/**
	 * Will serve the view for a new claim.
	 * @since 0.3
	 */
	enterNewClaim: function() {
		var newClaim = $( '<div/>' ).claimview();
		this.$claims.children( '.wb-claimview' ).last().after( newClaim );

		// if new claim is canceled before saved, we simply remove it
		newClaim.one( 'snakviewstopediting', function( e, dropValue ) {
			if( dropValue ) {
				newClaim.claimview( 'destroy' ).remove();
			}
		} );
	}
} );

}( mediaWiki, wikibase, dataValues, dataTypes, jQuery ) );
