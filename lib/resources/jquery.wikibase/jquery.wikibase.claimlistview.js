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
 * View for displaying and editing several Wikibase Claims.
 * @since 0.3
 *
 * @option {wb.Claim[]|null} value The claims displayed by this view. If this is null, this view
 *         will only display an add button, for adding new claims.
 * @option {String} entityId The ID of the entity, new Claims should be added to.
 */
$.widget( 'wikibase.claimlistview', {
	widgetName: 'wikibase-claimlistview',
	widgetBaseClass: 'wb-claimlistview',

	/**
	 * Node of the toolbar
	 * @type jQuery
	 */
	$toolbar: null,

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
		var self = this;
		this.element.addClass( this.widgetBaseClass );
		this.element.applyTemplate( 'wb-claims-section',
			'', // .wb-claims
			''  // edit section DOM
		);

		this.$claims = this.element.find( '.wb-claims' );
		this.$toolbar = this.element.find( '.wb-claims-toolbar' );

		this._createClaims(); // build this.$claims
		this._createToolbar();

		// make sure to add/remove 'wb-edit' class to sections when in edit mode
		this.element.on( 'snakviewstopediting snakviewstartediting', function( e ) {
			if( e.type === 'snakviewstartediting' ) {
				$( e.target ).parents( '.wb-claim-section' ).addClass( 'wb-edit' );
			} else {
				// remove 'wb-edit' from all section nodes if the section itself has not child
				// nodes with 'wb-edit' still set. This is necessary because of how we remove new
				// Claims added to an existing section. Also required if we want multiple edit modes.
				self.element.find( '.wb-claim-section' )
					.not( ':has( >.wb-edit )' ).removeClass( 'wb-edit' );
				// NOTE: could be performance optimized with edit counter per section
			}
		} );
	},

	/**
	 * @see $.widget.destroy
	 */
	destroy: function() {
		this.element.removeClass( this.widgetBaseClass );
		$.Widget.prototype.destroy.call( this );
	},

	/**
	 * Will build this.$claims but doesn't append it to the widgets main node yet.
	 * @since 0.3
	 */
	_createClaims: function() {
		var i, self = this,
			claims = this.option( 'value' );

		// initialize view for each of the claims:
		for( i in claims ) {
			this._addClaim( claims[i] );
		}
	},

	/**
	 * Creates the toolbar holding the 'add' button for adding new claims.
	 * @since 0.3
	 */
	_createToolbar: function() {
		// display 'add' button at the end of the claims:
		var self = this,
			toolbar = this._buildAddToolbar();

		$( toolbar.btnAdd ).on( 'action', function( event ) {
			self.enterNewClaim();
		} );

		toolbar.appendTo( $( '<div/>' ).addClass( 'wb-editsection' ).appendTo( this.$toolbar ) );
	},

	/**
	 * Creates a toolbar with an 'add' button
	 */
	_buildAddToolbar: function() {
		// display 'add' button at the end of the claims:
		var toolbar = new wb.ui.Toolbar();

		toolbar.innerGroup = new wb.ui.Toolbar.Group();
		toolbar.btnAdd = new wb.ui.Toolbar.Button( mw.msg( 'wikibase-add' ) );
		toolbar.innerGroup.addElement( toolbar.btnAdd );
		toolbar.addElement( toolbar.innerGroup );

		return toolbar;
	},

	/**
	 * Adds one claim to the list and renders it in the view.
	 * @since 0.3
	 *
	 * @param {wb.Claim} claim
	 */
	_addClaim: function( claim ) {
		var propertyId = claim.getMainSnak().getPropertyId(),
			$newClaim = $( '<div/>' ).claimview( { 'value': claim } );

		this._insertClaimRow( propertyId, $newClaim );
	},

	/**
	 * Inserts some content (usually a Claim) as a row in one of the Claim sections.
	 * @since 0.4
	 *
	 * @param {String} claimSectionPropertyId
	 * @param {jQuery} $content
	 */
	_insertClaimRow: function( claimSectionPropertyId, $content ) {
		// get the section we want to insert stuff in:
		var $claimRows = this._serveClaimSection( claimSectionPropertyId );

		// insert before last child node in that list (there is at least one node always, holding the 'add)
		$claimRows.children( '.wb-claim-container' ).last().before( $content );

		// TODO: optimize, don't do this every time, perhaps even use a CSS solution
		//       take a look at http://reference.sitepoint.com/css/adjacentsiblingselector
		var $claims = $claimRows.not( '.wb-claim-add' ).add( $content );

		$claims.removeClass( 'wb-first wb-last' );
		$claims.first().addClass( 'wb-first' );
		$claims.last().addClass( 'wb-last' );
	},

	/**
	 * Returns the section a claim had to be displayed in, depending on its Main Snak's property ID.
	 * If a section with Claims having this property as a main snak exists already, then this
	 * section will be returned. Otherwise, a new section will be created.
	 * @since 0.3
	 *
	 * @param {String} mainSnakPropertyId
	 * @return jQuery
	 */
	_serveClaimSection: function( mainSnakPropertyId ) {
		// query for existing section in this view:
		var $section = this.$claims.children( '.wb-claim-section-' + mainSnakPropertyId ).first();

		if( $section.length === 0 ) {
			var property = wb.entities[ mainSnakPropertyId ],
				$addClaim,
				$toolbarParent = $( '<span/>' ).addClass( 'wb-editsection' ),
				toolbar = this._buildAddToolbar(),
				self = this;

			if( !property ) {
				// Property info not in local store.
				// This should not ever happen if used properly, but make sure!
				throw new Error( 'Information for Property "' + mainSnakPropertyId + '" not found in local store' );
			}

			// 'add' button at the end of each claim section:
			$( toolbar.btnAdd ).on( 'action', function( event ) {
				self.enterNewClaimInSection( mainSnakPropertyId );
			} );
			toolbar.appendTo( $toolbarParent );

			// TODO: use another template for this, this isn't really a claim!
			$addClaim = mw.template( 'wb-claim',
				'wb-claim-add',
				'add', // class='wb-claim-$2'
				'', // .wb-claim-mainsnak
				$toolbarParent // .wb-claim-toolbar
			);

			// create new section for first Claim in this section
			$section = mw.template( 'wb-claim-section',
				mainSnakPropertyId, // main Snak's property ID
				property.label || mw.msg( 'wikibase-label-empty' ), // property name
				$addClaim // claim
			).appendTo( this.$claims );
		}

		return $section;
	},

	/**
	 * Will serve the view for defining a new Claim.
	 * @since 0.3
	 *
	 * @param {Number} [sectionPropertyId] The new Claim's Main Snak property. If omitted, the
	 *        property will have to be chosen by the user.
	 */
	enterNewClaim: function( sectionPropertyId ) {
		var self = this,
			$newClaim = $( '<div/>' ),
			options = { predefined: {} };

		if( sectionPropertyId ) {
			// only allow creation of Claim with Main Snak using that one property ID
			options.predefined.mainSnak = {
				property: sectionPropertyId
			};
		}

		if( sectionPropertyId ) {
			// insert claim to its section
			this._insertClaimRow( sectionPropertyId, $newClaim );
		} else {
			// insert Claim before toolbar with add button
			this.$claims.append( $newClaim );
		}

		// initialize view after node is in DOM, so the 'startediting' event can bubble
		$newClaim.claimview( options ).addClass( 'wb-claim-new' );

		// first time the claim (or at least a part of it) drops out of edit mode:
		// TODO: not nice to use the event of the main snak, perhaps the claimview could offer one!
		$newClaim.one( 'snakviewstopediting', function( event, dropValue ) {
			// TODO: right now, if the claim is not valid (e.g. because data type not yet supported,
			//       the edit mode will close when saving without showing any hint!
			var claim = $newClaim.claimview( 'value' );

			if( dropValue || claim === null ) {
				// if new claim is canceled before saved, or if it is invalid, we simply remove
				// and forget about it
				$newClaim.claimview( 'destroy' ).remove();
			} else {
				// TODO: add newly created claim to model of represented entity!
				event.preventDefault();

				var api = new wb.Api(),
					mainSnak = claim.getMainSnak();

				// TODO: use a proper base rev id!
				api.createClaim( self.option( 'entityId' ), 0, mainSnak )
				.done( function( newClaimWithGUID ) {
					$( event.target ).data( 'snakview' ).stopEditing();

					// destroy new claim input form and add claim to this list
					$newClaim.claimview( 'destroy' ).remove();
					self._addClaim( newClaimWithGUID ); // display new claim with final GUID
				} );
				// TODO: error handling
			}
		} );
	},

	/**
	 * Will serve the view for defining a new Claim whose Main Snak will have a certain, pre-defined
	 * property. This means the input form for the new Claim will be inserted right into the section
	 * where the Claim will be displayed later. Also, the property won't be changeable for the user.
	 * @since 0.4
	 *
	 * @param {Number} sectionPropertyId The new Claim's Main Snak property.
	 */
	enterNewClaimInSection: function( sectionPropertyId ) {
		this.enterNewClaim( sectionPropertyId );
	}
} );

}( mediaWiki, wikibase, jQuery ) );
