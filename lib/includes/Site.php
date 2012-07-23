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
	 * Returns the type of the site (ie SITE_TYPE_MEDIAWIKI).
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
	 * @return string|bool the site's url, or false if not known
	 */
	public function getUrl();

	/**
	 * Returns language code of the sites primary language.
	 *
	 * @since 0.1
	 *
	 * @return string|bool the site's language, or false if not known
	 */
	public function getLanguage();

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
	 * Returns the normalized, canonical form of the given page name.
	 * How normalization is performed or what the properties of a normalized name are depends on the site.
	 * The general contract of this method is that the normalized form shall refer to the same content
	 * as the original form, and any other page name referring to the same content will have the same normalized form.
	 *
	 * @since 0.1
	 *
	 * @param string $pageName
	 *
	 * @return string the normalized page name
	 * @return string the normalized page name
	 */
	public function normalizePageName( $pageName );

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

	/**
	 * Returns an array with additional data part of the
	 * site definition. This is meant for usage by fields
	 * we never need to search against and for those that
	 * are site type specific, ie "allows file uploads"
	 * for MediaWiki sites.
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	public function getExtraData();

}