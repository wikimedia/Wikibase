<?php

namespace Wikibase\Client\Hooks;

use Parser;
use Wikibase\Client\WikibaseClient;

/**
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Marius Hoch < hoo@online.de >
 */
class ParserClearStateHookHandler {

	/**
	 * Called when resetting the state of the Parser between parses.
	 *
	 * @param Parser $parser
	 *
	 * @return bool
	 */
	public static function onParserClearState( Parser $parser ) {
		$wikibaseClient = WikibaseClient::getDefaultInstance();
		// Reset the entity access limits, per T127462
		$wikibaseClient->getRestrictedEntityLookup()->reset();

		return true;
	}

}
