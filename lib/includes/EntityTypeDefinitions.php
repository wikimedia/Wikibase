<?php

namespace Wikibase\Lib;

use InvalidArgumentException;

/**
 * Service that manages entity type definition. This is a registry that provides access to factory
 * functions for various services associated with entity types, such as serializers.
 *
 * EntityTypeDefinitions provides a one-stop interface for defining entity types.
 * Each entity type is defined using a "entity type definition" array.
 *
 * The fields of a definition array can be seen in the follow doc file:
 * @see docs/entitytypes.wiki
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 * @author Thiemo Kreuz
 */
class EntityTypeDefinitions {

	/**
	 * @var array[]
	 */
	private $entityTypeDefinitions;

	/**
	 * @param array[] $entityTypeDefinitions Map from entity types to entity definitions
	 *        See class level documentation for details
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( array $entityTypeDefinitions ) {
		foreach ( $entityTypeDefinitions as $type => $def ) {
			if ( !is_string( $type ) || !is_array( $def ) ) {
				throw new InvalidArgumentException( '$entityTypeDefinitions must be a map from string to arrays' );
			}
		}

		$this->entityTypeDefinitions = $entityTypeDefinitions;
	}

	/**
	 * @return string[] a list of all defined entity types
	 */
	public function getEntityTypes() {
		return array_keys( $this->entityTypeDefinitions );
	}

	/**
	 * @param string $field
	 *
	 * @return array
	 */
	private function getMapForDefinitionField( $field ) {
		$fieldValues = [];

		foreach ( $this->entityTypeDefinitions as $type => $def ) {
			if ( isset( $def[$field] ) ) {
				$fieldValues[$type] = $def[$field];
			}
		}

		return $fieldValues;
	}

	/**
	 * @return callable[]
	 */
	public function getEntityStoreFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'entity-store-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getEntityRevisionLookupFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'entity-revision-lookup-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getEntityTitleStoreLookupFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'entity-title-store-lookup-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getSerializerFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'serializer-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getStorageSerializerFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'storage-serializer-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getDeserializerFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'deserializer-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getViewFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'view-factory-callback' );
	}

	/**
	 * @return string[]
	 */
	public function getContentModelIds() {
		return $this->getMapForDefinitionField( 'content-model-id' );
	}

	/**
	 * @return callable[]
	 */
	public function getContentHandlerFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'content-handler-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getEntityFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'entity-factory-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getEntityDifferStrategyBuilders() {
		return $this->getMapForDefinitionField( 'entity-differ-strategy-builder' );
	}

	/**
	 * @return callable[] An array mapping entity type identifiers
	 * to callables instantiating EntityDiffVisualizer objects
	 * Not guaranteed to contain all entity types.
	 */
	public function getEntityDiffVisualizerCallbacks() {
		return $this->getMapForDefinitionField( 'entity-diff-visualizer-callback' );
	}

	/**
	 * @return callable[]
	 */
	public function getEntityPatcherStrategyBuilders() {
		return $this->getMapForDefinitionField( 'entity-patcher-strategy-builder' );
	}

	/**
	 * @return string[]
	 */
	public function getJsDeserializerFactoryFunctions() {
		return $this->getMapForDefinitionField( 'js-deserializer-factory-function' );
	}

	/**
	 * @return callable[]
	 */
	public function getEntityIdBuilders() {
		$result = [];

		foreach ( $this->entityTypeDefinitions as $def ) {
			if ( isset( $def['entity-id-pattern'] ) && isset( $def['entity-id-builder'] ) ) {
				$result[$def['entity-id-pattern']] = $def['entity-id-builder'];
			}
		}

		return $result;
	}

	/**
	 * @return callable[] An array mapping entity type identifiers to callables capable of turning
	 *  unique parts of entity ID serializations into EntityId objects. Not guaranteed to contain
	 *  all entity types.
	 */
	public function getEntityIdComposers() {
		return $this->getMapForDefinitionField( 'entity-id-composer-callback' );
	}

	/**
	 * @return callable[] An array mapping entity type identifiers
	 * to callables instantiating ChangeOpDeserializer objects
	 * capable of turning serialization of the change (in array form)
	 * to a ChangeOp object representing the change.
	 * Not guaranteed to contain all entity types.
	 */
	public function getChangeOpDeserializerCallbacks() {
		return $this->getMapForDefinitionField( 'changeop-deserializer-callback' );
	}

	/**
	 * @return callable[] An array mapping entity type identifiers
	 * to callables instantiating EntityRdfBuilder objects
	 * Not guaranteed to contain all entity types.
	 */
	public function getRdfBuilderFactoryCallbacks() {
		return $this->getMapForDefinitionField( 'rdf-builder-factory-callback' );
	}

	/**
	 * @return callable[] An array mapping entity type identifiers
	 * to callables instantiating EntitySearchHelper objects
	 * Not guaranteed to contain all entity types.
	 */
	public function getEntitySearchHelperCallbacks() {
		return $this->getMapForDefinitionField( 'entity-search-callback' );
	}

	/**
	 * @return array An array mapping entity type identifiers to string[] of sub entity types.
	 * Not guaranteed to contain all entity types, will only contain entity types that have sub entities.
	 */
	public function getSubEntityTypes() {
		return $this->getMapForDefinitionField( 'sub-entity-types' );
	}

	/**
	 * @return callable[] An array mapping entity type identifiers
	 * to callables instantiating EntityLinkFormatter objects
	 * Not guaranteed to contain all entity types.
	 */
	public function getLinkFormatterCallbacks() {
		return $this->getMapForDefinitionField( 'link-formatter-callback' );
	}

	/**
	 * @return callable[] An array mapping entity type identifiers
	 * to callables instantiating EntityReferenceExtractor objects
	 * Not guaranteed to contain all entity types.
	 */
	public function getEntityReferenceExtractorCallbacks() {
		return $this->getMapForDefinitionField( 'entity-reference-extractor-callback' );
	}

	/**
	 * @return string[]|callable[] An array mapping entity type identifiers
	 * to fulltext search types or callables that return the search type as a string.
	 * Not guaranteed to contain all entity types.
	 */
	public function getFulltextSearchTypes() {
		return $this->getMapForDefinitionField( 'fulltext-search-context' );
	}

}
