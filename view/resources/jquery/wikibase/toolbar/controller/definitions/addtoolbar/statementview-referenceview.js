( function( $, mw ) {
	'use strict';

/**
 * @ignore
 *
 * @license GPL-2.0+
 * @author H. Snater < mediawiki@snater.com >
 */
$.wikibase.toolbarcontroller.definition( 'addtoolbar', {
	id: 'statementview-referenceview',
	selector: '.wikibase-statementview',
	events: {
		statementviewcreate: function( event, toolbarController ) {
			var $statementview = $( event.target ),
				statementview = $statementview.data( 'statementview' ),
				$node = statementview.$references;

			$node
			.addtoolbar( {
				$container: $( '<div/>' ).appendTo( $node ),
				label: mw.msg( 'wikibase-addreference' )
			} )
			.on( 'addtoolbaradd.addtoolbar', function( e ) {
				if ( e.target !== $node.get( 0 ) ) {
					return;
				}

				statementview._controller.startEditing().done( function() {
					var listview = statementview._referencesListview,
						lia = listview.listItemAdapter();

					listview.enterNewItem().done( function( $referenceview ) {
						var referenceview = lia.liInstance( $referenceview );
						referenceview.focus();
					} );

					// Re-focus "add" button after having added or having cancelled adding a reference:
					var eventName = lia.prefixedEvent( 'afterstopediting.addtoolbar' );
					listview.element.one( eventName, function( event ) {
						$node.data( 'addtoolbar' ).focus();
					} );
				} );

				toolbarController.registerEventHandler(
					event.data.toolbar.type,
					event.data.toolbar.id,
					'listviewdestroy',
					function( event, toolbarController ) {
						var $listview = $( event.target ),
							$node = $listview.parent();

						if ( !$node.hasClass( '.wikibase-statementview-references' ) ) {
							return;
						}

						toolbarController.destroyToolbar( $node.data( 'addtoolbar' ) );
						$node.off( 'addtoolbar' );
					}
				);
			} );

			toolbarController.registerEventHandler(
				event.data.toolbar.type,
				event.data.toolbar.id,
				'statementviewdisable',
				function( event ) {
					if ( event.target !== $statementview.get( 0 ) ) {
						return;
					}
					$node.data( 'addtoolbar' )[
						statementview.option( 'disabled' )
							? 'disable'
							: 'enable'
					]();
				}
			);
		}
	}
} );

}( jQuery, mediaWiki ) );
