( function( wb, $ ) {
	'use strict';

	/**
	 * Generates standardized output for errors.
	 *
	 * @license GPL-2.0+
	 * @author Daniel Werner < daniel.werner@wikimedia.de >
	 *
	 * @param {Error} error
	 * @return {jQuery}
	 */
	wb.buildErrorOutput = function( error ) {
		var $message = $( '<div/>' ).addClass( 'wb-error' );

		$message.append( $( '<div/>' ).addClass( 'wb-error-message' ).text( error.message ) );

		if ( error.detailedMessage ) {
			$message.append( $( '<p/>', {
				'class': 'wb-error-details',
				html: error.detailedMessage
			} ) );
		}

		return $message;
	};

}( wikibase, jQuery ) );
