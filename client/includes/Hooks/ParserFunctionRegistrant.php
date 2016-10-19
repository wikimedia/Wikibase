<?php

namespace Wikibase\Client\Hooks;

use Parser;
use PPFrame;
use Wikibase\Client\DataAccess\PropertyParserFunction\Runner;

/**
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Mättig
 */
class ParserFunctionRegistrant {

	/**
	 * @var bool Setting to enable use of property parser function.
	 */
	private $allowDataTransclusion;

	/**
	 * @param bool $allowDataTransclusion
	 */
	public function __construct( $allowDataTransclusion ) {
		$this->allowDataTransclusion = $allowDataTransclusion;
	}

	/**
	 * @param Parser $parser
	 */
	public function register( Parser $parser ) {
		$this->registerNoLangLinkHandler( $parser );
		$this->registerParserFunctions( $parser );
	}

	private function registerNoLangLinkHandler( Parser $parser ) {
		$parser->setFunctionHook(
			'noexternallanglinks',
			'\Wikibase\NoLangLinkHandler::handle',
			Parser::SFH_NO_HASH
		);
	}

	private function registerParserFunctions( Parser $parser ) {
		if ( !$this->allowDataTransclusion ) {
			return;
		}

		$parser->setFunctionHook(
			'property',
			function( Parser $parser, PPFrame $frame, array $args ) {
				return Runner::property( $parser, $frame, $args );
			},
			Parser::SFH_OBJECT_ARGS
		);

		$parser->setFunctionHook(
			'statementvalues',
			function( Parser $parser, PPFrame $frame, array $args ) {
				return Runner::statementvalues( $parser, $frame, $args );
			},
			Parser::SFH_OBJECT_ARGS
		);
	}

}
