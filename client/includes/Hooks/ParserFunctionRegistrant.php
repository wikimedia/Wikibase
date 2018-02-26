<?php

namespace Wikibase\Client\Hooks;

use Parser;
use PPFrame;
use Wikibase\Client\DataAccess\ParserFunctions\Runner;

/**
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Thiemo Kreuz
 */
class ParserFunctionRegistrant {

	/**
	 * @var bool Setting to enable use of property parser function.
	 */
	private $allowDataTransclusion;

	/**
	 * @var bool Setting to enable local override of descriptions.
	 */
	private $allowLocalDescription;

	/**
	 * @param bool $allowDataTransclusion
	 */
	public function __construct( $allowDataTransclusion, $allowLocalDescription ) {
		$this->allowDataTransclusion = $allowDataTransclusion;
		$this->allowLocalDescription = $allowLocalDescription;
	}

	public function register( Parser $parser ) {
		$this->registerNoLangLinkHandler( $parser );
		$this->registerParserFunctions( $parser );
	}

	private function registerNoLangLinkHandler( Parser $parser ) {
		$parser->setFunctionHook(
			'noexternallanglinks',
			[ NoLangLinkHandler::class, 'handle' ],
			Parser::SFH_NO_HASH
		);
	}

	private function registerParserFunctions( Parser $parser ) {
		if ( $this->allowDataTransclusion ) {
			$parser->setFunctionHook(
				'property',
				function( Parser $parser, PPFrame $frame, array $args ) {
					return Runner::renderEscapedPlainText( $parser, $frame, $args );
				},
				Parser::SFH_OBJECT_ARGS
			);

			$parser->setFunctionHook(
				'statements',
				function( Parser $parser, PPFrame $frame, array $args ) {
					return Runner::renderRichWikitext( $parser, $frame, $args );
				},
				Parser::SFH_OBJECT_ARGS
			);
		}

		if ( $this->allowLocalDescription ) {
			$parser->setFunctionHook(
				'shortdesc',
				[ ShortDescHandler::class, 'handle' ],
				Parser::SFH_NO_HASH
			);
		}
	}

}
