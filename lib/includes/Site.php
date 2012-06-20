<?php

namespace Wikibase;

/**
 * Interface for site objects.
 *
 * @since 0.1
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
interface Site extends \IORMRow {

	/**
	 *
	 *
	 * @since 0.1
	 *
	 * @return SiteConfig
	 */
	public function getConfig();

	/**
	 * Returns the global site identifier (ie enwiktionary).
	 *
	 * @since 0.1
	 *
	 * @return integer
	 */
	public function getGlobalId();

	/**
	 * Returns the type of the site (ie SITE_MW).
	 *
	 * @since 0.1
	 *
	 * @return integer
	 */
	public function getType();


	/**
	 * Returns the type of the site (ie SITE_GROUP_WIKIPEDIA).
	 *
	 * @since 0.1
	 *
	 * @return integer
	 */
	public function getGroup();

	/**
	 * Returns the base URL of the site, ie http://en.wikipedia.org
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getUrl();

	/**
	 * Returns the full page path (ie site url + relative page path).
	 * The page title should go at the $1 marker. If the $pageName
	 * argument is provided, the marker will be replaced by it's value.
	 *
	 * @since 0.1
	 *
	 * @param string|false $pageName
	 *
	 * @return string
	 */
	public function getPagePath( $pageName = false );

	/**
	 * Returns the full file path (ie site url + relative file path).
	 * The path should go at the $1 marker. If the $path
	 * argument is provided, the marker will be replaced by it's value.
	 *
	 * @since 0.1
	 *
	 * @param string|false $path
	 *
	 * @return string
	 */
	public function getFilePath( $path = false );

	/**
	 * Returns the relative page path.
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getRelativePagePath();

	/**
	 * Returns the relative file path.
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getRelativeFilePath();

}