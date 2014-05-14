/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( wb, $, QUnit ) {
	'use strict';

	/**
	 * Site details as required by the wikibase.Site constructor.
	 * @type {Object[]}
	 */
	var siteDetails = [
		{
			apiUrl: 'http://en.wikipedia.org/w/api.php',
			name: 'English',
			pageUrl: 'http://en.wikipedia.org/wiki/$1',
			shortName: 'English',
			languageCode: 'en',
			id: 'enwiki',
			group: 'whatever'
		},
		{
			apiUrl: 'http://de.wikipedia.org/w/api.php',
			name: 'Deutsch',
			pageUrl: 'http://de.wikipedia.org/wiki/$1',
			shortName: 'Deutsch',
			languageCode: 'de',
			id: 'dewiki',
			group: 'another'
		},
		{
			apiUrl: 'http://no.wikipedia.org/w/api.php',
			name: 'norsk bokmål',
			pageUrl: 'http://no.wikipedia.org/wiki/$1',
			shortName: 'norsk bokmål',
			languageCode: 'no',
			id: 'nowiki',
			group: 'foo'
		},
		{
			apiUrl: 'http://frrwiki.wikipedia.org/w/api.php',
			name: 'Nordfriisk',
			pageUrl: 'http://frrwiki.wikipedia.org/wiki/$1',
			shortName: 'Nordfriisk',
			languageCode: 'frr',
			id: 'frrwiki',
			group: 'foo'
		}
	];

	/**
	 * @type {wikibase.Site[]}
	 */
	var sites = [];

	for( var i = 0; i < siteDetails.length; i++ ) {
		sites.push( new wb.Site( siteDetails[i] ) );
	}

	/**
	 * Returns the predefined site featuring a specific site id.
	 *
	 * @param {string} siteId
	 * @return {wikibase.Site|null}
	 */
	function getSite( siteId ) {
		for( var i = 0; i < sites.length; i++ ) {
			if( sites[i].getId() === siteId ) {
				return sites[i];
			}
		}
		return null;
	}

	/**
	 * Factory creating a new siteselector enhanced input element.
	 *
	 * @param {Object} [options]
	 * @return  {jQuery} input element
	 */
	var newTestSiteSelector = function( options ) {
		options = $.merge( { source: sites }, options || {} );

		return $( '<input/>' ).addClass( 'test-siteselector' ).siteselector( options );
	};

	QUnit.module( 'jquery.wikibase.siteselector', QUnit.newWbEnvironment( {
		teardown: function() {
			$( '.test-siteselector' ).each( function( i, node ) {
				var $node = $( node );
				if( $node.data( 'siteselector' ) ) {
					$node.data( 'siteselector' ).destroy();
				}
				$node.remove();
			} );
		}
	} ) );

	QUnit.test( 'getSelectedSite()', function( assert ) {
		var $siteSelector = newTestSiteSelector(),
			siteSelector = $siteSelector.data( 'siteselector' );

		/**
		 * Key: Expected site id / Value: Input string.
		 * @type {Object}
		 */
		var testStrings = [
			{ enwiki: 'en' },
			{ dewiki: 'd' },
			{ enwiki: 'English (enwiki)'},
			{ dewiki: 'deutsch' },
			{ nowiki: 'no' }, // Prefer language code.
			{ enwiki: 'enwiki' },
			{ frrwiki: 'nord' }
		];

		var testString = function( string, expectedSiteId ) {
			$siteSelector.val( string );

			QUnit.stop();

			$siteSelector.one( 'siteselectoropen', function() {
				// siteselector sets the selected site on the "siteselector" open. So, defer
				// checking selected site:
				setTimeout( function() {
					assert.equal(
						siteSelector.getSelectedSite(),
						expectedSiteId ? getSite( expectedSiteId ) : null,
						'Implicitly selected expected site "' + ( expectedSiteId || 'NULL' )
							+ '" using input "' + string + '".'
					);
					siteSelector._close();
				}, 0 );

			} );

			siteSelector.search()
			.done( function( suggestions ) {
				assert.equal(
					suggestions.length > 0 ? suggestions[0] : null,
					expectedSiteId ? getSite( expectedSiteId ) : null,
					'Returned expected first suggestion "' + ( expectedSiteId || 'NULL' )
						+ '" using input "' + string + '".'
				);
			} )
			.fail( function() {
				QUnit.ok(
					false,
					'Search failed.'
				);
			} )
			.always( function() {
				QUnit.start();
			} );
		};

		for( var i = 0; i < testStrings.length; i++ ) {
			for( var siteId in testStrings[i] ) {
				testString( testStrings[i][siteId], siteId );
			}
		}

		// Reset selected site by clearing input:
		testString( '', null );

		testString( 'doesnotexist', null );
	} );

	QUnit.test( 'Item constructor', function( assert ) {
		var item = new $.wikibase.siteselector.Item( 'label', 'value', sites[0] );

		assert.ok(
			item instanceof $.wikibase.siteselector.Item,
			'Instantiated default siteselector item.'
		);

		assert.throws(
			function() {
				item = new $.wikibase.siteselector.Item( 'label', 'value' );
			},
			'Throwing error when omitting site on instantiation.'
		);
	} );

}( wikibase, jQuery, QUnit ) );
