<?php

namespace Wikibase\Client\Hooks;

use Parser;
use PPFrame;
use Wikibase\DataAccess\PropertyParserFunction\Runner;

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
		$this->registerPropertyParserFunction( $parser );
	}

	private function registerNoLangLinkHandler( Parser $parser ) {
		$parser->setFunctionHook(
			'noexternallanglinks',
			'\Wikibase\NoLangLinkHandler::handle',
			Parser::SFH_NO_HASH
		);
	}

	private function registerPropertyParserFunction( Parser $parser ) {
		if ( !$this->allowDataTransclusion ) {
			return;
		}

		$parser->setFunctionHook(
			'property',
			function( Parser $parser, PPFrame $frame, array $args ) {
				return Runner::render( $parser, $frame, $args );
			},
			Parser::SFH_OBJECT_ARGS
		);
	}

}
