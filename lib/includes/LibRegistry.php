<?php

namespace Wikibase;

use DataTypes\DataTypeFactory;
use ValueParsers\ParserOptions;
use Wikibase\Client\WikibaseClient;
use Wikibase\DataModel\Entity\BasicEntityIdParser;
use Wikibase\DataModel\Entity\DispatchingEntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\Lib\WikibaseDataTypeBuilders;
use Wikibase\Repo\WikibaseRepo;

/**
 * Application registry for Wikibase Lib.
 *
 * TODO: migrate out this class; code should be in client or repo and
 * use their respective settings. Same rationale as for moving settings out of lib.
 *
 * @deprecated
 *
 * NOTE:
 * This application registry is a workaround for design problems in existing code.
 * It should only be used to improve existing usage of code and ideally just be
 * a stepping stone towards using proper dependency injection where possible.
 * This means you should be very careful when adding new components to the registry.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.4
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
final class LibRegistry {

	/**
	 * @since 0.4
	 *
	 * @var SettingsArray
	 */
	protected $settings;

	protected $dataTypeFactory = null;

	/**
	 * @since 0.4
	 *
	 * @param SettingsArray $settings
	 */
	public function __construct( SettingsArray $settings ) {
		$this->settings = $settings;
	}

	/**
	 * @since 0.4
	 *
	 * @throws \RuntimeException
	 * @return DataTypeFactory
	 */
	public function getDataTypeFactory() {
		if ( $this->dataTypeFactory === null ) {

			// TODO: extreme uglynes here! Get rid of this method!
			if ( defined( 'WB_VERSION' ) ) { // repo mode
				$repo = WikibaseRepo::getDefaultInstance();
				$entityIdParser = $repo->getEntityIdParser();
				$entityLookup = $repo->getEntityLookup();
			} elseif ( defined( 'WBC_VERSION' ) ) { // client mode
				$client = WikibaseClient::getDefaultInstance();
				$entityIdParser = $client->getEntityIdParser();
				$entityLookup = $client->getStore()->getEntityLookup();
			} else {
				throw new \RuntimeException( "Neither repo nor client found!" );
			}

			$urlSchemes = $this->settings->getSetting( 'urlSchemes' );
			$builders = new WikibaseDataTypeBuilders( $entityLookup, $entityIdParser, $urlSchemes );

			$typeBuilderSpecs = array_intersect_key(
				$builders->getDataTypeBuilders(),
				array_flip( $this->settings->getSetting( 'dataTypes' ) )
			);

			$this->dataTypeFactory = new DataTypeFactory( $typeBuilderSpecs );
		}

		return $this->dataTypeFactory;
	}

	/**
	 * @since 0.4
	 *
	 * @return EntityIdParser
	 */
	public function getEntityIdParser() {
		//TODO: make the ID builders configurable
		return new DispatchingEntityIdParser( BasicEntityIdParser::getBuilders() );
	}

	/**
	 * Returns a new instance constructed from global settings.
	 *
	 * @since 0.4
	 *
	 * @return LibRegistry
	 */
	protected static function newInstance() {
		return new self( Settings::singleton() );
	}

	/**
	 * Returns a default instance constructed from global settings.
	 * IMPORTANT: Use only when it is not feasible to inject an instance properly.
	 *
	 * @since 0.4
	 *
	 * @return LibRegistry
	 */
	public static function getDefaultInstance() {
		static $instance = null;

		if ( $instance === null ) {
			$instance = self::newInstance();
		}

		return $instance;
	}

	// Do not add new stuff here without reading the notice at the top first.

}
