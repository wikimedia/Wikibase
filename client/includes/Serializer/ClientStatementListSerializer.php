<?php

namespace Wikibase\Client\Serializer;

use Serializers\Exceptions\SerializationException;
use Serializers\Serializer;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Statement\StatementList;

/**
 * @license GPL-2.0+
 * @author eranroz
 */
class ClientStatementListSerializer extends ClientSerializer {

	/**
	 * @var Serializer
	 */
	private $statementListSerializer;

	/**
	 * @param Serializer $statementListSerializer
	 * @param PropertyDataTypeLookup $dataTypeLookup
	 */
	public function __construct(
		Serializer $statementListSerializer,
		PropertyDataTypeLookup $dataTypeLookup
	) {
		parent::__construct( $dataTypeLookup );

		$this->statementListSerializer = $statementListSerializer;
	}

	/**
	 * Adds data types to serialization
	 *
	 * @param StatementList $statementList
	 *
	 * @throws SerializationException
	 * @return array
	 */
	public function serialize( $statementList ) {
		$serialization = $this->statementListSerializer->serialize( $statementList );

		$serialization = $this->injectSerializationWithDataTypes( $serialization, '' );

		return $this->omitEmptyArrays( $serialization );
	}

}
