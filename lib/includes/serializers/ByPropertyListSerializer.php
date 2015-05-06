<?php

namespace Wikibase\Lib\Serializers;

use InvalidArgumentException;
use Traversable;
use Wikibase\DataModel\ByPropertyIdGrouper;

/**
 * Serializer for Traversable objects that need to be grouped
 * per property id. Each element needs to have a getPropertyId method.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class ByPropertyListSerializer extends SerializerObject {

	const OPT_ADD_LOWER_CASE_KEYS = 'addLowerCaseKeys';

	/**
	 * @var string
	 */
	private $elementName;

	/**
	 * @var Serializer
	 */
	private $elementSerializer;

	/**
	 * @since 0.2
	 *
	 * @param string $elementName
	 * @param Serializer $elementSerializer
	 * @param SerializationOptions|null $options
	 */
	public function __construct(
		$elementName,
		Serializer $elementSerializer,
		SerializationOptions $options = null
	) {
		parent::__construct( $options );

		$this->elementName = $elementName;
		$this->elementSerializer = $elementSerializer;
	}

	/**
	 * @see ApiSerializer::getSerialized
	 *
	 * @since 0.2
	 *
	 * @param mixed $objects
	 *
	 * @return array
	 * @throws InvalidArgumentException
	 */
	public function getSerialized( $objects ) {
		if ( !( $objects instanceof Traversable ) ) {
			throw new InvalidArgumentException( 'ByPropertyListSerializer can only serialize Traversable objects' );
		}

		//NOTE: when changing the serialization structure, update docs/json.wiki too!

		$serialization = array();

		$byPropertyIdGrouper = new ByPropertyIdGrouper( $objects );

		foreach ( $byPropertyIdGrouper->getPropertyIds() as $propertyId ) {
			$serializedObjects = array();

			foreach ( $byPropertyIdGrouper->getByPropertyId( $propertyId ) as $object ) {
				$serializedObjects[] = $this->elementSerializer->getSerialized( $object );
			}

			$this->setIndexedTagName( $serializedObjects, $this->elementName );

			if ( $this->options->shouldIndexTags() ) {
				$serializedObjects['id'] = $propertyId->getSerialization();
				$serialization[] = $serializedObjects;
			}
			else {
				$key = $propertyId->getSerialization();

				if ( $this->getOptions()->shouldUseUpperCaseIdsAsKeys() ) {
					$key = strtoupper( $key );
					$serialization[$key] = $serializedObjects;
				}

				if ( $this->getOptions()->shouldUseLowerCaseIdsAsKeys() ) {
					$key = strtolower( $key );
					$serialization[$key] = $serializedObjects;
				}
			}
		}

		$this->setIndexedTagName( $serialization, 'property' );

		return $serialization;
	}

}
