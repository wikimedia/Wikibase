<?php

namespace Wikibase\Lib;

use InvalidArgumentException;
use ValueFormatters\FormatterOptions;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\EntityIdValue;

/**
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Thiemo Mättig
 */
class EntityIdFormatter {

	const OPT_LANG = 'lang';

	/**
	 * @var FormatterOptions
	 */
	private $options;

	/**
	 * @param FormatterOptions $options
	 */
	public function __construct( FormatterOptions $options ) {
		$this->options = $options;

		$this->options->defaultOption( self::OPT_LANG, 'en' );
	}

	/**
	 * Shortcut to $this->options->getOption.
	 *
	 * @param string $option
	 */
	protected final function getOption( $option ) {
		return $this->options->getOption( $option );
	}

	/**
	 * Shortcut to $this->options->defaultOption.
	 *
	 * @param string $option
	 * @param mixed $default
	 */
	protected final function defaultOption( $option, $default ) {
		$this->options->defaultOption( $option, $default );
	}

	/**
	 * Format an EntityId data value
	 *
	 * @since 0.4
	 *
	 * @param EntityId|EntityIdValue $value
	 *
	 * @throws InvalidArgumentException
	 * @return string
	 */
	public function format( $value ) {
		if ( $value instanceof EntityIdValue ) {
			$value = $value->getEntityId();
		}

		if ( !( $value instanceof EntityId ) ) {
			throw new InvalidArgumentException( '$value must be an EntityId or EntityIdValue' );
		}

		return $this->formatEntityId( $value );
	}

	/**
	 * @since 0.5
	 *
	 * @param EntityId $entityId
	 *
	 * @return string
	 */
	protected function formatEntityId( EntityId $entityId ) {
		return $entityId->getSerialization();
	}

}
