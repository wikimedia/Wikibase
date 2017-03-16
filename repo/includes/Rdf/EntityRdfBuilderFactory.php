<?php

namespace Wikibase\Rdf;

use SiteList;
use Wikimedia\Assert\Assert;
use Wikimedia\Purtle\RdfWriter;

/**
 * Factory for EntityRdfBuilder based on factory callbacks.
 * For use with EntityTypeDefinitions.
 *
 * @license GPL-2.0+
 * @author Amir Sarabadani <ladsgroup@gmail.com>
 */
class EntityRdfBuilderFactory {

	/**
	 * @var callable[]
	 */
	private $factoryCallbacks;

	/**
	 * @param callable[] $factoryCallbacks Factory callback functions as returned by
	 *        EntityTypeDefinitions::getRdfBuilderFactoryCallbacks(). Callbacks will be invoked
	 *        with the signature ($mode, RdfVocabulary, RdfWrite, EntityMentionListener, DedupeBag)
	 *        and must return a EntityRdfBuilder (or null).
	 */
	public function __construct( array $factoryCallbacks ) {
		Assert::parameterElementType( 'callable', $factoryCallbacks, '$factoryCallbacks' );

		$this->factoryCallbacks = $factoryCallbacks;
	}

	/**
	 * Returns an associative array mapping entity type to EntityRdfBuilder implementations
	 *
	 * @param int $flavorFlags Flavor flags to use for the entity rdf builder
	 * @param RdfVocabulary $vocabulary
	 * @param RdfWriter $writer
	 * @param EntityMentionListener $mentionedEntityTracker
	 * @param DedupeBag $dedupe
	 * @param SiteList $sites
	 * @return EntityRdfBuilder[] array mapping entity types to their EntityRdfBuilders
	 */
	public function getEntityRdfBuilders(
		$flavorFlags,
		RdfVocabulary $vocabulary,
		RdfWriter $writer,
		EntityMentionListener $mentionedEntityTracker,
		DedupeBag $dedupe,
		SiteList $sites
	) {
		return $this->createEntityRdfBuilders(
			$flavorFlags,
			$vocabulary,
			$writer,
			$mentionedEntityTracker,
			$dedupe,
			$sites
		);

	}

	/**
	 * @param int $flavorFlags Flavor flags to use for the entity rdf builder
	 * @param RdfVocabulary $vocabulary
	 * @param RdfWriter $writer
	 * @param EntityMentionListener $mentionedEntityTracker
	 * @param DedupeBag $dedupe
	 *
	 * @return EntityRdfBuilder[] array mapping entity types to their EntityRdfBuilders
	 */
	private function createEntityRdfBuilders(
		$flavorFlags,
		RdfVocabulary $vocabulary,
		RdfWriter $writer,
		EntityMentionListener $mentionedEntityTracker,
		DedupeBag $dedupe,
		SiteList $sites
	) {
		$builders = [];

		foreach ( $this->factoryCallbacks as $key => $callback ) {
			$builders[$key] = call_user_func(
				$callback,
				$flavorFlags,
				$vocabulary,
				$writer,
				$mentionedEntityTracker,
				$dedupe,
				$sites
			);

			Assert::postcondition(
				$builders[$key] instanceof EntityRdfBuilder,
				"builder for $key is not an EntityRdfBuilder"
			);
		}

		return $builders;
	}

}
