<?php

namespace Wikibase\Client;

use Exception;
use RuntimeException;

/**
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class PropertyLabelNotResolvedException extends RuntimeException {

	/**
	 * @param string $label
	 * @param string $languageCode
	 * @param string|null $message
	 * @param Exception|null $previous
	 */
	public function __construct(
		$label,
		$languageCode,
		$message = null,
		Exception $previous = null
	) {
		if ( $message === null ) {
			$message = "Property not found for label '$label' and language '$languageCode'";
		}

		parent::__construct( $message, 0, $previous );
	}

}
