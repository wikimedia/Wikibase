<?php

namespace Wikibase\Lib;

use InvalidArgumentException;
use RuntimeException;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;
use Wikibase\EntityTitleLookup;

/**
 * Formats entity IDs by generating the corresponding page title.
 *
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class EntityIdTitleFormatter extends EntityIdFormatter {

	/**
	 * @var EntityTitleLookup
	 */
	protected $titleLookup;

	/**
	 * @since 0.4
	 *
	 * @param FormatterOptions $options
	 * @param EntityTitleLookup $titleLookup
	 *
	 * @internal param \Wikibase\EntityLookup $entityLookup
	 *
	 */
	public function __construct( FormatterOptions $options, EntityTitleLookup $titleLookup ) {
		parent::__construct( $options );

		$this->titleLookup = $titleLookup;
	}

	/**
	 * Format an EntityId data value
	 *
	 * @since 0.4
	 *
	 * @param EntityId|EntityIdValue $value The value to format
	 *
	 * @return string
	 *
	 * @throws RuntimeException
	 * @throws InvalidArgumentException
	 */
	public function format( $value ) {
		if ( $value instanceof EntityIdValue ) {
			$value = $value->getEntityId();
		}

		if ( !( $value instanceof EntityId ) ) {
			throw new InvalidArgumentException( 'Data value type mismatch. Expected an EntityId or EntityIdValue.' );
		}

		$title = $this->titleLookup->getTitleForId( $value );
		return $title->getFullText();
	}

}

