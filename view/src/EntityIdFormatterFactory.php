<?php

namespace Wikibase\View;

use Language;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;
use Wikibase\DataModel\Services\Lookup\LabelDescriptionLookup;

/**
 * A factory interface for generating EntityIdFormatters.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Kinzler
 */
interface EntityIdFormatterFactory {

	/**
	 * Returns the formatter's output format, as defined by the
	 * SnakFormatter::FORMAT_XXX constants.
	 *
	 * This allows callers to assert that the formatter returned by getEntityIdFormatter()
	 * will generate text in the desired format, applying the appropriate escaping.
	 *
	 * @see SnakFormatter::FORMAT_WIKITEXT
	 * @see SnakFormatter::FORMAT_HTML
	 *
	 * @return string
	 */
	public function getOutputFormat();

	/**
	 * // TODO accept only Language
	 *
	 * @param Language|LabelDescriptionLookup $language
	 *
	 * @return EntityIdFormatter
	 */
	public function getEntityIdFormatter( $language );

}
