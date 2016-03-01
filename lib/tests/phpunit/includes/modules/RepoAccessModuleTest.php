<?php

namespace Wikibase\Lib\Tests\Modules;

use PHPUnit_Framework_TestCase;
use ResourceLoaderContext;
use Wikibase\RepoAccessModule;

/**
 * @covers Wikibase\RepoAccessModule
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @license GPL-2.0+
 * @author Thiemo Mättig
 */
class RepoAccessModuleTest extends PHPUnit_Framework_TestCase {

	/**
	 * @return ResourceLoaderContext
	 */
	private function getContext() {
		return $this->getMockBuilder( 'ResourceLoaderContext' )
			->disableOriginalConstructor()
			->getMock();
	}

	public function testGetScript() {
		$module = new RepoAccessModule();
		$script = $module->getScript( $this->getContext() );
		$this->assertStringStartsWith( 'mediaWiki.config.set("wbRepo",', $script );
		$this->assertStringEndsWith( ');', $script );
	}

}
