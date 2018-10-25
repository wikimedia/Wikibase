<?php

namespace Wikibase\Repo\ParserOutput;

use Language;
use OutOfBoundsException;
use Wikibase\DataModel\Entity\EntityDocument;
use Wikibase\LanguageFallbackChain;
use Wikibase\Lib\Store\EntityInfo;
use Wikibase\View\EntityDocumentView;
use Wikimedia\Assert\Assert;

/**
 * A factory to create EntityDocumentView implementations by entity type based on callbacks.
 *
 * @license GNU GPL v2+
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
class DispatchingEntityViewFactory {

	/**
	 * @var callable[]
	 */
	private $entityViewFactoryCallbacks;

	/**
	 * @param callable[] $entityViewFactoryCallbacks
	 */
	public function __construct( array $entityViewFactoryCallbacks ) {
		Assert::parameterElementType( 'callable', $entityViewFactoryCallbacks, '$entityViewFactoryCallbacks' );

		$this->entityViewFactoryCallbacks = $entityViewFactoryCallbacks;
	}

	/**
	 * Creates a new EntityDocumentView that can display the given type of entity.
	 *
	 * @param Language $language
	 * @param LanguageFallbackChain $fallbackChain
	 * @param EntityDocument $entity
	 * @param EntityInfo $entityInfo
	 *
	 * @throws OutOfBoundsException
	 * @return EntityDocumentView
	 */
	public function newEntityView(
		Language $language,
		LanguageFallbackChain $fallbackChain,
		EntityDocument $entity,
		EntityInfo $entityInfo
	) {
		$entityType = $entity->getType();
		if ( !isset( $this->entityViewFactoryCallbacks[$entityType] ) ) {
			throw new OutOfBoundsException( "No EntityDocumentView is registered for entity type '$entityType'" );
		}

		$entityView = call_user_func(
			$this->entityViewFactoryCallbacks[$entityType],
			$language,
			$fallbackChain,
			$entity,
			$entityInfo
		);

		Assert::postcondition(
			$entityView instanceof EntityDocumentView,
			'Callback must return an instance of EntityDocumentView'
		);

		return $entityView;
	}

}
