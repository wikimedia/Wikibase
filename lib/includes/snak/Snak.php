<?php

namespace Wikibase;

/**
 * Interface for objects that represent a single Wikibase snak.
 * See https://meta.wikimedia.org/wiki/Wikidata/Data_model#Snaks
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Snak extends \Serializable {

	/**
	 * Returns a hash that can be used to identify the snak within a list of snaks.
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getHash();

}