<?php

namespace Wikibase\Repo\Specials;

use HttpError;
use Wikibase\DataModel\Entity\EntityIdParser;
use Wikibase\DataModel\Entity\EntityIdParsingException;
use Wikibase\Lib\Store\EntityTitleLookup;

/**
 * Special page for addressing entity data pages without knowing the namespace.
 *
 * This special page redirects the a page related to the given entity ID.
 * This special page is completely agnostic to what is the page of the entity. This is the responsibility
 * of EntityTitleLookup that can e.g. return local titles for local entities, and use interwikis
 * for foreign entities.
 *
 * This allows wikis to link to an entity page without needing to know namespace names of entity types
 * configured on the target wiki.
 *
 * @license GPL-2.0+
 */
class SpecialEntityPage extends SpecialWikibasePage {

	/**
	 * @var EntityIdParser
	 */
	private $entityIdParser;

	/**
	 * @var EntityTitleLookup
	 */
	private $entityTitleLookup;

	public function __construct( EntityIdParser $entityIdParser, EntityTitleLookup $entityTitleLookup ) {
		parent::__construct( 'EntityPage' );

		$this->entityIdParser = $entityIdParser;
		$this->entityTitleLookup = $entityTitleLookup;
	}

	/**
	 * @see SpecialWikibasePage::execute
	 *
	 * @param string|null $subPage
	 *
	 * @throws HttpError
	 */
	public function execute( $subPage ) {
		$id = (string)$subPage;
		$id = $this->getRequest()->getText( 'id', $id );

		if ( $id === '' ) {
			$this->getOutput()->showErrorPage( 'wikibase-entitypage-title', 'wikibase-entitypage-text' );
			return;
		}

		try {
			$entityId = $this->entityIdParser->parse( $id );
		} catch ( EntityIdParsingException $ex ) {
			throw new HttpError( 400, wfMessage( 'wikibase-entitypage-bad-id', $id ) );
		}

		$title = $this->entityTitleLookup->getTitleForId( $entityId );

		if ( $title === null ) {
			throw new HttpError( 400, wfMessage( 'wikibase-entitypage-bad-id', $id ) );
		}

		$this->getOutput()->redirect( $title->getFullURL(), 301 );
	}

}
