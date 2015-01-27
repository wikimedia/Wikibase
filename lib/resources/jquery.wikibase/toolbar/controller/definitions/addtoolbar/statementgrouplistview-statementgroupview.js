( function( $ ) {
	'use strict';

/**
 * @ignore
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
$.wikibase.toolbarcontroller.definition( 'addtoolbar', {
	id: 'statementgrouplistview-statementgroupview',
	selector: ':' + $.wikibase.statementgrouplistview.prototype.namespace
		+ '-' + $.wikibase.statementgrouplistview.prototype.widgetName,
	events: {
		statementgrouplistviewcreate: function( event, toolbarcontroller ) {
			var $statementgrouplistview = $( event.target ),
				statementgrouplistview = $statementgrouplistview.data( 'statementgrouplistview' );

			$statementgrouplistview.addtoolbar( {
				$container: $( '<div/>' ).appendTo( $statementgrouplistview )
			} )
			.on( 'addtoolbaradd.addtoolbar', function( e ) {
				if( e.target !== $statementgrouplistview.get( 0 ) ) {
					return;
				}

				statementgrouplistview.enterNewItem().done( function( $statementgroupview ) {
					var statementgroupview = $statementgroupview.data( 'statementgroupview' ),
						$statementlistview = statementgroupview.$statementlistview,
						statementlistview = $statementlistview.data( 'statementlistview' );
					statementlistview.focus();
				} );

				toolbarcontroller.registerEventHandler(
					event.data.toolbar.type,
					event.data.toolbar.id,
					'statementgrouplistviewdestroy',
					function( event, toolbarController ) {
						toolbarController.destroyToolbar( $( event.target ).data( 'addtoolbar' ) );
					}
				);
			} );

			// TODO: Integrate state management into addtoolbar
			toolbarcontroller.registerEventHandler(
				event.data.toolbar.type,
				event.data.toolbar.id,
				'statementgrouplistviewdisable',
				function() {
					$statementgrouplistview.data( 'addtoolbar' )[
						statementgrouplistview.option( 'disabled' )
						? 'disable'
						: 'enable'
					]();
				}
			);
		}
	}
} );

}( jQuery ) );
