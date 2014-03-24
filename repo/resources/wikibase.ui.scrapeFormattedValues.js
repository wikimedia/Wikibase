/**
 * Scrape initial formatted value from static HTML.
 * This is supposed to be temporary solution only. Front-end widgets should be initialized properly
 * on the static HTML rendering this file and its resource loader module obsolete.
 * @since 0.5
 * @licence GNU GPL v2+
 *
 * @author: H. Snater < mediawiki@snater.com >
 */
( function( $, mw, wb ) {
	'use strict';

	// TODO: This whole file along with its resource loader module should be removed. This requires
	// initializing the ui widgets on top of the DOM generated by the backend instead of
	// re-constructing the DOM in JavaScript.

	var DATA_VALUE_TYPES_TO_SCRAPE = [
		'globecoordinate',
		'quantity',
		'string',
		'wikibase-entityid'
	];

	mw.hook( 'wikipage.content' ).add( function() {

		if( mw.config.get( 'wbEntity' ) === null ) {
			return;
		}

		wb.__formattedValues = {};
		$.each( wb.entity.getClaims(), function( i, claim ) {
			var $claim = null,
				mainSnak = claim.getMainSnak(),
				$qualifierValues = null,
				scrapeType = null,
				iQualifiers = 0;

			if( isSnakToScrape( mainSnak ) ) {
				$claim = getClaimNode( claim.getGuid() );

				// Use .text() for quantities in order to avoid double escaping
				scrapeType = mainSnak.getValue().getType() === 'quantity' ? 'text' : 'html';

				wb.__formattedValues[JSON.stringify( claim.getMainSnak().getValue().toJSON() )]
					= $claim.children( '.wb-claim-mainsnak' ).find( '.wb-snak-value' )[scrapeType]();
			}

			$.each( claim.getQualifiers().getGroupedSnakLists(), function( j, snakList ) {
				snakList.each( function( k, snak ) {
					if( isSnakToScrape( snak ) ) {
						$claim = $claim || getClaimNode( claim.getGuid() );
						$qualifierValues = $qualifierValues || getQualifierValueNodes( $claim );

						wb.__formattedValues[JSON.stringify( snak.getValue().toJSON() )]
							= $qualifierValues.eq( iQualifiers ).html();
					}
					iQualifiers++;
				} );
			} );

			$.each( claim.getReferences(), function( j, reference ) {
				var $referenceValues = null,
					iReferences = 0;

				$.each( reference.getSnaks().getGroupedSnakLists(), function( j, snakList ) {
					snakList.each( function( k, snak ) {
						if( isSnakToScrape( snak ) ) {
							$referenceValues
								= $referenceValues || getReferenceValueNodes( reference.getHash() );

							wb.__formattedValues[JSON.stringify( snak.getValue().toJSON() )]
								= $referenceValues.eq( iReferences ).html();
						}
						iReferences++;
					} );
				} );

			} );

		} );

	} );

	/**
	 * @param {wikibase.Snak} snak
	 * @return {boolean}
	 */
	function isSnakToScrape( snak ) {
		return snak.getType() === 'value' &&
			$.inArray( snak.getValue().getType(), DATA_VALUE_TYPES_TO_SCRAPE ) !== -1;
	}

	/**
	 * @param {string} guid
	 * @return {jQuery}
	 */
	function getClaimNode( guid ) {
		return $( document.getElementsByClassName( 'wb-claim-' + guid )[0] );
	}

	/**
	 * @param {jQuery} $claim
	 * @return {jQuery}
	 */
	function getQualifierValueNodes( $claim ) {
		return $claim.children( '.wb-claim-qualifiers' ).find( '.wb-snak-value' );
	}

	/**
	 * @param {string} hash
	 * @return {jQuery}
	 */
	function getReferenceValueNodes( hash ) {
		var $reference = $( document.getElementsByClassName( 'wb-referenceview-' + hash )[0] );
		return $reference.find( '.wb-snak-value' );
	}

} )( jQuery, mediaWiki, wikibase );





