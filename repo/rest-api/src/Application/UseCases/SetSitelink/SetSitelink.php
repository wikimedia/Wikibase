<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\SetSitelink;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\Repo\RestApi\Application\Serialization\SitelinkDeserializer;
use Wikibase\Repo\RestApi\Domain\Model\EditMetadata;
use Wikibase\Repo\RestApi\Domain\Model\SitelinkEditSummary;
use Wikibase\Repo\RestApi\Domain\Services\ItemRetriever;
use Wikibase\Repo\RestApi\Domain\Services\ItemUpdater;

/**
 * @license GPL-2.0-or-later
 */
class SetSitelink {

	private SitelinkDeserializer $sitelinkDeserializer;
	private ItemRetriever $itemRetriever;
	private ItemUpdater $itemUpdater;

	public function __construct(
		SitelinkDeserializer $sitelinkDeserializer,
		ItemRetriever $itemRetriever,
		ItemUpdater $itemUpdater
	) {
		$this->sitelinkDeserializer = $sitelinkDeserializer;
		$this->itemRetriever = $itemRetriever;
		$this->itemUpdater = $itemUpdater;
	}

	public function execute( SetSitelinkRequest $request ): SetSitelinkResponse {
		$itemId = new ItemId( $request->getItemId() );
		$siteId = $request->getSiteId();
		$sitelink = $this->sitelinkDeserializer->deserialize( $siteId, $request->getSitelink() );

		$item = $this->itemRetriever->getItem( $itemId );
		$sitelinkExists = $item->getSiteLinkList()->hasLinkWithSiteId( $siteId );
		$item->getSiteLinkList()->setSiteLink( $sitelink );

		$editSummary = $sitelinkExists
			? SitelinkEditSummary::newReplaceSummary( '', $sitelink )
			: SitelinkEditSummary::newAddSummary( '', $sitelink );

		$newRevision = $this->itemUpdater->update(
			$item, // @phan-suppress-current-line PhanTypeMismatchArgumentNullable
			new EditMetadata( [], false, $editSummary )
		);

		return new SetSitelinkResponse(
			$newRevision->getItem()->getSitelinks()[ $siteId ],
			$newRevision->getLastModified(),
			$newRevision->getRevisionId(),
			$sitelinkExists
		);
	}

}
