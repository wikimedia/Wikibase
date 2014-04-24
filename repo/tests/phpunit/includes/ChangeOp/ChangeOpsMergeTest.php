<?php

namespace Wikibase\Test;

use ValueValidators\Error;
use Wikibase\ChangeOp\ChangeOpFactoryProvider;
use Wikibase\ChangeOp\ChangeOpsMerge;
use Wikibase\DataModel\Entity\Item;
use Wikibase\DataModel\Entity\ItemId;
use Wikibase\DataModel\Internal\ObjectComparer;

/**
 * @covers Wikibase\ChangeOp\ChangeOpsMerge
 *
 * @group Wikibase
 * @group WikibaseRepo
 * @group ChangeOp
 *
 * @licence GNU GPL v2+
 * @author Adam Shorland
 */
class ChangeOpsMergeTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var ChangeOpTestMockProvider
	 */
	protected $mockProvider;

	/**
	 * @param string|null $name
	 * @param array $data
	 * @param string $dataName
	 */
	public function __construct( $name = null, array $data = array(), $dataName = '' ) {
		parent::__construct( $name, $data, $dataName );

		$this->mockProvider = new ChangeOpTestMockProvider( $this );
	}

	protected function makeChangeOpsMergeItems(
		Item $fromItem,
		Item $toItem,
		$ignoreConflicts,
		array $termConflicts,
		array $linkConflicts
	) {
		$duplicateDetector = $this->mockProvider->getMockLabelDescriptionDuplicateDetector( $termConflicts );
		$linkCache = $this->mockProvider->getMockSitelinkCache( $linkConflicts );

		$changeOpFactoryProvider =  new ChangeOpFactoryProvider(
			$duplicateDetector,
			$linkCache,
			$this->mockProvider->getMockGuidGenerator(),
			$this->mockProvider->getMockGuidValidator(),
			$this->mockProvider->getMockGuidParser( $toItem->getId() ),
			$this->mockProvider->getMockSnakValidator()
		);

		return new ChangeOpsMerge(
			$fromItem,
			$toItem,
			$ignoreConflicts,
			$duplicateDetector,
			$linkCache,
			$changeOpFactoryProvider
		);
	}

	/**
	 * @dataProvider provideValidConstruction
	 */
	public function testCanConstruct( $from, $to, $ignoreConflicts ) {
		$changeOps = $this->makeChangeOpsMergeItems(
			$from,
			$to,
			$ignoreConflicts,
			array(),
			array()
		);
		$this->assertInstanceOf( '\Wikibase\ChangeOp\ChangeOpsMergeItems', $changeOps );
	}

	public static function provideValidConstruction() {
		$from = self::getItem( 'Q111' );
		$to = self::getItem( 'Q222' );
		return array(
			array( $from, $to, array() ),
			array( $from, $to, array( 'label' ) ),
			array( $from, $to, array( 'description' ) ),
			array( $from, $to, array( 'description', 'label' ) ),
			array( $from, $to, array( 'description', 'label', 'sitelink' ) ),
		);
	}

	/**
	 * @dataProvider provideInvalidConstruction
	 */
	public function testInvalidIgnoreConflicts( $from, $to, $ignoreConflicts ) {
		$this->setExpectedException( 'InvalidArgumentException' );
		$this->makeChangeOpsMergeItems(
			$from,
			$to,
			$ignoreConflicts,
			array(),
			array()
		);
	}

	public static function provideInvalidConstruction() {
		$from = self::getItem( 'Q111' );
		$to = self::getItem( 'Q222' );
		return array(
			array( $from, $to, 'foo' ),
			array( $from, $to, array( 'foo' ) ),
			array( $from, $to, array( 'label', 'foo' ) ),
			array( $from, $to, null ),
		);
	}

	/**
	 * @param string $id
	 * @param array $data
	 *
	 * @return Item
	 */
	public static function getItem( $id, $data = array() ) {
		$item = new Item( $data );
		$item->setId( new ItemId( $id ) );
		return $item;
	}

	/**
	 * @dataProvider provideData
	 */
	public function testCanApply( $fromData, $toData, $expectedFromData, $expectedToData, $ignoreConflicts = array() ) {
		$from = self::getItem( 'Q111', $fromData );
		$to = self::getItem( 'Q222', $toData );
		$changeOps = $this->makeChangeOpsMergeItems(
			$from,
			$to,
			$ignoreConflicts,
			array(),
			array()
		);

		$this->assertTrue( $from->equals( new Item( $fromData ) ), 'FromItem was not filled correctly' );
		$this->assertTrue( $to->equals( new Item( $toData ) ), 'ToItem was not filled correctly' );

		$changeOps->apply();


		$fromData = $from->toArray();
		$toData = $to->toArray();

		//Cycle through the old claims and set the guids to null (we no longer know what they should be)
		$fromClaims = array();
		foreach( $fromData['claims'] as $claim ) {
			unset( $claim['g'] );
			$fromClaims[] = $claim;
		}

		$toClaims = array();
		foreach( $toData['claims'] as $claim ) {
			unset( $claim['g'] );
			$toClaims[] = $claim;
		}

		$fromData['claims'] = $fromClaims;
		$toData['claims'] = $toClaims;

		$fromData = array_intersect_key( $fromData, $expectedFromData );
		$toData = array_intersect_key( $toData, $expectedToData );

		$comparer = new ObjectComparer();
		$this->assertTrue( $comparer->dataEquals( $expectedFromData, $fromData, array( 'entity' ) ) );
		$this->assertTrue( $comparer->dataEquals( $expectedToData, $toData, array( 'entity' ) ) );
	}

	/**
	 * @return array 1=>fromData 2=>toData 3=>expectedFromData 4=>expectedToData
	 */
	public static function provideData() {
		$testCases = array();
		$testCases['labelMerge'] = array(
			array( 'label' => array( 'en' => 'foo' ) ),
			array(),
			array(),
			array( 'label' => array( 'en' => 'foo' ) ),
		);
		$testCases['identicalLabelMerge'] = array(
			array( 'label' => array( 'en' => 'foo' ) ),
			array( 'label' => array( 'en' => 'foo' ) ),
			array(),
			array( 'label' => array( 'en' => 'foo' ) ),
		);
		$testCases['ignoreConflictLabelMerge'] = array(
			array( 'label' => array( 'en' => 'foo' ) ),
			array( 'label' => array( 'en' => 'bar' ) ),
			array( 'label' => array( 'en' => 'foo' ) ),
			array( 'label' => array( 'en' => 'bar' ) ),
			array( 'label' )
		);
		$testCases['descriptionMerge'] = array(
			array( 'description' => array( 'en' => 'foo' ) ),
			array(),
			array(),
			array( 'description' => array( 'en' => 'foo' ) ),
		);
		$testCases['identicalDescriptionMerge'] = array(
			array( 'description' => array( 'en' => 'foo' ) ),
			array( 'description' => array( 'en' => 'foo' ) ),
			array(),
			array( 'description' => array( 'en' => 'foo' ) ),
		);
		$testCases['ignoreConflictDescriptionMerge'] = array(
			array( 'description' => array( 'en' => 'foo' ) ),
			array( 'description' => array( 'en' => 'bar' ) ),
			array( 'description' => array( 'en' => 'foo' ) ),
			array( 'description' => array( 'en' => 'bar' ) ),
			array( 'description' )
		);
		$testCases['aliasMerge'] = array(
			array( 'aliases' => array( 'en' => array( 'foo', 'bar' ) ) ),
			array(),
			array(),
			array( 'aliases' => array( 'en' =>  array( 'foo', 'bar' ) ) ),
		);
		$testCases['duplicateAliasMerge'] = array(
			array( 'aliases' => array( 'en' => array( 'foo', 'bar' ) ) ),
			array( 'aliases' => array( 'en' => array( 'foo', 'bar', 'baz' ) ) ),
			array(),
			array( 'aliases' => array( 'en' =>  array( 'foo', 'bar', 'baz' ) ) ),
		);
		$testCases['linkMerge'] = array(
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
			array(),
			array(),
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
		);
		$testCases['ignoreConflictLinkMerge'] = array(
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
			array( 'links' => array( 'enwiki' => array( 'name' => 'bar', 'badges' => array() ) ) ),
			array( 'links' => array( 'enwiki' => array( 'name' => 'foo', 'badges' => array() ) ) ),
			array( 'links' => array( 'enwiki' => array( 'name' => 'bar', 'badges' => array() ) ) ),
			array( 'sitelink' ),
		);
		$testCases['claimMerge'] = array(
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( ),
					'g' => 'Q111$D8404CDA-25E4-4334-AF13-A390BCD9C556' )
			),
			),
			array(),
			array(),
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( ) )
			),
			),
		);
		$testCases['claimWithQualifierMerge'] = array(
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( array(  'novalue', 56  ) ),
					'g' => 'Q111$D8404CDA-25E4-4334-AF13-A3290BCD9C0F' )
			),
			),
			array(),
			array(),
			array( 'claims' => array(
				array(
					'm' => array( 'novalue', 56 ),
					'q' => array( array(  'novalue', 56  ) ) )
			),
			),
		);
		$testCases['itemMerge'] = array(
			array(
				'label' => array( 'en' => 'foo', 'pt' => 'ptfoo' ),
				'description' => array( 'en' => 'foo', 'pl' => 'pldesc'  ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array( 'dewiki' => array( 'name' => 'foo', 'badges' => array() ) ),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ),
						'g' => 'Q111$D8404CDA-25E4-4334-AF88-A3290BCD9C0F' )
				),
			),
			array(),
			array(),
			array(
				'label' => array( 'en' => 'foo', 'pt' => 'ptfoo'  ),
				'description' => array( 'en' => 'foo', 'pl' => 'pldesc' ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array( 'dewiki' => array( 'name' => 'foo', 'badges' => array() ) ),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ) )
				),
			),
		);
		$testCases['ignoreConflictItemMerge'] = array(
			array(
				'label' => array( 'en' => 'foo', 'pt' => 'ptfoo' ),
				'description' => array( 'en' => 'foo', 'pl' => 'pldesc'  ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array(
					'dewiki' => array( 'name' => 'foo', 'badges' => array() ),
					'plwiki' => array( 'name' => 'bar', 'badges' => array() ),
				),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ),
						'g' => 'Q111$D8404CDA-25E4-4334-AF88-A3290BCD9C0F' )
				),
			),
			array(
				'label' => array( 'en' => 'toLabel' ),
				'description' => array( 'pl' => 'toLabel' ),
				'links' => array( 'plwiki' => array( 'name' => 'toLink', 'badges' => array() ) ),
			),
			array(
				'label' => array( 'en' => 'foo' ),
				'description' => array( 'pl' => 'pldesc' ),
				'links' => array( 'plwiki' => array( 'name' => 'bar', 'badges' => array() ) ),
			),
			array(
				'label' => array( 'en' => 'toLabel', 'pt' => 'ptfoo'  ),
				'description' => array( 'en' => 'foo', 'pl' => 'toLabel' ),
				'aliases' => array( 'en' => array( 'foo', 'bar' ), 'de' => array( 'defoo', 'debar' ) ),
				'links' => array(
					'dewiki' => array( 'name' => 'foo', 'badges' => array() ),
					'plwiki' => array( 'name' => 'toLink', 'badges' => array() ),
				),
				'claims' => array(
					array(
						'm' => array( 'novalue', 88 ),
						'q' => array( array(  'novalue', 88  ) ) )
				),
			),
			array( 'label', 'description', 'sitelink' )
		);
		return $testCases;
	}

	public function testExceptionThrownWhenLabelDescriptionDuplicatesDetected() {
		$conflicts = array( Error::newError( 'Foo!', 'label', 'foo', array( 'imatype', 'imalang', 'foog text', 'Q999' ) ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMergeItems(
			$from,
			$to,
			array(),
			$conflicts,
			array()
		);

		$this->setExpectedException(
			'\Wikibase\ChangeOp\ChangeOpException',
			'Item being merged to has conflicting terms: (Q999 => imalang => imatype => foog text)'
		);
		$changeOps->apply();
	}

	public function testExceptionNotThrownWhenLabelDescriptionDuplicatesDetectedOnFromItem() {
		$conflicts = array( Error::newError( 'Foo!', 'label', 'foo', array( 'imatype', 'imalang', 'foog text', 'Q111' ) ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMergeItems(
			$from,
			$to,
			array(),
			$conflicts,
			array()
		);

		$changeOps->apply();
		$this->assertTrue( true ); // no exception thrown
	}

	public function testExceptionThrownWhenSitelinkDuplicatesDetected() {
		$conflicts = array( array( 'itemId' => 8888, 'siteId' => 'eewiki', 'sitePage' => 'imapage' ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMergeItems(
			$from,
			$to,
			array(),
			array(),
			$conflicts
		);

		$this->setExpectedException(
			'\Wikibase\ChangeOp\ChangeOpException',
			'Item being merged to has conflicting terms: (Q8888 => eewiki => imapage)'
		);
		$changeOps->apply();
	}

	public function testExceptionNotThrownWhenSitelinkDuplicatesDetectedOnFromItem() {
		$conflicts = array( array( 'itemId' => 111, 'siteId' => 'eewiki', 'sitePage' => 'imapage' ) );
		$from = self::getItem( 'Q111', array() );
		$to = self::getItem( 'Q222', array() );
		$changeOps = $this->makeChangeOpsMergeItems(
			$from,
			$to,
			array(),
			array(),
			$conflicts
		);

		$changeOps->apply();
		$this->assertTrue( true ); // no exception thrown
	}

}
