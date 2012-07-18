<?php

namespace Wikibase;

/**
 * Represents a single Wikibase item.
 * See https://meta.wikimedia.org/wiki/Wikidata/Data_model#Items
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Daniel Kinzler
 */
class ItemObject extends EntityObject implements Item {

	/**
	 * @see EntityObject::getIdPrefix()
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	protected function getIdPrefix() {
		return 'q';
	}

	/**
	 * @see Item::addSiteLink()
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $pageName
	 * @param string $updateType
	 *
	 * @return array|false Returns array on success, or false on failure
	 */
	public function addSiteLink( $siteId, $pageName, $updateType = 'add' ) {
		$success =
			( $updateType === 'add' && !array_key_exists( $siteId, $this->data['links'] ) )
				|| ( $updateType === 'update' && array_key_exists( $siteId, $this->data['links'] ) )
				|| ( $updateType === 'set' );

		if ( $success ) {
			$this->data['links'][$siteId] = $pageName;
		}

		// TODO: we should not return this array here like this. Probably create new object to represent link.
		return $success ? array( 'site' => $siteId, 'title' => $this->data['links'][$siteId] ) : false;
	}

	/**
	 * @see Item::removeSiteLink()
	 *
	 * @since 0.1
	 *
	 * @param string $siteId
	 * @param string $pageName
	 *
	 * @return boolean Success indicator
	 */
	public function removeSiteLink( $siteId, $pageName = false ) {
		if ( $pageName !== false) {
			$success = array_key_exists( $siteId, $this->data['links'] ) && $this->data['links'][$siteId] === $pageName;
		}
		else {
			$success = true;
		}

		if ( $success ) {
			unset( $this->data['links'][$siteId] );
		}

		return $success;
	}

	/**
	 * @see Item::getSiteLinks()
	 *
	 * @since 0.1
	 *
	 * @return array
	 */
	public function getSiteLinks() {
		return $this->data['links'];
	}

	/**
	 * @see Item::isEmpty()
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public function isEmpty() {
		if ( !parent::isEmpty() ) {
			return false;
		}

		$fields = array( 'links' );

		foreach ( $fields as $field ) {
			if ( $this->data[$field] !== array() ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Cleans the internal array structure.
	 * This consists of adding elements the code expects to be present later on
	 * and migrating or removing elements after changes to the structure are made.
	 * Should typically be called before using any of the other methods.
	 *
	 * @since 0.1
	 */
	protected function cleanStructure() {
		parent::cleanStructure();

		foreach ( array( 'links' ) as $field ) {
			if ( !array_key_exists( $field, $this->data ) ) {
				$this->data[$field] = array();
			}
		}
	}

	/**
	 * @see Item::copy()
	 *
	 * @since 0.1
	 *
	 * @return Item
	 */
	public function copy() {
		$array = array();

		foreach ( $this->toArray() as $key => $value ) {
			$array[$key] = is_object( $value ) ? clone $value : $value;
		}

		return new static( $array );
	}

	/**
	 * @since 0.1
	 *
	 * @param array $data
	 *
	 * @return Item
	 */
	public static function newFromArray( array $data ) {
		return new static( $data );
	}

	/**
	 * @since 0.1
	 *
	 * @return Item
	 */
	public static function newEmpty() {
		return self::newFromArray( array() );
	}

	/**
	 * Whatever would be more appropriate during a normalization of titles during lookup.
	 * FIXME: this is extremely badly named and is probably incorrectly placed
	 *
	 * @since 0.1
	 *
	 * @param string $str
	 * @return string
	 */
	public static function normalize( $str ) {

		// ugly but works, should probably do more normalization
		// should (?) use $wgLegalTitleChars and $wgDisableTitleConversion somehow
		$str = preg_replace( '/^[\s_]+/', '', $str );
		$str = preg_replace( '/[\s_]+$/', '', $str );
		$str = preg_replace( '/[\s_]+/', ' ', $str );

		return $str;
	}

	/**
	 * @see Entity::getType
	 *
	 * @since 0.1
	 *
	 * @return string
	 */
	public function getType() {
		return 'item';
	}

	/**
	 * @see Entity::getDiff
	 *
	 * @since 0.1
	 *
	 * @param Entity $target
	 *
	 * @return ItemDiff
	 */
	public function getDiff( Entity $target ) {
		return ItemDiff::newFromItems( $this, $target );
	}

}
