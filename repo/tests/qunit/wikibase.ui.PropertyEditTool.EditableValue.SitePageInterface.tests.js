/**
 * QUnit tests for site page interface component of property edit tool
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @since 0.1
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author H. Snater
 */
'use strict';


( function() {
	module( 'wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface', window.QUnit.newWbEnvironment( {
		setup: function() {
			this.node = $( '<div/>', { id: 'subject' } ).append( $( 'a', { text: 'Link' } ) );
			this.siteDetails = {
				'en': {
					apiUrl: 'http://en.wikipedia.org/w/api.php',
					id: 'en',
					name: 'English Wikipedia',
					pageUrl: 'http://en.wikipedia.org/wiki/$1',
					shortName: 'English'
				},
				'de': {
					apiUrl: 'http://de.wikipedia.org/w/api.php',
					id: 'de',
					name: 'Deutsche Wikipedia',
					pageUrl: 'http://de.wikipedia.org/wiki/$1',
					shortName: 'Deutsch'
				}
			};
			this.sites = {
				'en': new window.wikibase.Site( this.siteDetails.en ),
				'de': new window.wikibase.Site( this.siteDetails.de )
			};
			this.language = {
				rtl: {
					code: 'fakertllang',
					dir: 'rtl'
				},
				ltr: {
					code: 'fakeltrlang',
					dir: 'ltr'
				}
			};
			this.subject = new window.wikibase.ui.PropertyEditTool.EditableValue.SitePageInterface( this.node, this.sites.en );

			ok(
				this.subject._subject[0] == this.node[0],
				'validated subject'
			);

		},
		teardown: function() {
			this.subject.destroy();

			equal(
				this.subject.site,
				null,
				'destroyed object'
			);

			this.subject = null;
			this.siteDetails = null;
			this.sites = null;
		}
	} ) );


	test( 'basic', function() {

		equal(
			this.subject.getSite(),
			this.sites.en,
			'verified site'
		);

		this.subject.setSite( this.sites.de );

		equal(
			this.subject.getSite(),
			this.sites.de,
			'set new site'
		);

	} );


	test( 'update language attributes', function() {

		this.subject.updateLanguageAttributes( this.language.ltr );

		equal(
			this.subject._subject.attr( 'lang' ),
			this.language.ltr.code,
			'assigned ltr language code to subject'
		);

		equal(
			this.subject._subject.attr( 'dir' ),
			this.language.ltr.dir,
			'assigned ltr language direction to subject'
		);

		this.subject.startEditing();

		this.subject.updateLanguageAttributes( this.language.rtl );

		equal(
			this.subject._inputElem.data( 'autocomplete' ).menu.element.attr( 'lang' ),
			this.language.rtl.code,
			'assigned rtl language code to auto-complete menu'
		);

		equal(
			this.subject._inputElem.data( 'autocomplete' ).menu.element.attr( 'dir' ),
			this.language.rtl.dir,
			'assigned rtl language direction to auto-complete menu'
		);

	} );


}() );
