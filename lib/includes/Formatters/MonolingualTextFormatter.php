<?php

namespace Wikibase\Formatters;

use DataValues\MonolingualTextValue;
use InvalidArgumentException;
use ValueFormatters\ValueFormatterBase;

/**
 * @license GPL-2.0+
 * @author Daniel Kinzler
 */
class MonolingualTextFormatter extends ValueFormatterBase {

	/**
	 * Intentional override because this formatter does not consume any options.
	 */
	public function __construct() {
		parent::__construct();
	}

	/**
	 * @see ValueFormatter::format
	 *
	 * @param MonolingualTextValue $value
	 *
	 * @throws InvalidArgumentException
	 * @return string Text
	 */
	public function format( $value ) {
		if ( !( $value instanceof MonolingualTextValue ) ) {
			throw new InvalidArgumentException( 'Data value type mismatch. Expected a MonolingualTextValue.' );
		}

		return $value->getText();
	}

}
