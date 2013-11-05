<?php

namespace Wikibase\Test;

use Wikibase\Claim;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\Lib\Serializers\SerializationOptions;
use Wikibase\PropertyNoValueSnak;
use Wikibase\PropertySomeValueSnak;
use Wikibase\SnakList;
use Wikibase\Statement;
use Wikibase\Lib\Serializers\ClaimSerializer;
use Wikibase\Lib\Serializers\SnakSerializer;

/**
 * @covers Wikibase\Lib\Serializers\ClaimSerializer
 *
 * @since 0.2
 *
 * @group WikibaseLib
 * @group Wikibase
 * @group WikibaseSerialization
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author H. Snater < mediawiki@snater.com >
 */
class ClaimSerializerTest extends SerializerBaseTest {

	/**
	 * @see SerializerBaseTest::getClass
	 *
	 * @since 0.2
	 *
	 * @return string
	 */
	protected function getClass() {
		return '\Wikibase\Lib\Serializers\ClaimSerializer';
	}

	/**
	 * @see SerializerBaseTest::validProvider
	 *
	 * @since 0.2
	 *
	 * @return array
	 */
	public function validProvider() {
		$validArgs = array();

		$id = new PropertyId( 'P42' );

		$validArgs[] = new Claim( new PropertyNoValueSnak( $id ) );

		$validArgs[] = new Claim( new PropertySomeValueSnak( $id ) );

		$validArgs = $this->arrayWrap( $validArgs );

		$claim = new Claim( new PropertyNoValueSnak( $id ) );

		$validArgs[] = array(
			$claim,
			array(
				'id' => $claim->getGuid(),
				'mainsnak' => array(
					'snaktype' => 'novalue',
					'property' => 'P42',
				),
				'type' => 'claim',
			),
		);

		$statement = new Statement( new PropertyNoValueSnak( $id ) );

		$validArgs['statement'] = array(
			$statement,
			array(
				'id' => $statement->getGuid(),
				'mainsnak' => array(
					'snaktype' => 'novalue',
					'property' => 'P42',
				),
				'rank' => 'normal',
				'type' => 'statement',
			),
		);

		$claim = new Claim(
			new PropertyNoValueSnak( $id ),
			new SnakList( array(
				new PropertyNoValueSnak( $id ),
				new PropertySomeValueSnak( $id ),
				new PropertyNoValueSnak(
					new PropertyId( 'P1' )
				),
			) )
		);

		$snakSerializer = new SnakSerializer();

		$validArgs['complexClaimByProp'] = array(
			$claim,
			array(
				'id' => $claim->getGuid(),
				'mainsnak' => $snakSerializer->getSerialized( new PropertyNoValueSnak( $id ) ),
				'qualifiers' => array(
					'P42' => array(
						$snakSerializer->getSerialized( new PropertyNoValueSnak( $id ) ),
						$snakSerializer->getSerialized( new PropertySomeValueSnak( $id ) ),
					),
					'P1' => array(
						$snakSerializer->getSerialized( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) ),
					),
				),
				'qualifiers-order' => array( 'P42', 'P1' ),
				'type' => 'claim',
			),
		);

		$opts = new SerializationOptions();
		$opts->setOption( SerializationOptions::OPT_GROUP_BY_PROPERTIES, array() );

		$validArgs['complexClaimList'] = array(
			$claim,
			array(
				'id' => $claim->getGuid(),
				'mainsnak' => $snakSerializer->getSerialized( new PropertyNoValueSnak( $id ) ),
				'qualifiers' => array(
					$snakSerializer->getSerialized( new PropertyNoValueSnak( $id ) ),
					$snakSerializer->getSerialized( new PropertySomeValueSnak( $id ) ),
					$snakSerializer->getSerialized( new PropertyNoValueSnak( new PropertyId( 'P1' ) ) ),
				),
				'qualifiers-order' => array( 'P42', 'P1' ),
				'type' => 'claim',
			),
			$opts
		);

		return $validArgs;
	}

	public function rankProvider() {
		$ranks = array(
			Statement::RANK_NORMAL,
			Statement::RANK_PREFERRED,
			Statement::RANK_DEPRECATED,
		);

		return $this->arrayWrap( $ranks );
	}

	/**
	 * @dataProvider rankProvider
	 */
	public function testRankSerialization( $rank ) {
		$id = new PropertyId( 'P42' );
		$statement = new Statement( new PropertyNoValueSnak( $id ) );

		$statement->setRank( $rank );

		$serializer = new ClaimSerializer();

		$serialization = $serializer->getSerialized( $statement );

		$this->assertEquals(
			$rank,
			ClaimSerializer::unserializeRank( $serialization['rank'] ),
			'Roundtrip between rank serialization and unserialization'
		);
	}

}
