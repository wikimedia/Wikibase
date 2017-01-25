<?php

namespace Wikibase\Repo\Search\Elastic\Fields;

use SearchEngine;
use Wikibase\DataModel\Entity\EntityDocument;

/**
 * Each field is intended to be used by CirrusSearch as an
 * additional property of a page.
 *
 * The data returned by the field must match the field
 * type defined in the mapping. (e.g. nested must be array,
 * integer field must get an int, etc)
 *
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
interface SearchIndexField {

	/**
	 * Produce specific field mapping
	 * @param SearchEngine $engine
	 * @param string $name
	 * @return \SearchIndexField
	 */
	public function getMapping( SearchEngine $engine, $name );

	/**
	 * @param EntityDocument $entity
	 *
	 * @return mixed Get the value of the field to be indexed when a page/document
	 *               is indexed. This might be an array with nested data, if the field
	 *               is defined with nested type or an int or string for simple field types.
	 */
	public function getFieldData( EntityDocument $entity );

}
