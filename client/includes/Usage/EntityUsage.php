<?php

namespace Wikibase\Client\Usage;

use InvalidArgumentException;
use Wikibase\DataModel\Entity\EntityId;

/**
 * Value object representing the usage of an entity. This includes information about
 * how the entity is used, but not where.
 *
 * @see docs/usagetracking.wiki
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class EntityUsage {

	/**
	 * Usage flag indicating that the entity's sitelinks were used as links.
	 * This would be the case when generating language links or sister links from
	 * an entity's sitelinks, for display in the sidebar.
	 *
	 * @note: This does NOT cover sitelinks used in wikitext (e.g. via Lua).
	 *        Use OTHER_USAGE for that.
	 */
	const SITELINK_USAGE = 'S';

	/**
	 * Usage flag indicating that the entity's label in the local content language was used.
	 * This would be the case when showing the label of a referenced entity.
	 */
	const LABEL_USAGE = 'L';

	/**
	 * Usage flag indicating that the entity's local page name was used.
	 * This would be the case when linking a referenced entity to the
	 * corresponding local wiki page.
	 */
	const TITLE_USAGE = 'T';

	/**
	 * Usage flag indicating that any and all aspects of the entity
	 * were (or may have been) used.
	 */
	const ALL_USAGE = 'X';

	/**
	 * Usage flag indicating that some aspect of the entity was changed
	 * which is not covered by any other usage flag (except "all"). That is,
	 * the specific usage flags together with the "other" flag are equivalent
	 * to the "all" flag ( S + T + L + O = X or rather O = X - S - T - L ).
	 */
	const OTHER_USAGE = 'O';

	/**
	 * List of all valid aspects. Only the array keys are used, the values are meaningless.
	 *
	 * @var null[]
	 */
	private static $aspects = array(
		self::SITELINK_USAGE => null,
		self::LABEL_USAGE => null,
		self::TITLE_USAGE => null,
		self::OTHER_USAGE => null,
		self::ALL_USAGE => null,
	);

	/**
	 * @var EntityId
	 */
	private $entityId;

	/**
	 * @var string
	 */
	private $aspect;

	/**
	 * @param EntityId $entityId
	 * @param string $aspect use the EntityUsage::XXX_USAGE constants
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( EntityId $entityId, $aspect ) {
		if ( !array_key_exists( $aspect, self::$aspects ) ) {
			throw new InvalidArgumentException( '$aspect must use one of the XXX_USAGE constants!' );
		}

		$this->entityId = $entityId;
		$this->aspect = $aspect;
	}

	/**
	 * @return string
	 */
	public function getAspect() {
		return $this->aspect;
	}

	/**
	 * @return EntityId
	 */
	public function getEntityId() {
		return $this->entityId;
	}

	/**
	 * @return string
	 */
	public function getIdentityString() {
		return $this->getEntityId()->getSerialization() . '#' . $this->getAspect();
	}

	/**
	 * @return string
	 */
	public function __toString() {
		return $this->getIdentityString();
	}

}
