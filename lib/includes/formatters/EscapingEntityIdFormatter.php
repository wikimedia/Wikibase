<?php

namespace Wikibase\Lib;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Services\EntityId\EntityIdFormatter;

/**
 * EscapingEntityIdFormatter wraps another EntityIdFormatter and
 * applies a transformation (escaping) to that formatter's output.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class EscapingEntityIdFormatter implements EntityIdFormatter {

	/**
	 * @var EntityIdFormatter
	 */
	private $formatter;

	/**
	 * @var callable
	 */
	private $escapeCallback;

	/**
	 * @param EntityIdFormatter $formatter
	 * @param callable $escapeCallback A callable taking plain text and returning escaped HTML
	 * @throws InvalidArgumentException
	 */
	public function __construct( EntityIdFormatter $formatter, $escapeCallback ) {
		if ( !is_callable( $escapeCallback ) ) {
			throw new InvalidArgumentException( '$escapeCallback must be callable' );
		}

		$this->formatter = $formatter;
		$this->escapeCallback = $escapeCallback;
	}

	/**
	 * Format an EntityId
	 *
	 * @param EntityId $value
	 * @return string
	 */
	public function formatEntityId( EntityId $value ) {
		$text = $this->formatter->formatEntityId( $value );
		$escaped = call_user_func( $this->escapeCallback, $text );
		return $escaped;
	}

}
