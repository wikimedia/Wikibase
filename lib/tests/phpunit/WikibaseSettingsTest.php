<?php

namespace Wikibase\Lib\Tests;

use MWException;
use PHPUnit_Framework_TestCase;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Term\Term;
use Wikibase\WikibaseSettings;
use Wikimedia\Assert\ParameterAssertionException;

/**
 * @covers Wikibase\WikibaseSettings
 *
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler <daniel.kinzler@wikimedia.de>
 */
class WikibaseSettingsTest extends PHPUnit_Framework_TestCase {

	public function testGetSettings() {
		if ( WikibaseSettings::isClientEnabled() ) {
			$this->assertNotNull( WikibaseSettings::getSettings( 'Client' ) );
		}

		if ( WikibaseSettings::isRepoEnabled() ) {
			$this->assertNotNull( WikibaseSettings::getSettings( 'Repo' ) );
		}
	}

	public function testGetRepoSettings() {
		if ( WikibaseSettings::isRepoEnabled() ) {
			$this->assertNotNull( WikibaseSettings::getRepoSettings() );
		} else {
			$this->setExpectedException( MWException::class );
			WikibaseSettings::getRepoSettings();
		}
	}

	public function testGetClientSettings() {
		if ( WikibaseSettings::isClientEnabled() ) {
			$this->assertNotNull( WikibaseSettings::getClientSettings() );
		} else {
			$this->setExpectedException( MWException::class );
			WikibaseSettings::getClientSettings();
		}
	}

}
