<?php

/**
 * Minimal set of classes necessary to fulfill needs of parts of Wikibase relying on
 * the Scribunto extension.
 * @codingStandardsIgnoreFile
 */

class Scribunto_LuaEngine {
	/**
	 * @param string $moduleFileName string
	 * @param array $interfaceFuncs
	 * @param array $setupOptions
	 * @return array Lua package
	 */
	public function registerInterface( $moduleFileName, $interfaceFuncs, $setupOptions = array() ) {
	}
}

class Scribunto_LuaLibraryBase {
	/**
	 * @param string $name
	 * @param int $argIdx integer
	 * @param mixed $arg mixed
	 * @param string $expectType
	 */
	protected function checkType( $name, $argIdx, $arg, $expectType ) {
	}

	/**
	 * @param string $name
	 * @param int $argIdx
	 * @param mixed &$arg
	 * @param string $expectType
	 * @param mixed $default
	 */
	protected function checkTypeOptional( $name, $argIdx, &$arg, $expectType, $default ) {
	}

	/**
	 * @return Scribunto_LuaEngine engine
	 */
	protected function getEngine() {
	}

	/**
	 * @return Parser parser
	 */
	protected function getParser() {
	}

	/**
	 * @return ParserOptions parser options
	 */
	protected function getParserOptions() {
	}

	/**
	 * @return Lua
	 */
	function register() {
	}
}

class ScribuntoException {
	/**
	 * @param string $messageName
	 * @param array $params
	 */
	function __construct( $messageName, $params = [] ) {
	}
}
