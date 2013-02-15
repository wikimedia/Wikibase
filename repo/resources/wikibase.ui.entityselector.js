( function( $, mw ) {
  'use strict';
  $( document ).ready( function() {
    $( '#searchInput' ).removeData( 'suggestionsContext' )
    $( '#searchInput' ).entityselector( {
        url: mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php',
        language: mw.config.get( 'wgUserLanguage' ),
    } );
  } );
}( jQuery, mediaWiki ) );
