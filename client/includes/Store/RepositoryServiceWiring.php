<?php

use Wikibase\Client\ForbiddenSerializer;
use Wikibase\Client\Store\RepositoryServiceContainer;
use Wikibase\Client\WikibaseClient;
use Wikibase\Lib\Interactors\TermIndexSearchInteractorFactory;
use Wikibase\Lib\Store\EntityContentDataCodec;
use Wikibase\Lib\Store\Sql\PrefetchingWikiPageEntityMetaDataAccessor;
use Wikibase\Lib\Store\Sql\PropertyInfoTable;
use Wikibase\Lib\Store\Sql\WikiPageEntityMetaDataLookup;
use Wikibase\Lib\Store\WikiPageEntityRevisionLookup;
use Wikibase\Store\BufferingTermLookup;
use Wikibase\TermIndex;
use Wikibase\TermSqlIndex;

/**
 * @license GPL-2.0+
 */

return [

	'EntityRevisionLookup' => function(
		RepositoryServiceContainer $services,
		WikibaseClient $client
	) {
		$codec = new EntityContentDataCodec(
			$services->getEntityIdParser(),
			new ForbiddenSerializer( 'Entity serialization is not supported on the client!' ),
			$services->getEntityDeserializer(),
			$client->getSettings()->getSetting( 'maxSerializedEntitySize' ) * 1024
		);

		$metaDataLookup = new PrefetchingWikiPageEntityMetaDataAccessor(
			new WikiPageEntityMetaDataLookup(
				$client->getEntityNamespaceLookup(),
				$services->getDatabaseName(),
				$services->getRepositoryName()
			)
		);

		return new WikiPageEntityRevisionLookup(
			$codec,
			$metaDataLookup,
			$services->getDatabaseName()
		);
	},

	'PropertyInfoLookup' => function(
		RepositoryServiceContainer $services,
		WikibaseClient $client
	) {
		return new PropertyInfoTable(
			$client->getEntityIdComposer(),
			$services->getDatabaseName(),
			$services->getRepositoryName()
		);
	},

	'TermIndex' => function(
		RepositoryServiceContainer $services,
		WikibaseClient $client
	) {
		return new TermSqlIndex(
			$client->getStringNormalizer(),
			$client->getEntityIdComposer(),
			$services->getDatabaseName(),
			$services->getRepositoryName()
		);
	},

	'TermSearchInteractorFactory' => function(
		RepositoryServiceContainer $services,
		WikibaseClient $client
	) {
		/** @var TermIndex $termIndex */
		$termIndex = $services->getService( 'TermIndex' );
		return new TermIndexSearchInteractorFactory(
			$termIndex,
			$client->getLanguageFallbackChainFactory(),
			new BufferingTermLookup( $termIndex, 1000 ) // TODO: customize buffer sizes
		);
	},

];
