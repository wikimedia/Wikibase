<?php

namespace Wikibase\Lib\Serializers;

use InvalidArgumentException;
use OutOfBoundsException;
use Wikibase\ReferenceList;
use Wikibase\Snak;
use Wikibase\SnakList;
use Wikibase\Statement;
use Wikibase\Claim;

/**
 * Serializer for Claim objects.
 *
 * See docs/json.wiki for details of the format.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ClaimSerializer extends SerializerObject implements Unserializer {

	/**
	 * @since 0.3
	 *
	 * @var string[]
	 */
	protected static $rankMap = array(
		Statement::RANK_DEPRECATED => 'deprecated',
		Statement::RANK_NORMAL => 'normal',
		Statement::RANK_PREFERRED => 'preferred',
	);

	/**
	 * Returns the available ranks in serialized form.
	 *
	 * @since 0.3
	 *
	 * @return string[]
	 */
	public static function getRanks() {
		return array_values( self::$rankMap );
	}

	/**
	 * Unserializes the rank and returns an element from the Statement::RANK_ enum.
	 * Roundtrips with @see ClaimSerializer::serializeRank
	 *
	 * @since 0.3
	 *
	 * @param string $serializedRank
	 *
	 * @return integer
	 */
	public static function unserializeRank( $serializedRank ) {
		$ranks = array_flip( self::$rankMap );
		return $ranks[$serializedRank];
	}

	/**
	 * Serializes the rank.
	 * Roundtrips with @see ClaimSerializer::unserializeRank
	 *
	 * @since 0.3
	 *
	 * @param integer $rank
	 *
	 * @return string
	 */
	public static function serializeRank( $rank ) {
		return self::$rankMap[$rank];
	}

	/**
	 * @see ApiSerializer::getSerialized
	 *
	 * @since 0.2
	 *
	 * @param mixed $claim
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function getSerialized( $claim ) {
		if ( !( $claim instanceof Claim ) ) {
			throw new InvalidArgumentException( 'ClaimSerializer can only serialize Claim objects' );
		}

		//NOTE: when changing the serialization structure, update docs/json.wiki too!

		$serialization['id'] = $claim->getGuid();

		$snakSerializer = new SnakSerializer( $this->options );
		$serialization['mainsnak'] = $snakSerializer->getSerialized( $claim->getMainSnak() );

		$snaksSerializer = new ByPropertyListSerializer( 'qualifiers', $snakSerializer, $this->options );
		$qualifiers = $snaksSerializer->getSerialized( $claim->getQualifiers() );

		if ( $qualifiers !== array() ) {
			$serialization['qualifiers'] = $qualifiers;

			$serialization['qualifiers-order'] = array();
			foreach( $claim->getQualifiers() as $snak ) {
				/** @var Snak $snak $id */
				$id = $snak->getPropertyId()->getPrefixedId();
				if( !in_array( $id, $serialization['qualifiers-order'] ) ) {
					$serialization['qualifiers-order'][] = $snak->getPropertyId()->getPrefixedId();
				}
			}
			$this->setIndexedTagName( $serialization['qualifiers-order'], 'property' );
		}

		$serialization['type'] = $claim instanceof Statement ? 'statement' : 'claim';

		if ( $claim instanceof Statement ) {
			$serialization['rank'] = self::$rankMap[ $claim->getRank() ];

			$referenceSerializer = new ReferenceSerializer( $this->options );

			$serialization['references'] = array();

			foreach ( $claim->getReferences() as $reference ) {
				$serialization['references'][] = $referenceSerializer->getSerialized( $reference );
			}

			if ( $serialization['references'] === array() ) {
				unset( $serialization['references'] );
			}
			else {
				$this->setIndexedTagName( $serialization['references'], 'reference' );
			}
		}

		return $serialization;
	}

	/**
	 * @see Unserializer::newFromSerialization
	 *
	 * @since 0.4
	 *
	 * @param array $serialization
	 *
	 * @return Claim
	 * @throws InvalidArgumentException
	 * @throws OutOfBoundsException
	 */
	public function newFromSerialization( array $serialization ) {
		if ( !array_key_exists( 'type', $serialization )
			|| !in_array( $serialization['type'], array( 'claim', 'statement' ) ) ) {
			throw new InvalidArgumentException( 'Invalid claim type specified' );
		}

		$requiredElements = array(
			'mainsnak',
		);

		$isStatement = $serialization['type'] === 'statement';

		if ( $isStatement ) {
			$requiredElements[] = 'rank';
		}

		foreach ( $requiredElements as $requiredElement ) {
			if ( !array_key_exists( $requiredElement, $serialization ) ) {
				throw new InvalidArgumentException( "Required key '$requiredElement' missing" );
			}
		}

		$snakUnserializer = new SnakSerializer(); // FIXME: derp injection

		$claimClass = $isStatement ? '\Wikibase\Statement' : '\Wikibase\Claim';

		/**
		 * @var Claim $claim
		 */
		$claim = new $claimClass( $snakUnserializer->newFromSerialization( $serialization['mainsnak'] ) );
		assert( $claim instanceof Claim );

		if( array_key_exists( 'id', $serialization ) ){
			$claim->setGuid( $serialization['id'] );
		}

		$claim->setQualifiers( $this->unserializeQualifiers( $serialization, $snakUnserializer ) );

		if ( $isStatement ) {
			if ( !in_array( $serialization['rank'], self::$rankMap ) ) {
				throw new InvalidArgumentException( 'Invalid statement rank provided' );
			}

			/**
			 * @var Statement $claim
			 */
			$claim->setRank( self::unserializeRank( $serialization['rank'] ) );

			if ( array_key_exists( 'references', $serialization ) ) {
				$references = array();

				$referenceUnserializer = new ReferenceSerializer();

				foreach ( $serialization['references'] as $referenceSerialization ) {
					$references[] = $referenceUnserializer->newFromSerialization( $referenceSerialization );
				}

				$claim->setReferences( new ReferenceList( $references ) );
			}
		}

		return $claim;
	}

	/**
	 * Deserializes qualifiers from a serialized claim.
	 *
	 * @since 0.4
	 *
	 * @param array $serialization
	 * @param SnakSerializer $snakUnserializer
	 * @return SnakList
	 * @throws OutOfBoundsException
	 */
	protected function unserializeQualifiers( $serialization, $snakUnserializer ) {
		if ( !array_key_exists( 'qualifiers', $serialization ) ) {
			return new SnakList();
		} else {
			$sortedQualifiers = array();

			if( !array_key_exists( 'qualifiers-order', $serialization ) ) {
				$sortedQualifiers = $serialization['qualifiers'];

			} else {
				foreach( $serialization['qualifiers-order'] as $propertyId ) {
					if( !isset( $serialization['qualifiers'][$propertyId] ) ) {
						throw new OutOfBoundsException( 'No snaks with property id "' . $propertyId . '" '
						. 'found in "qualifiers" parameter although specified in '
						. '"qualifiers-order"' );
					}

					$sortedQualifiers[$propertyId] = $serialization['qualifiers'][$propertyId];
				}

				$missingProperties = array_diff_key(
					$sortedQualifiers,
					$serialization['qualifiers']
				);

				if( count( $missingProperties ) > 0 ) {
					throw new OutOfBoundsException( 'Property ids ' . implode( ', ', $missingProperties )
					. ' have not been specified in "qualifiers-order"' );
				}
			}

			$snaksUnserializer = new ByPropertyListUnserializer( $snakUnserializer );
			return new SnakList( $snaksUnserializer->newFromSerialization( $sortedQualifiers ) );
		}
	}

}
