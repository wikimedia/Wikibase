<?php

namespace Wikibase\Lib\Store;

use OutOfBoundsException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\Term;
use Wikibase\TermIndex;

/**
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Daniel Kinzler
 */
class EntityTermLookup implements TermLookup, TermBuffer {

	/**
	 * @var TermIndex
	 */
	private $termIndex;

	/**
	 * @param TermIndex $termIndex
	 */
	public function __construct( TermIndex $termIndex ) {
		$this->termIndex = $termIndex;
	}

	/**
	 * @see TermLookup::getLabel
	 *
	 * @param EntityId $entityId
	 * @param string $languageCode
	 *
	 * @throws OutOfBoundsException if no such label was found
	 * @return string
	 */
	public function getLabel( EntityId $entityId, $languageCode ) {
		$labels = $this->getLabels( $entityId, array( $languageCode ) );

		if ( !isset( $labels[$languageCode] ) ) {
			throw new OutOfBoundsException( 'No label found for language ' . $languageCode );
		}

		return $labels[$languageCode];
	}

	/**
	 * @see TermLookup::getLabels
	 *
	 * @param EntityId $entityId
	 * @param string[]|null $languageCodes The languages to get terms for; null means all languages.
	 *
	 * @return string[]
	 */
	public function getLabels( EntityId $entityId, array $languageCodes = null ) {
		return $this->getTermsOfType( $entityId, 'label', $languageCodes );
	}

	/**
	 * @see TermLookup::getDescription
	 *
	 * @param EntityId $entityId
	 * @param string $languageCode
	 *
	 * @throws OutOfBoundsException if no such description was found
	 * @return string
	 */
	public function getDescription( EntityId $entityId, $languageCode ) {
		$descriptions = $this->getDescriptions( $entityId, array( $languageCode ) );

		if ( !isset( $descriptions[$languageCode] ) ) {
			throw new OutOfBoundsException( 'No description found for language ' . $languageCode );
		}

		return $descriptions[$languageCode];
	}

	/**
	 * @see TermLookup::getDescriptions
	 *
	 * @param EntityId $entityId
	 * @param string[]|null $languageCodes The languages to get terms for; null means all languages.
	 *
	 * @return string[]
	 */
	public function getDescriptions( EntityId $entityId, array $languageCodes = null ) {
		return $this->getTermsOfType( $entityId, 'description', $languageCodes );
	}

	/**
	 * @param EntityId $entityId
	 * @param string $termType
	 * @param string[]|null $languageCodes The languages to get terms for; null means all languages.
	 *
	 * @return string[]
	 */
	private function getTermsOfType( EntityId $entityId, $termType, array $languageCodes = null ) {
		$wikibaseTerms = $this->termIndex->getTermsOfEntity( $entityId, array( $termType ), $languageCodes );

		return $this->convertTermsToMap( $wikibaseTerms );
	}

	/**
	 * @param Term[] $wikibaseTerms
	 *
	 * @return string[] strings keyed by language code
	 */
	private function convertTermsToMap( array $wikibaseTerms ) {
		$terms = array();

		foreach( $wikibaseTerms as $wikibaseTerm ) {
			$languageCode = $wikibaseTerm->getLanguage();
			$terms[$languageCode] = $wikibaseTerm->getText();
		}

		return $terms;
	}

}
