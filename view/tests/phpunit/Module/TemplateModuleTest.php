<?php

namespace Wikibase\View\Tests\Module;

use PHPUnit_Framework_TestCase;
use Wikibase\View\Module\TemplateModule;

/**
 * @covers Wikibase\View\Module\TemplateModule
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class TemplateModuleTest extends PHPUnit_Framework_TestCase {

	public function testGetScript() {
		$instance = new TemplateModule();
		$script = $instance->getScript( $this->getResourceLoaderContext() );
		$this->assertInternalType( 'string', $script );
		$this->assertContains( 'wbTemplates', $script );
		$this->assertContains( 'set( {', $script );
	}

	public function testSupportsURLLoading() {
		$instance = new TemplateModule();
		$this->assertFalse( $instance->supportsURLLoading() );
	}

	public function testGetDefinitionSummary() {
		$context = $this->getResourceLoaderContext();
		$file = __DIR__ . '/../../../resources/templates.php';

		$instance = new TemplateModule();
		$oldSummary = $instance->getDefinitionSummary( $context );
		$this->assertInternalType( 'array', $oldSummary );
		$this->assertInternalType( 'string', $oldSummary['mtime'] );

		if ( !is_writable( $file ) || !touch( $file, mt_rand( 0, time() ) ) ) {
			$this->markTestSkipped( "Can't test the modified hash, if we can't touch the file" );
		}

		clearstatcache( $file );
		$newSummary = $instance->getDefinitionSummary( $context );

		$this->assertNotEquals( $oldSummary['mtime'], $newSummary['mtime'] );
	}

	private function getResourceLoaderContext() {
		$context = $this->getMockBuilder( 'ResourceLoaderContext' )
			->disableOriginalConstructor()
			->getMock();
		$context->expects( $this->any() )
			->method( 'getLanguage' )
			->will( $this->returnValue( 'en' ) );

		return $context;
	}

}
