<?php

namespace Wikibase\Lib\Store\Sql\Terms;

use MediaWiki\Storage\NameTableStore;
use MediaWiki\Storage\NameTableAccessException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WANObjectCache;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * An acquirer and resolver for term type IDs implemented using a NameTableStore for wbt_type.
 *
 * @license GPL-2.0-or-later
 */
class DatabaseTypeIdsStore implements TypeIdsAcquirer, TypeIdsResolver, TypeIdsLookup {

	/** @var NameTableStore */
	private $nameTableStore;

	public function __construct(
		ILoadBalancer $loadBalancer,
		WANObjectCache $cache,
		$repoDbDomain = false,
		LoggerInterface $logger = null
	) {
		$this->nameTableStore = new NameTableStore(
			$loadBalancer,
			$cache,
			$logger ?: new NullLogger(),
			'wbt_type',
			'wby_id',
			'wby_name',
			null,
			$repoDbDomain
		);
	}

	public function acquireTypeIds( array $types ): array {
		$typeIds = [];
		foreach ( $types as $typeName ) {
			$typeIds[$typeName] = $this->nameTableStore->acquireId( $typeName );
		}
		return $typeIds;
	}

	public function resolveTypeIds( array $typeIds ): array {
		$typeNames = [];
		foreach ( $typeIds as $typeId ) {
			$typeNames[$typeId] = $this->nameTableStore->getName( $typeId );
		}
		return $typeNames;
	}

	/**
	 * {@inheritdoc}
	 * Unknown types will be associated with null in the value
	 */
	public function lookupTypeIds( array $types ): array {
		$typeIds = [];

		foreach ( $types as $type ) {
			try {
				$typeIds[$type] = $this->nameTableStore->getId( $type );
			} catch ( NameTableAccessException $ex ) {
				$typeIds[$type] = null;
			}
		}

		return $typeIds;
	}

}
