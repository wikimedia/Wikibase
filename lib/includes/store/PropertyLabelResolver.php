<?php
 /**
 *
 * Copyright © 24.04.13 by the authors listed below.
 *
 * @license GPL 2+
 *
 * @author daniel
 */


namespace Wikibase;


/**
 * Resolves property labels (which are unique per language) into entity IDs.
 *
 * @package Wikibase
 */

interface PropertyLabelResolver {

	/**
	 * @param string[] $labels the labels
	 * @param string   $recache Flag, set to 'recache' to discard cached data and fetch fresh data
	 *                 from the database.
	 *
	 * @return EntityId[] a map of strings from $labels to the corresponding entity ID.
	 */
	public function getPropertyIdsForLabels( array $labels, $recache = '' );

}