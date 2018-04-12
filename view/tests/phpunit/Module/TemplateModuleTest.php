<?php

namespace Wikibase\View\Tests\Module;

use PHPUnit4And6Compat;
use ResourceLoaderContext;
use Wikibase\View\Module\TemplateModule;

/**
 * @covers Wikibase\View\Module\TemplateModule
 *
 * @uses Wikibase\View\Template\TemplateFactory
 * @uses Wikibase\View\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0-or-later
 * @author Thiemo Kreuz
 */
class TemplateModuleTest extends \PHPUnit\Framework\TestCase {
	use PHPUnit4And6Compat;

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

	/**
	 * @return ResourceLoaderContext
	 */
	private function getResourceLoaderContext() {
		$context = $this->getMockBuilder( ResourceLoaderContext::class )
			->disableOriginalConstructor()
			->getMock();
		$context->method( 'getLanguage' )
			->will( $this->returnValue( 'en' ) );

		return $context;
	}

}
