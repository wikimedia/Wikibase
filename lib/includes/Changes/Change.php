<?php

namespace Wikibase;

/**
 * Interface for objects representing changes.
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Change {

	/**
	 * Returns the age of the change in seconds.
	 *
	 * @return integer
	 */
	public function getAge();

	/**
	 * Returns the time on which the change was made as a timestamp in TS_MW format.
	 *
	 * @return string TS_MW
	 */
	public function getTime();

	/**
	 * @return int|null Number to be used as an identifier when persisting the change.
	 */
	public function getId();

	/**
	 * Returns the id of the affected object (ie item or property).
	 *
	 * @return string
	 */
	public function getObjectId();

}
