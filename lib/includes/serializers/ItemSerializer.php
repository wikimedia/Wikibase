<?php
namespace Wikibase\Lib\Serializers;

use MWException;
use Wikibase\DataModel\SimpleSiteLink;
use Wikibase\Entity;
use Wikibase\Item;

/**
 * Serializer for items.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author John Erling Blad < jeblad@gmail.com >
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ItemSerializer extends EntitySerializer implements Unserializer {

	/**
	 * @since 0.4
	 *
	 * @var \SiteSQLStore
	 */
	protected $siteStore;

	/**
	 * Constructor.
	 *
	 * @since 0.4
	 *
	 * @param EntitySerializationOptions $options
	 */
	public function __construct( EntitySerializationOptions $options, \SiteSQLStore $siteStore = null ) {
		if ( $siteStore === null ) {
			$this->siteStore = \SiteSQLStore::newInstance();
		} else {
			$this->siteStore = $siteStore;
		}
		parent::__construct( $options );
	}

	/**
	 * @see EntitySerializer::getEntityTypeSpecificSerialization
	 *
	 * @since 0.2
	 *
	 * @param Entity $item
	 *
	 * @return array
	 * @throws MWException
	 */
	protected function getEntityTypeSpecificSerialization( Entity $item ) {
		if ( !( $item instanceof Item ) ) {
			throw new MWException( 'ItemSerializer can only serialize Item implementing objects' );
		}

		$serialization = array();

		if ( in_array( 'sitelinks', $this->options->getProps() ) ) {
			$siteLinkSerializer = new SiteLinkSerializer( $this->options, $this->siteStore );
			$siteLinks = $item->getSimpleSiteLinks();
			$serialization['sitelinks'] = $siteLinkSerializer->getSerialized( $siteLinks );
		}

		return $serialization;
	}

	/**
	 * @see Unserializer::newFromSerialization
	 *
	 * @since 0.5
	 *
	 * @param array $data
	 *
	 * @return Item
	 */
	public function newFromSerialization( array $data ) {
		$item = parent::newFromSerialization( $data );

		if ( array_key_exists( 'sitelinks', $data ) ) {
			$siteLinkSerializer = new SiteLinkSerializer( $this->options );
			$siteLinks = $siteLinkSerializer->newFromSerialization( $data['sitelinks'] );

			foreach( $siteLinks as $siteLink ) {
				$item->addSimpleSiteLink( $siteLink );
			}
		}

		return $item;
	}
}
