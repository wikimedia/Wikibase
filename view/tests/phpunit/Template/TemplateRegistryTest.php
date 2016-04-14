<?php

namespace Wikibase\View\Tests\Template;

use PHPUnit_Framework_TestCase;
use Wikibase\View\Template\TemplateRegistry;

/**
 * @covers Wikibase\View\Template\TemplateRegistry
 *
 * @group Wikibase
 * @group WikibaseView
 *
 * @license GPL-2.0+
 * @author Thiemo Mättig
 */
class TemplateRegistryTest extends PHPUnit_Framework_TestCase {

	public function testCanConstructWithEmptyArray() {
		$registry = new TemplateRegistry( array() );
		$this->assertSame( array(), $registry->getTemplates() );
	}

	public function testRemovesTabs() {
		$registry = new TemplateRegistry( array( 'key' => "no\ttabs" ) );
		$this->assertSame( 'notabs', $registry->getTemplate( 'key' ) );
	}

	public function testRemovesComments() {
		$registry = new TemplateRegistry( array(
			'key' => "no<!--[if IE]>IE<![endif]-->comments<!-- <div>\n</div> -->",
		) );
		$this->assertSame( 'nocomments', $registry->getTemplate( 'key' ) );
	}

	public function testGetTemplates() {
		$registry = new TemplateRegistry( array( 'key' => 'html' ) );
		$this->assertSame( array( 'key' => 'html' ), $registry->getTemplates() );
	}

	public function testGetKnownTemplate() {
		$registry = new TemplateRegistry( array( 'key' => 'html' ) );
		$this->assertSame( 'html', $registry->getTemplate( 'key' ) );
	}

	public function testGetUnknownTemplate() {
		$registry = new TemplateRegistry( array() );

		\MediaWiki\suppressWarnings();
		$html = $registry->getTemplate( 'unknown' );
		\MediaWiki\restoreWarnings();

		$this->assertNull( $html );
	}

}
