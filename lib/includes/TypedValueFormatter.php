<?php

namespace Wikibase\Lib;

use DataValues\DataValue;
use ValueFormatters\FormattingException;

/**
 * Provides a string representation for a DataValue given its associated DataType.
 *
 * @since 0.4
 *
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
interface TypedValueFormatter {

	/**
	 * Formats the given DataValue.
	 *
	 * If $dataTypeId is given, it may be used as a hint for providing
	 * more appropriate formatting.
	 *
	 * @todo: make $dataTypeId mandatory.
	 *
	 * @param DataValue $value
	 * @param string|null $dataTypeId
	 *
	 * @throws FormattingException
	 * @return string
	 */
	public function formatValue( DataValue $value, $dataTypeId = null );

}
