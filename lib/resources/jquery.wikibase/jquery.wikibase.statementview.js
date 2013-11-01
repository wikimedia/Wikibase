/**
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	var PARENT = $.wikibase.claimview;

/**
 * View for displaying and editing Wikibase Statements.
 * @since 0.4
 * @extends jQuery.wikibase.claimview
 *
 * @event afterremove: Triggered after a reference(view) has been remove from the statementview's
 *        list of references/-views.
 *        (1) {jQuery.Event}
 */
$.widget( 'wikibase.statementview', PARENT, {
	widgetBaseClass: 'wb-statementview',

	options: {
		template: 'wb-statement',
		templateParams: [
			'wb-last', // class: wb-first|wb-last
			function() { // class='wb-claim-$2'
				return ( this._claim && this._claim.getGuid() ) || 'new';
			},
			'', // TODO: This toolbar placeholder should be removed from the template.
			'', // .wb-claim-mainsnak
			'', // Qualifiers
			'', // References heading
			'' // List of references
		],
		templateShortCuts: {
			'$mainSnak': '.wb-claim-mainsnak',
			'$qualifiers': '.wb-statement-qualifiers',
			'$refsHeading': '.wb-statement-references-heading',
			'$references': '.wb-statement-references'
		}
	},

	/**
	 * Shortcut to the list item adapter in use in the reference view.
	 * @type {jquery.wikibase.listview.ListItemAdapter}
	 */
	_referenceviewLia: null,

	/**
	 * @see jQuery.claimview._create
	 */
	_create: function() {
		// add claimview class as well, so we inherit basic CSS rules:
		this.element.addClass( 'wb-claimview' );

		PARENT.prototype._create.call( this );

		var self = this,
			statement = this.value(),
			refs = statement ? statement.getReferences() : [];

		if( this.value() ) {
			var $listview = $( '<div/>' )
			.prependTo( this.$references )
			.listview( {
				listItemAdapter: new $.wikibase.listview.ListItemAdapter( {
					listItemWidget: $.wikibase.referenceview,
					listItemWidgetValueAccessor: 'value',
					newItemOptionsFn: function( value ) {
						return {
							value: value || null,
							statementGuid: self.value().getGuid()
						};
					}
				} ),
				value: refs
			} );

			this._referenceviewLia = $listview.data( 'listview' ).listItemAdapter();

			$listview
			.on( 'referenceviewitemadded referenceviewitemremoved', function( event, value, $li ) {
				self.drawReferencesCounter();
			} )
			.on( 'listviewenternewitem', function( event, $newLi ) {
				// Enter first item into the referenceview.
				self._referenceviewLia.liInstance( $newLi ).enterNewItem();

				var lia = self._referenceviewLia,
					liInstance = lia.liInstance( $newLi );

				if ( !liInstance.value() ) {
					$newLi
					.on( lia.prefixedEvent( 'afterstopediting' ), function( event, dropValue ) {
						if( dropValue ) {
							liInstance.destroy();
							$newLi.remove();
							self.drawReferencesCounter();
						} else {
							var newReferenceWithHash = liInstance.value();

							// Destroy new reference input form and add reference to list
							liInstance.destroy();
							$newLi.remove();

							// Display new reference with final GUID
							self._addReference( newReferenceWithHash );
						}
					} );
				}
			} );

			// Collapse references if there is at least one.
			if ( $listview.data( 'listview' ).items().length > 0 ) {
				this.$references.css( 'display', 'none' );
			}

			// toggle for references section:
			var $toggler = $( '<a/>' ).text(
				mw.msg( // will be overwritten by drawReferencesCounter() immediately!
					'wikibase-statementview-referencesheading-pendingcountersubject',
					refs.length
				)
			).toggler( { $subject: this.$references } );

			this.$refsHeading.append( $toggler );

			// replace heading with nice counter:
			this.drawReferencesCounter();
		}
	},

	/**
	 * Instantiates a statement with the statementview's current value.
	 * @see jQuery.wikibase.claimview._instantiateClaim
	 *
	 * @param {string} guid
	 * @return {wb.Statement}
	 */
	_instantiateClaim: function( guid ) {
		var qualifiers = null;

		// Gather qualifiers in one single wb.SnakList object. (The qualifiers are split into
		// separate snaklistivews grouping snaks featuring the same property.)
		if( this._qualifiers ) {
			var snaklistviews = this._qualifiers.value();

			qualifiers = new wb.SnakList();

			for( var i = 0; i < snaklistviews.length; i++ ) {
				qualifiers.add( snaklistviews[i].value() );
			}
		}

		return new wb.Statement(
			this.$mainSnak.data( 'snakview' ).snak(),
			qualifiers,
			this.getReferences(),
			null, // TODO: Rank
			guid
		);
	},

	/**
	 * Adds one reference to the list and renders it in the view.
	 * @since 0.4
	 *
	 * @param {wb.Reference} reference
	 */
	_addReference: function( reference ) {
		var lv = this.$references.children( ':wikibase-listview' ).data( 'listview' );
		lv.addItem( reference );
	},

	/**
	 * Returns all references currently set (including all pending changes).
	 *
	 * @return {wb.Reference[]}
	 */
	getReferences: function() {
		var self = this,
			listview = this.$references.children( ':wikibase-listview' ).data( 'listview' ),
			references = [];

		// If the statement is pending (not yet stored), the listview widget for the references is
		// not defined.
		if ( !listview ) {
			return references;
		}

		$.each( listview.items(), function( i, item ) {
			var referenceview = self._referenceviewLia.liInstance( $( item ) );
			references.push( referenceview.value() );
		} );

		return references;
	},

	/**
	 * Removes a referenceview from the list of references.
	 * @since 0.4
	 *
	 * @param {$.wikibase.referenceview} referenceview
	 */
	remove: function( referenceview ) {
		var self = this;

		referenceview.disable();

		this._removeReferenceApiCall( referenceview.value() )
			.done( function( pageInfo ) {
				var listview = self.$references.children( ':wikibase-listview' ).data( 'listview' );
				listview.removeItem( referenceview.element );
				self._trigger( 'afterremove' );
			} ).fail( function( errorCode, details ) {
				var error = wb.RepoApiError.newFromApiResponse( errorCode, details, 'remove' );

				referenceview.enable();
				referenceview.setError( error );
			} );
	},

	/**
	 * Triggers the API call to remove a reference.
	 * @since 0.4
	 *
	 * @param {wb.Reference} reference
	 * @return {jQuery.Promise}
	 */
	_removeReferenceApiCall: function( reference ) {
		var abstractedApi = new wb.AbstractedRepoApi(),
			guid = this.value().getGuid();

		return abstractedApi.removeReferences(
			guid,
			reference.getHash(),
			wb.getRevisionStore().getClaimRevision( guid )
		).done( function( baseRevId ) {
			// update revision store
			wb.getRevisionStore().setClaimRevision( baseRevId, guid );
		} );
	},

	/**
	 * @see $.widget.destroy
	 */
	destroy: function() {
		this.element.removeClass( 'wb-claimview' );
		PARENT.prototype.destroy.call( this );
	},

	/**
	 * Returns the current Statement represented by the view. If null is returned, than this is a
	 * fresh view where a new Statement is being constructed.
	 *
	 * @since 0.4
	 *
	 * @return {wb.Statement|null}
	 */
	value: function() {
		var claim = this._claim;

		if( !claim ) {
			return null;
		}
		if( !( claim instanceof wb.Statement ) ) {
			return new wb.Statement( claim.getMainSnak(), null, [], 0, claim.getGuid() );
		}
		return claim;
	},

	/**
	 * Will update the references counter in the DOM.
	 *
	 * @since 0.4
	 */
	drawReferencesCounter: function() {
		var listView = this.$references.children( ':wikibase-listview' ).data( 'listview' ),
			numberOfValues = listView.nonEmptyItems().length,
			numberOfPendingValues = listView.items().length - numberOfValues;

		// build a nice counter, displaying fixed and pending values:
		var $counterMsg = wb.utilities.ui.buildPendingCounter(
			numberOfValues,
			numberOfPendingValues,
			'wikibase-statementview-referencesheading-pendingcountersubject',
			'wikibase-statementview-referencesheading-pendingcountertooltip' );

		// update counter, don't touch the toggle!
		this.$refsHeading.find( '.ui-toggler-label' ).empty().append( $counterMsg );
	}
} );

