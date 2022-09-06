<?php

namespace Wikibase\Repo\SeaHorse;

use Wikibase\Lib\EntityTypeDefinitions as Def;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\DataModel\Serializers\SerializerFactory;

return [
	Def::CONTENT_MODEL_ID => SeaHorseSaddle::CONTENT_ID,
	Def::CONTENT_HANDLER_FACTORY_CALLBACK => function() {
		$services = \MediaWiki\MediaWikiServices::getInstance();
		return new Groom(
			SeaHorseSaddle::CONTENT_ID,
			null, // unused
			WikibaseRepo::getEntityContentDataCodec( $services ),
			WikibaseRepo::getEntityConstraintProvider( $services ),
			WikibaseRepo::getValidatorErrorLocalizer( $services ),
			WikibaseRepo::getEntityIdParser( $services ),
			WikibaseRepo::getFieldDefinitionsFactory( $services )
			->getFieldDefinitionsByType( SeaHorseSaddle::ENTITY_TYPE ),
			null
		);
	},
	Def::STORAGE_SERIALIZER_FACTORY_CALLBACK => function( SerializerFactory $serializerFactory ) {
		return new SeaHorseSerializer();
	},
	Def::ENTITY_DIFFER_STRATEGY_BUILDER => static function () {
		return new SeaHorseDiffer();
	},
];
