<?php

namespace Wikibase\Repo\Tests\Rdf;

use Closure;
use PHPUnit_Framework_TestCase;
use Wikibase\Rdf\RdfProducer;
use Wikibase\Rdf\EntityRdfBuilder;
use Wikibase\Rdf\EntityRdfBuilderFactory;
use Wikibase\Rdf\RdfVocabulary;
use Wikibase\Rdf\NullEntityMentionListener;
use Wikibase\Rdf\NullDedupeBag;
use Wikimedia\Purtle\NTriplesRdfWriter;
use Wikimedia\Purtle\RdfWriter;
use Wikibase\Rdf\EntityMentionListener;
use Wikibase\Rdf\DedupeBag;

/**
 * @covers Wikibase\Rdf\EntityRdfBuilderFactory
 *
 * @group Wikibase
 * @group WikibaseRdf
 *
 * @license GPL-2.0+
 * @author Amir Sarabadani <ladsgroup@gmail.com>
 */
class EntityRdfBuilderFactoryTest extends PHPUnit_Framework_TestCase {

	public function getBuilderFlags() {
		return [
			[ 0 ], // simple values
			[ RdfProducer::PRODUCE_FULL_VALUES ], // complex values
		];
	}

	/**
	 * @dataProvider getBuilderFlags
	 */
	public function testGetEntityRdfBuilder( $flags ) {
		$vocab = new RdfVocabulary( RdfBuilderTestData::URI_BASE, RdfBuilderTestData::URI_DATA );
		$writer = new NTriplesRdfWriter();
		$tracker = new NullEntityMentionListener();
		$dedupe = new NullDedupeBag();
		$called = false;

		$constructor = $this->newRdfBuilderConstructorCallback(
			$flags, $vocab, $writer, $tracker, $dedupe, $called
		);

		$factory = new EntityRdfBuilderFactory( [ 'test' => $constructor ] );
		$factory->getEntityRdfBuilder( $flags, $vocab, $writer, $tracker, $dedupe );
		$this->assertTrue( $called );
	}

	/**
	 * Constructs a closure that asserts that it is being called with the expected parameters.
	 *
	 * @param int $expectedMode
	 * @param RdfVocabulary $expectedVocab
	 * @param RdfWriter $expectedWriter
	 * @param EntityMentionListener $expectedTracker
	 * @param DedupeBag $expectedDedupe
	 * @param bool &$called Will be set to true once the returned function has been called.
	 *
	 * @return Closure
	 */
	private function newRdfBuilderConstructorCallback(
		$expectedMode,
		RdfVocabulary $expectedVocab,
		RdfWriter $expectedWriter,
		EntityMentionListener $expectedTracker,
		DedupeBag $expectedDedupe,
		&$called
	) {
		$entityRdfBuilder = $this->getMock( EntityRdfBuilder::class );

		return [ function(
			$mode,
			RdfVocabulary $vocab,
			RdfWriter $writer,
			EntityMentionListener $tracker,
			DedupeBag $dedupe
		) use (
			$expectedMode,
			$expectedVocab,
			$expectedWriter,
			$expectedTracker,
			$expectedDedupe,
			$entityRdfBuilder,
			&$called
		) {
			$this->assertSame( $expectedMode, $mode );
			$this->assertSame( $expectedVocab, $vocab );
			$this->assertSame( $expectedWriter, $writer );
			$this->assertSame( $expectedTracker, $tracker );
			$this->assertSame( $expectedDedupe, $dedupe );
			$called = true;

			return $entityRdfBuilder;
		} ];
	}

}
