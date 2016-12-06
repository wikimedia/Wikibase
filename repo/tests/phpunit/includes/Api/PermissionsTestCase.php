<?php

namespace Wikibase\Repo\Tests\Api;

use UsageException;
use Wikibase\Test\PermissionsHelper;
use Wikibase\Test\Repo\Api\WikibaseApiTestCase;

/**
 * Base class for permissions tests
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler <daniel.kinzler@wikimedia.de>
 * @author Addshore
 */
class PermissionsTestCase extends WikibaseApiTestCase {

	private static $hasSetup;

	protected function setUp() {
		parent::setUp();

		if ( !isset( self::$hasSetup ) ) {
			$this->initTestEntities( array( 'Oslo', 'Empty' ) );
		}
		self::$hasSetup = true;
		$this->stashMwGlobals( 'wgGroupPermissions' );
		$this->stashMwGlobals( 'wgUser' );
	}

	protected function doPermissionsTest(
		$action,
		array $params,
		array $permissions = null,
		$expectedError = null
	) {
		global $wgUser;

		$this->setMwGlobals( 'wgUser', clone $wgUser );
		PermissionsHelper::applyPermissions( $permissions );

		try {
			$params[ 'action' ] = $action;
			$this->doApiRequestWithToken( $params, null, $wgUser );

			if ( $expectedError !== null ) {
				$this->fail( 'API call should have failed with a permission error!' );
			} else {
				// the below is to avoid the tests being marked incomplete
				$this->assertTrue( true );
			}
		} catch ( UsageException $ex ) {
			if ( $expectedError !== true ) {
				$this->assertEquals( $expectedError, $ex->getCodeString(),
					'API did not return expected error code. Got error message ' . $ex );
			}
		}
	}

}
