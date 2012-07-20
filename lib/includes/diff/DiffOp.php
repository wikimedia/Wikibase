<?php

namespace Wikibase;
use MWException;

/**
 * Base class for diff operations. A diff operation
 * represents a change to a single element.
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 * @ingroup WikibaseDiff
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class DiffOp implements IDiffOp {

	/**
	 * Returns a new IDiffOp implementing instance to represent the provided change.
	 *
	 * @since 0.1
	 *
	 * @param array $array
	 *
	 * @return IDiffOp
	 * @throws MWException
	 */
	public static function newFromArray( array $array ) {
		$type = array_shift( $array );

		$typeMap = array(
			'add' => '\Wikibase\DiffOpAdd',
			'remove' => '\Wikibase\DiffOpRemove',
			'change' => '\Wikibase\DiffOpChange',
			'list' => '\Wikibase\ListDiff',
			'map' => '\Wikibase\MapDiff',
		);

		if ( !array_key_exists( $type, $typeMap ) ) {
			throw new MWException( 'Invalid diff type provided.' );
		}

		return call_user_func_array( array( $typeMap[$type], '__construct' ), $array );
	}

	/**
	 * @since 0.1
	 *
	 * @param string $type
	 *
	 * @return IDiffOp
	 */
	public static function newFromType( $type ) {
		return static::newFromArray( array( $type, array() ) );
	}

}