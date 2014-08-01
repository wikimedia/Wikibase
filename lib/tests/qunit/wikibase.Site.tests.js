/**
 * @licence GNU GPL v2+
 * @author H. Snater
 */

( function( mw, wb, $, QUnit ) {
	'use strict';

	var siteDetails = {
		apiUrl: 'http://en.wikipedia.org/w/api.php',
		id: 'en-global',
		name: 'English Wikipedia',
		pageUrl: 'http://en.wikipedia.org/wiki/$1',
		shortName: 'English',
		languageCode: 'en',
		group: 'wikipedia'
	};

	var string = 'test',
		site = new wb.Site( siteDetails );

	QUnit.module( 'wikibase.Site', QUnit.newWbEnvironment() );

	QUnit.test( 'check init', function( assert ) {
		assert.strictEqual(
			site._siteDetails,
			siteDetails,
			'set site details'
		);

		assert.strictEqual(
			site.getId(),
			siteDetails.id,
			'verified site id'
		);

		assert.strictEqual(
			site.getName(),
			siteDetails.name,
			'verified site name'
		);

		assert.strictEqual(
			site.getShortName(),
			siteDetails.shortName,
			'verified short site name'
		);

		assert.strictEqual(
			site.getApi(),
			siteDetails.apiUrl,
			'verified site api'
		);

		assert.strictEqual(
			site.getGroup(),
			siteDetails.group,
			'verified site group'
		);
	} );

	QUnit.test( 'link handling', function( assert ) {

		assert.equal(
			site.getLinkTo( string )[0].nodeName,
			'A',
			'created DOM node for link'
		);

	} );

	QUnit.test( 'language functions', function( assert ) {

		assert.equal(
			site.getLanguageCode(),
			'en',
			'retrieved language code'
		);

		assert.equal(
			site.getLanguageDirection(),
			( $.uls !== undefined ) ? 'ltr' : 'auto',
			'retrieved ltr language direction'
		);

		site._siteDetails.languageCode = 'ar';

		assert.equal(
			site.getLanguageDirection(),
			( $.uls !== undefined ) ? 'rtl' : 'auto',
			'retrieved rtl language direction'
		);

		site._siteDetails.languageCode = 'non-existing-code';

		assert.equal(
			site.getLanguageDirection(),
			'auto',
			'received "auto" when no special language direction could be retrieved'
		);

	} );

}( mediaWiki, wikibase, jQuery, QUnit ) );
