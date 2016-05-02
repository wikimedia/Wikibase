<?php

namespace Wikibase\View;

use Wikibase\DataModel\Term\AliasesProvider;
use Wikibase\DataModel\Term\DescriptionsProvider;
use Wikibase\DataModel\Term\LabelsProvider;

/**
 * Generates HTML to display terms of an entity in a list.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Adrian Heine <adrian.heine@wikimedia.de>
 */
interface TermsListView {

	/**
	 * @param LabelsProvider $labelsProvider
	 * @param DescriptionsProvider $descriptionsProvider
	 * @param AliasesProvider|null $aliasesProvider
	 * @param string[] $languageCodes The languages in which terms should be shown
	 *
	 * @return string HTML
	 */
	public function getHtml(
		LabelsProvider $labelsProvider,
		DescriptionsProvider $descriptionsProvider,
		AliasesProvider $aliasesProvider = null,
		array $languageCodes
	);

}
