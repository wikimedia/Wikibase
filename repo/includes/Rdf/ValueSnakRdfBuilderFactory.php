<?php

namespace Wikibase\Rdf;

use Wikimedia\Assert\Assert;
use Wikimedia\Purtle\RdfWriter;

/**
 * Factory for ValueSnakRdfBuilder based on factory callbacks.
 * For use with DataTypeDefinitions.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class ValueSnakRdfBuilderFactory {

	/**
	 * @var callable[]
	 */
	private $factoryCallbacks;

	/**
	 * @param callable[] $factoryCallbacks Factory callback functions as returned by
	 *        DataTypeDefinitions::getRdfBuilderFactoryCallbacks(). Callbacks will be invoked
	 *        with the signature ($mode, RdfVocabulary, EntityMentionListener) and must
	 *        return a ValueSnakRdfBuilder (or null).
	 */
	public function __construct( array $factoryCallbacks ) {
		Assert::parameterElementType( 'callable', $factoryCallbacks, '$factoryCallbacks' );

		$this->factoryCallbacks = $factoryCallbacks;
	}

	/**
	 * Returns an ValueSnakRdfBuilder for reified value output.
	 *
	 * @param                       $mode
	 * @param RdfVocabulary         $vocabulary
	 * @param RdfWriter             $writer
	 * @param EntityMentionListener $mentionedEntityTracker
	 * @param DedupeBag             $dedupe
	 * @return DispatchingValueSnakRdfBuilder
	 */
	public function getValueSnakRdfBuilder(
		$mode,
		RdfVocabulary $vocabulary,
		RdfWriter $writer,
		EntityMentionListener $mentionedEntityTracker,
		DedupeBag $dedupe
	) {
		$builders = $this->createValueSnakRdfBuilders(
			$mode,
			$vocabulary,
			$writer,
			$mentionedEntityTracker,
			$dedupe
		);

		return new DispatchingValueSnakRdfBuilder( $builders );
	}

	/**
	 * @param string $mode
	 * @param RdfVocabulary $vocabulary
	 * @param RdfWriter $writer
	 * @param EntityMentionListener $mentionedEntityTracker
	 * @param DedupeBag $dedupe
	 *
	 * @return ValueSnakRdfBuilder[]
	 */
	private function createValueSnakRdfBuilders(
		$mode,
		RdfVocabulary $vocabulary,
		RdfWriter $writer,
		EntityMentionListener $mentionedEntityTracker,
		DedupeBag $dedupe
	) {
		$builders = array();

		foreach ( $this->factoryCallbacks as $key => $callback ) {
			$builder = call_user_func(
				$callback,
				$mode,
				$vocabulary,
				$writer,
				$mentionedEntityTracker,
				$dedupe
			);

			if ( $builder instanceof ValueSnakRdfBuilder ) {
				$builders[$key] = $builder;
			}
		}

		return $builders;
	}

}
