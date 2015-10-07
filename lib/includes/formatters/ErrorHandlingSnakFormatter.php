<?php

namespace Wikibase\Lib\Formatters;

use DataValues\DataValue;
use DataValues\UnDeserializableValue;
use Html;
use InvalidArgumentException;
use Language;
use ValueFormatters\Exceptions\MismatchingDataValueTypeException;
use ValueFormatters\FormatterOptions;
use ValueFormatters\FormattingException;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookupException;
use Wikibase\DataModel\Snak\PropertyValueSnak;
use Wikibase\DataModel\Snak\Snak;
use Wikibase\Lib\SnakFormatter;

/**
 * Decorator for SnakFormatter that handles PropertyDataTypeLookupException and
 * MismatchingDataValueTypeException by placing the appropriate warning in the
 * snakFormatter's output. A fallback ValueFormatter can be used to provide basic
 * formatting for values in the presence of snak formatting errors.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class ErrorHandlingSnakFormatter implements SnakFormatter {

	/**
	 * @var SnakFormatter
	 */
	private $snakFormatter;

	/**
	 * @var ValueFormatter|null
	 */
	private $fallbackFormatter;

	/**
	 * @var string|null
	 */
	private $language;

	/**
	 * @param SnakFormatter $snakFormatter
	 * @param ValueFormatter|null $fallbackFormatter
	 * @param string|null $language
	 */
	public function __construct(
		SnakFormatter $snakFormatter,
		ValueFormatter $fallbackFormatter = null,
		$language = null
	) {
		$this->snakFormatter = $snakFormatter;
		$this->fallbackFormatter = $fallbackFormatter;
		$this->language = $language;
	}

	/**
	 * Formats the given Snak by looking up its property type and calling the
	 * SnakValueFormatter supplied to the constructor.
	 *
	 * @param Snak $snak
	 *
	 * @throws InvalidArgumentException
	 * @throws FormattingException
	 * @return string Text in the format indicated by getFormat()
	 */
	public function formatSnak( Snak $snak ) {
		try {
			return $this->snakFormatter->formatSnak( $snak );
		} catch ( MismatchingDataValueTypeException $ex ) {
			if ( $ex->getDataValueType() === UnDeserializableValue::getType() ) {
				$warningText = $this->formatWarning( 'wikibase-undeserializable-value' );
			} else {
				$warningText = $this->formatWarning(
					'wikibase-snakformatter-valuetype-mismatch',
					$ex->getDataValueType(),
					$ex->getExpectedValueType()
				);
			}
		} catch ( PropertyDataTypeLookupException $ex ) {
			// @todo: PropertyDataTypeLookupException should be wrapped in a FormatterException
			$warningText = $this->formatWarning(
				'wikibase-snakformatter-property-not-found',
				$snak->getPropertyId()->getSerialization()
			);
		}

		if ( $snak instanceof PropertyValueSnak && $this->fallbackFormatter ) {
			$value = $snak->getDataValue();
			$valueText = $this->fallbackFormatter->format( $value );

			if ( $valueText !== '' ) {
				return $valueText . ' ' . $warningText;
			}
		}

		return $warningText;
	}

	/**
	 * @param string $key
	 *
	 * @return string Formatted warning, in the format specified by getFormat()
	 */
	private function formatWarning( $key ) {
		$args = func_get_args();
		array_shift( $args );

		$warning = wfMessage( $key, $args );

		if ( $this->language !== null ) {
			$warning->inLanguage( $this->language );
		}

		$attributes = array( 'class' => 'error wb-format-error' );

		$format = $this->getFormat();

		//NOTE: format identifiers are MIME types, so we can just check the prefix.
		if ( strpos( $format, SnakFormatter::FORMAT_HTML ) === 0 ) {
			$text = $warning->parse();
			$text = Html::rawElement( 'span', $attributes, $text );

		} elseif ( $format === SnakFormatter::FORMAT_WIKI ) {
			$text = $warning->text();
			$text = Html::rawElement( 'span', $attributes, $text );

		} elseif ( $format === SnakFormatter::FORMAT_PLAIN ) {
			$text = '(' . $warning->text() . ')';

		} else {
			$text = '';
		}

		return $text;
	}

	/**
	 * @see SnakFormatter::getFormat
	 *
	 * @return string
	 */
	public function getFormat() {
		return $this->snakFormatter->getFormat();
	}

	/**
	 * Checks whether the given snak's type is 'value'.
	 *
	 * @see SnakFormatter::canFormatSnak
	 *
	 * @param Snak $snak
	 *
	 * @return bool
	 */
	public function canFormatSnak( Snak $snak ) {
		return $this->snakFormatter->canFormatSnak( $snak );
	}

}
