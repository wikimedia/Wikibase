<?php

namespace Wikibase;

/**
 * Interface for diffs. Diffs are collections of IDiffOp objects.
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
interface IDiff extends IDiffOp {

	/**
	 * Constructor.
	 *
	 * @since 0.1
	 *
	 * @param $operations array of IDiffOp
	 * @patam string|integer|null $parentKey
	 */
	function __construct( array $operations, $parentKey = null );

	/**
	 * Returns the operations that make up the diff.
	 *
	 * @since 0.1
	 *
	 * @return array of IDiffOp
	 */
	public function getOperations();

	/**
	 * Returns if the diff is empty. ie if it has no operations.
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public function isEmpty();

	/**
	 * Adds the provided operations to the diff.
	 *
	 * @since 0.1
	 *
	 * @param $operations array of IDiffOp
	 */
	public function addOperations( array $operations );

	/**
	 * Returns a new empty diff.
	 *
	 * @since 0.1
	 *
	 * @return IDiff
	 */
	public static function newEmpty();

	/**
	 * Returns the key the IDiffOp has in it's parent diff.
	 *
	 * @since 0.1
	 *
	 * @return int|null|string
	 */
	public function getParentKey();

	/**
	 * Returns if the IDiffOp has a parent diff key.
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public function hasParentKey();

	/**
	 * Filters the diff for operations that can be applied to the provided object.
	 *
	 * @since 0.1
	 *
	 * @param array $currentObject
	 *
	 * @return IDiff
	 */
	public function getApplicableDiff( array $currentObject );

}