// Register toolbars:
$.wikibase.toolbarcontroller.definition( 'addtoolbar', {
	id: 'references',
	selector: '.wb-statement-references',
	events: {
		listviewcreate: function( event ) {
			var $listview = $( event.target ),
				$node = $listview.parent();

			if( !$node.hasClass( 'wb-statement-references' ) ) {
				return;
			}

			$node.addtoolbar( {
				customAction: function( event, $parent ) {
					var statementView = $parent.closest( '.wb-statementview' ).data( 'statementview' ),
						$listview = statementView.$references.children( ':wikibase-listview' );
					$listview.data( 'listview' ).enterNewItem();
				},
				eventPrefix: $.wikibase.statementview.prototype.widgetEventPrefix,
				addButtonLabel: mw.msg( 'wikibase-addreference' )
			} );
		},
		listviewdestroy: function( event ) {
			var $listview = $( event.target ),
				$node = $listview.parent();

			if( !$node.hasClass( '.wb-statement-references' ) ) {
				return;
			}

			var addtoolbar = $node.data( 'addtoolbar' );

			if( addtoolbar ) {
				addtoolbar.destroy();
				$node.removeData( 'addtoolbar' );
			}
		}
	}
} );

$.wikibase.toolbarcontroller.definition( 'edittoolbar', {
	id: 'referenceview',
	selector: ':' + $.wikibase.referenceview.prototype.namespace
		+ '-' + $.wikibase.referenceview.prototype.widgetName,
	events: {
		referenceviewcreate: function( event ) {
			var $referenceview = $( event.target );

			$referenceview.edittoolbar( {
				interactionWidgetName: $.wikibase.referenceview.prototype.widgetName,
				parentWidgetFullName: 'wikibase.statementview'
			} );
		}

		// Destroying the referenceview will destroy the toolbar. Trying to destroy the toolbar
		// in parallel will cause interference.
	}
} );

}( mediaWiki, wikibase, jQuery ) );
