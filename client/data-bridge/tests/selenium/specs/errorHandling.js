const assert = require( 'assert' ),
	Api = require( 'wdio-mediawiki/Api' ),
	LoginPage = require( 'wdio-mediawiki/LoginPage' ),
	DataBridgePage = require( '../pageobjects/dataBridge.page' ),
	ErrorSavingAssertUser = require( '../pageobjects/ErrorSavingAssertUser' ),
	WikibaseApi = require( 'wdio-wikibase/wikibase.api' ),
	DomUtil = require( './../DomUtil' ),
	NetworkUtil = require( './../NetworkUtil' ),
	WindowUtil = require( './../WindowUtil' );

describe( 'App', () => {
	it( 'shows ErrorUnknown when launching bridge for a non-existent entity', () => {
		const title = DataBridgePage.getDummyTitle();
		const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
		const nonExistentEntityId = 'Q999999999';
		const content = DataBridgePage.createInfoboxWikitext( [ {
			label: 'shows the occurrence of errors',
			entityId: nonExistentEntityId,
			propertyId,
			editFlow: 'single-best-value',
		} ] );

		browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

		DataBridgePage.openAppOnPage( title );

		DataBridgePage.error.waitForDisplayed( 5000 );
		assert.ok( DataBridgePage.error.isDisplayed() );
		assert.ok( DataBridgePage.showsErrorUnknown() );

		const errorText = DataBridgePage.error.getText();
		assert.ok( errorText.match( new RegExp( propertyId ) ) );
		assert.ok( errorText.match( new RegExp( nonExistentEntityId ) ) );
	} );

	it( 'can be relaunched from ErrorUnknown', () => {
		const title = DataBridgePage.getDummyTitle();
		const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
		const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
			'claims': [ {
				'mainsnak': {
					'snaktype': 'value',
					'property': propertyId,
					'datavalue': { 'value': 'foo bar baz', 'type': 'string' },
				},
				'type': 'statement',
				'rank': 'normal',
			} ],
		} ) );
		const content = DataBridgePage.createInfoboxWikitext( [ {
			label: 'prevail at last',
			entityId,
			propertyId,
			editFlow: 'single-best-value',
		} ] );
		browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

		DataBridgePage.open( title );

		NetworkUtil.disableNetwork();
		DataBridgePage.launchApp();
		DataBridgePage.error.waitForDisplayed();

		assert.ok( DataBridgePage.showsErrorUnknown() );

		NetworkUtil.enableNetwork();
		DataBridgePage.errorUnknownRelaunch.click();
		DataBridgePage.app.waitForDisplayed( 10000 );
		DataBridgePage.dismissWarningAnonymousEdit();
		DataBridgePage.bridge.waitForDisplayed();
	} );

	it( 'can retry saving bridge from ErrorSaving', () => {
		const title = DataBridgePage.getDummyTitle();
		const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
		const stringPropertyExampleValue = 'initialValue';
		const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
			'claims': [ {
				'mainsnak': {
					'snaktype': 'value',
					'property': propertyId,
					'datavalue': { 'value': stringPropertyExampleValue, 'type': 'string' },
				},
				'type': 'statement',
				'rank': 'normal',
			} ],
		} ) );
		const content = DataBridgePage.createInfoboxWikitext( [ {
			label: 'official website',
			entityId,
			propertyId,
			editFlow: 'single-best-value',
		} ] );
		browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

		DataBridgePage.openAppOnPage( title );

		DataBridgePage.bridge.waitForDisplayed( 5000 );
		assert.ok( DataBridgePage.bridge.isDisplayed() );

		const newValue = 'newValue';
		DomUtil.setValue( DataBridgePage.value, newValue );

		DataBridgePage.editDecision( 'replace' ).click();

		// show License
		DataBridgePage.saveButton.click();
		DataBridgePage.licensePopup.waitForDisplayed();

		// lose internet connection
		NetworkUtil.disableNetwork();

		// actually trigger save
		DataBridgePage.saveButton.click();

		// show ErrorSaving screen
		DataBridgePage.error.waitForDisplayed();

		assert.ok( DataBridgePage.showsErrorSaving() );

		// restore internet connection
		NetworkUtil.enableNetwork();
		DataBridgePage.retrySaveButton.click();
		DataBridgePage.thankYouScreen.waitForDisplayed();
	} );

	it( 'can go back from a save error both on desktop and mobile', () => {
		const title = DataBridgePage.getDummyTitle();
		const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
		const stringPropertyExampleValue = 'initialValue';
		const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
			'claims': [ {
				'mainsnak': {
					'snaktype': 'value',
					'property': propertyId,
					'datavalue': { 'value': stringPropertyExampleValue, 'type': 'string' },
				},
				'type': 'statement',
				'rank': 'normal',
			} ],
		} ) );
		const content = DataBridgePage.createInfoboxWikitext( [ {
			label: 'official website',
			entityId,
			propertyId,
			editFlow: 'single-best-value',
		} ] );
		browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

		DataBridgePage.openAppOnPage( title );

		DataBridgePage.bridge.waitForDisplayed( 5000 );
		assert.ok( DataBridgePage.bridge.isDisplayed() );

		const newValue = 'newValue';
		DomUtil.setValue( DataBridgePage.value, newValue );

		DataBridgePage.editDecision( 'replace' ).click();

		// show License
		DataBridgePage.saveButton.click();
		DataBridgePage.licensePopup.waitForDisplayed();

		// lose internet connection
		NetworkUtil.disableNetwork();

		// actually trigger save
		DataBridgePage.saveButton.click();

		// show ErrorSaving screen
		DataBridgePage.error.waitForDisplayed();

		assert.ok( DataBridgePage.showsErrorSaving() );

		// ensure that we are on desktop
		DataBridgePage.setMobileWindowSize( false );

		assert.ok( DataBridgePage.errorSavingBackButton.isDisplayed() );
		assert.ok( !DataBridgePage.headerBackButton.isDisplayed() );
		DataBridgePage.errorSavingBackButton.click();

		DataBridgePage.value.waitForDisplayed();
		assert.equal( DataBridgePage.value.getValue(), newValue );

		// switch to mobile
		DataBridgePage.setMobileWindowSize();

		// show License
		DataBridgePage.saveButton.click();
		DataBridgePage.licensePopup.waitForDisplayed();

		// actually trigger save
		DataBridgePage.saveButton.click();

		// show ErrorSaving screen
		DataBridgePage.error.waitForDisplayed();

		DataBridgePage.errorSavingBackButton.waitForDisplayed( undefined, true );
		DataBridgePage.headerBackButton.waitForDisplayed();
		DataBridgePage.headerBackButton.click();

		DataBridgePage.value.waitForDisplayed();
		assert.equal( DataBridgePage.value.getValue(), newValue );
	} );

	describe( 'when assertuser fails', () => {
		beforeEach( 'login, run bridge, logout, trigger error', () => {
			// log in
			LoginPage.loginAdmin();

			// prepare Bridge for saving
			const title = DataBridgePage.getDummyTitle();
			const propertyId = browser.call( () => WikibaseApi.getProperty( 'string' ) );
			const stringPropertyExampleValue = 'initialValue';
			const entityId = browser.call( () => WikibaseApi.createItem( 'data bridge browser test item', {
				'claims': [ {
					'mainsnak': {
						'snaktype': 'value',
						'property': propertyId,
						'datavalue': { 'value': stringPropertyExampleValue, 'type': 'string' },
					},
					'type': 'statement',
					'rank': 'normal',
				} ],
			} ) );
			const content = DataBridgePage.createInfoboxWikitext( [ {
				label: 'official website',
				entityId,
				propertyId,
				editFlow: 'single-best-value',
			} ] );
			browser.call( () => Api.bot().then( ( bot ) => bot.edit( title, content ) ) );

			DataBridgePage.openAppOnPage( title );
			DataBridgePage.bridge.waitForDisplayed( 5000 );

			const newValue = 'newValue';
			DomUtil.setValue( DataBridgePage.value, newValue );

			DataBridgePage.editDecision( 'replace' ).click();

			DataBridgePage.saveButton.click();
			DataBridgePage.licensePopup.waitForDisplayed();

			// log out
			browser.deleteCookies();

			// trigger error
			DataBridgePage.saveButton.click();
			DataBridgePage.error.waitForDisplayed();
		} );

		it( 'can retry saving without assertuser', () => {
			assert.ok( ErrorSavingAssertUser.isDisplayed() );

			// go back, try again
			ErrorSavingAssertUser.clickBackButton();
			DataBridgePage.saveButton.click();
			DataBridgePage.licensePopup.waitForDisplayed();
			DataBridgePage.saveButton.click();
			DataBridgePage.error.waitForDisplayed();

			assert.ok( ErrorSavingAssertUser.isDisplayed() );

			// save without logging in
			ErrorSavingAssertUser.proceedButton.click();

			DataBridgePage.thankYouScreen.waitForDisplayed();
		} );

		it( 'can login and retry saving', () => {
			assert.ok( ErrorSavingAssertUser.isDisplayed() );

			// log in
			ErrorSavingAssertUser.loginButton.click();
			WindowUtil.doInOtherWindow( () => {
				LoginPage.username.waitForDisplayed();
				DomUtil.setValue( LoginPage.username, browser.config.mwUser );
				DomUtil.setValue( LoginPage.password, browser.config.mwPwd );
				LoginPage.loginButton.click();
				LoginPage.username.waitForDisplayed( undefined, /* reverse: */ true );
			} );

			// app should have returned from error in the meantime
			assert.ok( !DataBridgePage.error.isExisting() );

			// try again
			DataBridgePage.saveButton.click();
			DataBridgePage.licensePopup.waitForDisplayed();
			DataBridgePage.saveButton.click();

			DataBridgePage.thankYouScreen.waitForDisplayed();
		} );

		it( 'still asserts user after logging in', () => {
			assert.ok( ErrorSavingAssertUser.isDisplayed() );

			// click login button, but close tab without logging in
			ErrorSavingAssertUser.loginButton.click();
			WindowUtil.doInOtherWindow( () => {
				LoginPage.username.waitForDisplayed();
			} );

			// app should have returned from error in the meantime
			assert.ok( !DataBridgePage.error.isExisting() );

			// try again
			DataBridgePage.saveButton.click();
			DataBridgePage.licensePopup.waitForDisplayed();
			DataBridgePage.saveButton.click();

			// should show error again
			DataBridgePage.error.waitForDisplayed();
			assert.ok( ErrorSavingAssertUser.isDisplayed() );
		} );

		it( 'shows custom Bridge warning on login page', () => {
			assert.ok( ErrorSavingAssertUser.isDisplayed() );

			// go to login page
			ErrorSavingAssertUser.loginButton.click();
			WindowUtil.doInOtherWindow( () => {
				LoginPage.username.waitForDisplayed();
				/*
				 * The login page could be displayed in any language,
				 * so we can’t assert a particular text, but we can
				 * look for the <strong> part of our message.
				 */
				assert.ok( browser.$( '.warningbox strong' ).isDisplayed() );
			} );
		} );
	} );

} );
