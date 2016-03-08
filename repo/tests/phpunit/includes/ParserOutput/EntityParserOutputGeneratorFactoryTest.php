<?php

namespace Wikibase\Repo\Tests\ParserOutput;

use Language;
use ParserOptions;
use Wikibase\Repo\WikibaseRepo;

/**
 * @covers Wikibase\Repo\ParserOutput\EntityParserOutputGeneratorFactory
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group Database
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class EntityParserOutputGeneratorFactoryTest extends \MediaWikiTestCase {

	public function testGetEntityParserOutputGenerator() {
		$parserOutputGeneratorFactory = $this->getEntityParserOutputGeneratorFactory();

		$testUser = new \TestUser( 'Wikibase User' );

		$instance = $parserOutputGeneratorFactory->getEntityParserOutputGenerator(
			'en', true
		);

		$this->assertInstanceOf( 'Wikibase\Repo\ParserOutput\EntityParserOutputGenerator', $instance );
	}

	private function getEntityParserOutputGeneratorFactory() {
		return WikibaseRepo::getDefaultInstance()->getEntityParserOutputGeneratorFactory();
	}

}
