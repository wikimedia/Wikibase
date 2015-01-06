<?php

namespace Wikibase\Test;

use User;

/**
 * @covers Wikibase\Repo\BabelUserLanguageLookup
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group EntityView
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class BabelUserLanguageLookupTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @param string $subject
	 *
	 * @return string[]
	 */
	private function split( $subject ) {
		return empty( $subject ) ? array() : explode( '|', $subject );
	}

	/**
	 * TODO: We really want to test grabbing languages from the Babel extension,
	 * but how can we test that?
	 *
	 * @dataProvider userLanguagesProvider
	 *
	 * @param string $usersLanguage
	 * @param string $babelLanguages
	 * @param string $userSpecifiedLanguages
	 * @param string $allExpected
	 */
	public function testGetUserLanguages(
		$usersLanguage,
		$babelLanguages,
		$userSpecifiedLanguages,
		$allExpected
	) {
		$message = $usersLanguage . ' with {{#babel:' . $babelLanguages . '}} in assert #';

		$babelLanguages            = $this->split( $babelLanguages );
		$userSpecifiedLanguages    = $this->split( $userSpecifiedLanguages );
		$allExpected               = $this->split( $allExpected );

		$user = new User();
		// Required to not be anonymous
		$user->setId( 1 );
		$user->setName( 'UserLanguageLookupTest-TestUser' );
		$user->setOption( 'language', $usersLanguage );
		// Not a real option, just to manipulate the double class
		$user->setOption( 'babelLanguages', $babelLanguages );
		$userLanguageLookup = new BabelUserLanguageLookupDouble( $user );

		$this->assertEquals( $allExpected, array_values(
			$userLanguageLookup->getAllUserLanguages( $user ) ), $message . '1' );
		$this->assertEquals( $userSpecifiedLanguages,
			$userLanguageLookup->getUserSpecifiedLanguages( $user ), $message . '5' );
	}

	public function userLanguagesProvider() {
		return array(
			// 1. Language from the users settings
			// 2. List of languages from the users babel box (as returned by the Babel extension)
			// 3. List of usable user specified languages
			// 4. Expected collection of all languages
			array( 'de', '',         '',         'de'       ),
			array( 'de', 'en',       'en',       'de|en'    ),
			array( 'de', 'de|en|fr', 'de|en|fr', 'de|en|fr' ),
			array( 'en', '',         '',         'en',      ),
			array( 'en', 'en',       'en',       'en',      ),
			array( 'en', 'de|en|fr', 'de|en|fr', 'en|de|fr' ),

			// Codes reported from Babel are getting lower-cased
			array( 'en', 'nds-NL',   'nds-nl',   'en|nds-nl', 'en|nds-nl', 'nds-nl' ),

			// Invalid codes (codes we don't support) returned by Babel get removed
			array( 'en', 'invalid-language-code', '', 'en', 'en', '' ),
		);
	}

}
