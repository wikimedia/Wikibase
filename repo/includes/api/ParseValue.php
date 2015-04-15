<?php

namespace Wikibase\Api;

use ApiBase;
use ApiResult;
use DataValues\DataValue;
use LogicException;
use OutOfBoundsException;
use ValueParsers\ParseException;
use ValueParsers\ParserOptions;
use ValueParsers\ValueParser;
use Wikibase\Repo\ValueParserFactory;

/**
 * API module for using value parsers.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class ParseValue extends ApiWikibase {

	/**
	 * @var null|ValueParserFactory
	 */
	private $factory = null;

	/**
	 * @return ValueParserFactory
	 */
	private function getFactory() {
		if ( $this->factory === null ) {
			$this->factory = new ValueParserFactory( $GLOBALS['wgValueParsers'] );
		}

		return $this->factory;
	}

	/**
	 * @see ApiBase::execute
	 *
	 * @since 0.1
	 */
	public function execute() {
		$parser = $this->getParser();

		$results = array();

		$params = $this->extractRequestParams();

		foreach ( $params['values'] as $value ) {
			$results[] = $this->parseValue( $parser, $value );
		}

		$this->outputResults( $results );
	}

	/**
	 * @return ValueParser
	 * @throws LogicException
	 */
	private function getParser() {
		$params = $this->extractRequestParams();

		$options = $this->getOptionsObject( $params['options'] );

		try {
			$parser = $this->getFactory()->newParser( $params['parser'], $options );
		} catch ( OutOfBoundsException $ex ) {
			throw new LogicException( 'Could not obtain a ValueParser instance' );
		}

		return $parser;
	}

	private function parseValue( ValueParser $parser, $value ) {
		$result = array(
			'raw' => $value
		);

		try {
			$parseResult = $parser->parse( $value );
		}
		catch ( ParseException $parseError ) {
			$this->addParseErrorToResult( $result, $parseError );
			return $result;
		}

		if ( $parseResult instanceof DataValue ) {
			$result['value'] = $parseResult->getArrayValue();
			$result['type'] = $parseResult->getType();
		}
		else {
			$result['value'] = $parseResult;
		}

		return $result;
	}

	private function addParseErrorToResult( &$result, ParseException $parseError ) {
		$result['error'] = get_class( $parseError );

		$result['error-info'] = $parseError->getMessage();
		$result['expected-format'] = $parseError->getExpectedFormat();

		$status = $this->getExceptionStatus( $parseError );
		$this->getErrorReporter()->addStatusToResult( $status, $result );
	}

	private function outputResults( array $results ) {
		ApiResult::setIndexedTagName( $results, 'result' );

		$this->getResult()->addValue(
			null,
			'results',
			$results
		);
	}

	/**
	 * @param string $optionsParam
	 *
	 * @return ParserOptions
	 */
	private function getOptionsObject( $optionsParam ) {
		$parserOptions = new ParserOptions();
		$parserOptions->setOption( ValueParser::OPT_LANG, $this->getLanguage()->getCode() );

		if ( $optionsParam !== null && $optionsParam !== '' ) {
			$options = \FormatJson::decode( $optionsParam, true );

			if ( !is_array( $options ) ) {
				$this->dieError( 'Malformed options parameter', 'malformed-options' );
			}

			foreach ( $options as $name => $value ) {
				$parserOptions->setOption( $name, $value );
			}
		}

		return $parserOptions;
	}

	/**
	 * @see ApiBase::getAllowedParams
	 */
	protected function getAllowedParams() {
		return array(
			'parser' => array(
				ApiBase::PARAM_TYPE => $this->getFactory()->getParserIds(),
				ApiBase::PARAM_REQUIRED => true,
			),
			'values' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
				ApiBase::PARAM_ISMULTI => true,
			),
			'options' => array(
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => false,
			),
		);
	}

	/**
	 * @see ApiBase::getExamplesMessages
	 */
	protected function getExamplesMessages() {
		return array(
			'action=wbparsevalue&parser=null&values=foo|bar' => 'apihelp-wbparsevalue-example-1',
			'action=wbparsevalue&parser=time&values=1994-02-08&options={"precision":9}' => 'apihelp-wbparsevalue-example-2',
		);
	}

}
