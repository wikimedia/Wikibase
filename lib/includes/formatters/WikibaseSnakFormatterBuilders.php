<?php

namespace Wikibase\Lib;

use DataTypes\DataTypeFactory;
use DataValues\Geo\Formatters\GeoCoordinateFormatter;
use DataValues\Geo\Formatters\GlobeCoordinateFormatter;
use InvalidArgumentException;
use ValueFormatters\DecimalFormatter;
use ValueFormatters\FormatterOptions;
use ValueFormatters\QuantityFormatter;
use ValueFormatters\QuantityHtmlFormatter;
use ValueFormatters\StringFormatter;
use ValueFormatters\ValueFormatter;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Services\EntityId\EntityIdLabelFormatter;
use Wikibase\DataModel\Services\Lookup\PropertyDataTypeLookup;
use Wikibase\Lib\Formatters\HtmlExternalIdentifierFormatter;
use Wikibase\Lib\Formatters\WikitextExternalIdentifierFormatter;
use Wikibase\PropertyInfoStore;

/**
 * Low level factory for SnakFormatters for well known data types.
 *
 * @warning: This is a low level factory for use by boostrap code only!
 * Program logic should use an instance of OutputFormatValueFormatterFactory
 * resp. OutputFormatSnakFormatterFactory.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class WikibaseSnakFormatterBuilders {

	/**
	 * @var WikibaseValueFormatterBuilders
	 */
	private $valueFormatterBuilders;

	/**
	 * @var PropertyInfoStore
	 */
	private $propertyInfoStore;

	/**
	 * @var PropertyDataTypeLookup
	 */
	private $dataTypeLookup;

	/**
	 * @var DataTypeFactory
	 */
	private $dataTypeFactory;

	/**
	 * @param WikibaseValueFormatterBuilders $valueFormatterBuilders
	 */
	public function __construct(
		WikibaseValueFormatterBuilders $valueFormatterBuilders,
		PropertyInfoStore $propertyInfoStore,
		PropertyDataTypeLookup $dataTypeLookup,
		DataTypeFactory $dataTypeFactory
	) {
		$this->valueFormatterBuilders = $valueFormatterBuilders;
		$this->propertyInfoStore = $propertyInfoStore;
		$this->dataTypeLookup = $dataTypeLookup;
		$this->dataTypeFactory = $dataTypeFactory;
	}

	/**
	 * @param string $format The desired target format, see SnakFormatter::FORMAT_XXX
	 *
	 * @return bool True if $format is one of the SnakFormatter::FORMAT_HTML_XXX formats.
	 */
	private function isHtmlFormat( $format ) {
		return $format === SnakFormatter::FORMAT_HTML
			|| $format === SnakFormatter::FORMAT_HTML_DIFF
			|| $format === SnakFormatter::FORMAT_HTML_WIDGET;
	}

	/**
	 * Wraps the given formatter in an EscapingSnakFormatter if necessary.
	 *
	 * @param string $format The desired target format, see SnakFormatter::FORMAT_XXX
	 * @param SnakFormatter $formatter The plain text formatter to wrap.
	 *
	 * @return SnakFormatter
	 */
	private function escapeSnakFormatter( $format, SnakFormatter $formatter ) {
		if ( $this->isHtmlFormat( $format ) ) {
			return new EscapingSnakFormatter( $format, $formatter, 'htmlspecialchars' );
		} elseif ( $format === SnakFormatter::FORMAT_WIKI ) {
			return new EscapingSnakFormatter( $format, $formatter, 'wfEscapeWikiText' );
		} elseif ( $format === SnakFormatter::FORMAT_PLAIN ) {
			return $formatter;
		} else {
			throw new InvalidArgumentException( 'Unsupported output format: ' . $format );
		}
	}

	/**
	 * @param string $format The desired target format, see SnakFormatter::FORMAT_XXX
	 * @param FormatterOptions $options
	 *
	 * @return SnakFormatter
	 */
	public function newExternalIdentifierFormatter( $format, FormatterOptions $options ) {
		if ( $format === SnakFormatter::FORMAT_PLAIN ) {
			return new PropertyValueSnakFormatter(
				$format,
				$options,
				$this->valueFormatterBuilders->newStringFormatter( $format, $options ),
				$this->dataTypeLookup,
				$this->dataTypeFactory
			);
		}

		$urlProvider = new FieldPropertyInfoProvider(
			$this->propertyInfoStore,
			PropertyInfoStore::KEY_FORMATTER_URL
		);

		$urlExpander = new PropertyInfoSnakUrlExpander( $urlProvider );

		if ( $format === SnakFormatter::FORMAT_WIKI ) {
			return new WikitextExternalIdentifierFormatter( $urlExpander );
		} elseif ( $this->isHtmlFormat( $format ) ) {
			return new HtmlExternalIdentifierFormatter( $urlExpander );
		} else {
			throw new InvalidArgumentException( 'Unsupported output format: ' . $format );
		}
	}

}
