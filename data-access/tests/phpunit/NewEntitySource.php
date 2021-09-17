<?php
declare( strict_types=1 );

namespace Wikibase\DataAccess\Tests;

use Wikibase\DataAccess\ApiEntitySource;
use Wikibase\DataAccess\DatabaseEntitySource;
use Wikibase\DataAccess\EntitySource;

/**
 * Convenience builder for EntitySource objects in tests
 *
 * @license GPL-2.0-or-later
 */
class NewEntitySource {

	/** @var string */
	private $name;

	/** @var string|false */
	private $dbName;

	/**
	 * @var array Associative array indexed by entity type (string), values are array of form [ 'namespaceId' => int, 'slot' => string ]
	 */
	private $entityNamespaceIdsAndSlots;

	/** @var string */
	private $conceptBaseUri;

	/** @var string */
	private $rdfNodeNamespacePrefix;

	/** @var string */
	private $rdfPredicateNamespacePrefix;

	/** @var string */
	private $interwikiPrefix;

	/** @var string */
	private $type;

	/** @var array */
	private $entityTypes;

	public static function create(): self {
		return new self();
	}

	public static function havingName( string $name ): self {
		return self::create()->withName( $name );
	}

	public function withName( string $name ): self {
		$result = clone $this;
		$result->name = $name;

		return $result;
	}

	public function withDbName( string $db ): self {
		$result = clone $this;
		$result->dbName = $db;

		return $result;
	}

	public function withEntityTypes( array $entityTypes ): self {
		if ( $this->type === null ) {
			$this->type = ApiEntitySource::TYPE;
		}
		$result = clone $this;
		$result->entityTypes = $entityTypes;

		return $result;
	}

	public function withEntityNamespaceIdsAndSlots( array $namespaceIdsAndSlots ): self {
		$result = clone $this;
		$result->entityNamespaceIdsAndSlots = $namespaceIdsAndSlots;

		return $result;
	}

	public function withConceptBaseUri( string $conceptBaseUri ): self {
		$result = clone $this;
		$result->conceptBaseUri = $conceptBaseUri;

		return $result;
	}

	public function withRdfNodeNamespacePrefix( string $prefix ): self {
		$result = clone $this;
		$result->rdfNodeNamespacePrefix = $prefix;

		return $result;
	}

	public function withRdfPredicateNamespacePrefix( string $prefix ): self {
		$result = clone $this;
		$result->rdfPredicateNamespacePrefix = $prefix;

		return $result;
	}

	public function withInterwikiPrefix( string $prefix ): self {
		$result = clone $this;
		$result->interwikiPrefix = $prefix;

		return $result;
	}

	public function withType( string $type ): self {
		$result = clone $this;
		$result->type = $type;

		return $result;
	}

	public function build(): EntitySource {
		if ( $this->type === ApiEntitySource::TYPE ) {
			return new ApiEntitySource(
				$this->name ?? '',
				$this->entityTypes ?? [ 'item', 'property', 'lexeme' ],
				$this->conceptBaseUri ?? $this->makeRandomUri(),
				$this->rdfNodeNamespacePrefix ?? '',
				$this->rdfPredicateNamespacePrefix ?? '',
				$this->interwikiPrefix ?? ''
			);
		}
		return new DatabaseEntitySource(
			$this->name ?? '',
			$this->dbName ?? false,
			$this->entityNamespaceIdsAndSlots ?? [],
			$this->conceptBaseUri ?? $this->makeRandomUri(),
			$this->rdfNodeNamespacePrefix ?? '',
			$this->rdfPredicateNamespacePrefix ?? '',
			$this->interwikiPrefix ?? ''
		);
	}

	private function makeRandomUri(): string {
		return 'http://my-random-uri-' . mt_rand() . '.org/entity/';
	}

}
