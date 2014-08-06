<?php

namespace Wikibase;

/**
 * Contains templates commonly used in server-side output generation and client-side JavaScript
 * processing.
 *
 * @since 0.2
 *
 * @licence GNU GPL v2+
 * @author H. Snater <mediawiki@snater.com>
 *
 * @return array templates
 */

return call_user_func( function() {
	$templates = array();

	$templates['wb-entity'] =
// container reserved for widgets, will be displayed on the right side if there is space
// TODO: no point in inserting this here, is there? Should be generated in JS!
<<<HTML
<div id="wb-$1-$2" class="wb-entity wb-$1" lang="$3" dir="$4">$5</div>
<div id="wb-widget-container-$2" class="wb-widget-container"></div>
HTML;


	$templates['wb-entity-header-separator'] =
<<<HTML
<hr class="wb-hr" />
HTML;

	$templates['wb-entity-toc'] =
<<<HTML
<div id="toc" class="toc wb-toc">
	<div id="toctitle">
		<h2>$1</h2>
	</div>
	<ul>$2</ul>
</div>
HTML;

// $1: Index of the section
// $2: Target of the link
// $3: Text of the link
	$templates['wb-entity-toc-section'] =
<<<HTML
<li class="toclevel-1 tocsection-$1"><a href="#$2"><span class="toctext">$3</span></a></li>
HTML;

// $1: Text of the heading.
// $2: Optional ID for the heading.
	$templates['wb-section-heading'] =
<<<HTML
<h2 class="wb-section-heading" dir="auto" id="$2">$1</h2>
HTML;

	$templates['wb-section-heading-sitelinks'] =
<<<HTML
<h2 class="wb-section-heading wb-sitelinks-heading" dir="auto" id="$2">$1</h2>
HTML;

	$templates['wb-claimgrouplistview'] =
<<<HTML
<div class="wb-claimgrouplistview">
	<div class="wb-claimlists">$1<!-- [0,*] wb-claimlist--></div>
	$2<!-- {1} wb-toolbar -->
</div>
HTML;

	$templates['wb-claimgrouplistview-groupname'] =
<<<HTML
<div class="wb-claimgrouplistview-groupname">
	<div class="wb-claim-name" dir="auto">$1</div>
</div>
HTML;

	$templates['wb-claimlistview'] =
<<<HTML
<div class="wb-claimlistview">
	<div class="wb-claims" id="$3">
		$1 <!-- [0,*] wb-claim|wb-statement -->
	</div>
	$2 <!-- [0,*] wb-toolbar -->
</div>
HTML;

	$templates['wb-claim'] =
<<<HTML
<div class="wb-claimview">
	<div class="wb-claim wb-claim-$1">
		<div class="wb-claim-mainsnak" dir="auto">
			$2 <!-- wb-snak (Main Snak) -->
		</div>
		<div class="wb-claim-qualifiers">$3</div>
	</div>
	$4 <!-- wikibase-toolbar -->
</div>
HTML;

	// TODO: .wb-snakview should not be part of the template; check uses of that class and move them
	// to .wb-snak
	$templates['wb-snak'] =
// This template is not only used for PropertyValueSnak Snaks but also for other Snaks without a
// value which may display some message in the value node.
<<<HTML
<div class="wb-snak wb-snakview">
	<div class="wb-snak-property-container">
		<div class="wb-snak-property" dir="auto">$1</div>
	</div>
	<div class="wb-snak-value-container" dir="auto">
		<div class="wb-snak-typeselector"></div>
		<div class="wb-snak-value $2">$3</div>
	</div>
</div>
HTML;

	// TODO: This template should be split up and make use of the wb-claim template. $4 is used for
	// the non-JS toolbar to attach to. This parameter should be removed.
	$templates['wb-statement'] =
<<<HTML
<div class="wb-statement wb-statementview wb-claimview">
	<div class="wb-statement-rank">$1</div>
	<div class="wb-claim wb-claim-$2">
		<div class="wb-claim-mainsnak" dir="auto">
			$3 <!-- wb-snak (Main Snak) -->
		</div>
		<div class="wb-claim-qualifiers wb-statement-qualifiers">$4</div>
	</div>
	$5 <!-- wikibase-toolbar -->
	<div class="wb-statement-references-heading">$6</div>
	<div class="wb-statement-references">
		$7 <!-- [0,*] wb-referenceview -->
	</div>
</div>
HTML;

	$templates['wb-rankselector'] =
<<<HTML
<div class="wb-rankselector $1">
	<span class="ui-icon ui-icon-rankselector $2" title="$3"></span>
</div>
HTML;

	$templates['wb-referenceview'] =
<<<HTML
<div class="wb-referenceview $1">
	<div class="wb-referenceview-heading"></div>
	<div class="wb-referenceview-listview">$2<!-- [0,*] wb-snaklistview --></div>
</div>
HTML;


	$templates['wb-listview'] =
<<<HTML
<div class="wb-listview">$1</div>
HTML;

	$templates['wb-snaklistview'] =
<<<HTML
<div class="wb-snaklistview">
	<div class="wb-snaklistview-listview">$1<!-- wb-listview --></div>
</div>
HTML;

	$templates['wb-label'] =
// add an h1 for displaying the entity's label; the actual firstHeading is being hidden by
// css since the original MediaWiki DOM does not represent a Wikidata entity's structure
// where the combination of label and description is the unique "title" of an entity which
// should not be semantically disconnected by having elements in between, like siteSub,
// contentSub and jump-to-nav
<<<HTML
<h1 id="wb-firstHeading-$1" class="wb-firstHeading wb-value-row">$2</h1>
HTML;

	$templates['wb-description'] =
<<<HTML
<div class="wb-property-container wb-value-row wb-description" dir="auto">
	<div class="wb-property-container-key" title="description"></div>
	$1
</div>
HTML;

	$templates['wb-property'] =
<<<HTML
<span class="wb-property-container-value wb-value-container">
	<span class="wb-value $1" dir="auto">$2</span>
	$3
</span>
HTML;

	$templates['wb-property-value-supplement'] =
<<<HTML
<span class="wb-value-supplement">$1</span>
HTML;

	$templates['wb-aliases-wrapper'] =
<<<HTML
<div class="wb-aliases $1">
	<div class="wb-gridhelper">
		<span class="wb-aliases-label $2">$3</span>
		$4
	</div>
</div>
HTML;

	$templates['wb-aliases'] =
<<<HTML
<ul class="wb-aliases-container">$1</ul>
HTML;

	$templates['wb-alias'] =
<<<HTML
<li class="wb-aliases-alias">$1</li>
HTML;

	$templates['wb-editsection'] =
<<<HTML
<$1 class="wb-editsection">$2</$1>
HTML;

	$templates['wikibase-toolbar'] =
<<<HTML
<span class="wikibase-toolbar $1">$2</span>
HTML;

	$templates['wikibase-toolbareditgroup'] =
<<<HTML
<span class="wikibase-toolbareditgroup $1">[$2]</span>
HTML;

	$templates['wikibase-toolbarbutton'] =
<<<HTML
<a href="$2" class="wikibase-toolbarbutton">$1</a>
HTML;

	$templates['wikibase-toolbarbutton-disabled'] =
<<<HTML
<a href="#" class="wikibase-toolbarbutton wikibase-toolbarbutton-disabled" tabindex="-1">$1</a>
HTML;

	$templates['wb-terms-heading'] =
<<<HTML
<h2 id="wb-terms" class="wb-terms-heading">$1</h2>
HTML;

	$templates['wb-terms-table'] =
<<<HTML
<table class="wb-terms">
	<colgroup>
		<col class="wb-terms-language" />
		<col class="wb-terms-term" />
		<col class="wb-editsection" />
	</colgroup>
	<tbody>$1</tbody>
</table>
HTML;

// make the wb-value-row a wb-property-container to start with the edit button stuff
// $1: language-code
	$templates['wb-term'] =
<<<HTML
<tr class="wb-terms-label wb-terms-$1">
	<td class="wb-terms-language wb-terms-language-$1" rowspan="2"><a href="$9">$2</a><!-- language name --></td>
	<td class="wb-terms-label wb-terms-label-$1 wb-value wb-value-lang-$1 $7">$3<!-- label --></td>
	<td class="wb-editsection">$5<!-- label toolbar --></td>
</tr>
<tr class="wb-terms-description wb-terms-$1">
	<td class="wb-terms-description wb-terms-description-$1 wb-value wb-value-lang-$1 $8">$4<!-- description --></td>
	<td class="wb-editsection">$6<!-- description toolbar --></td>
</tr>
HTML;

	$templates['wb-sitelinks-table'] =
<<<HTML
<table class="wb-sitelinks" data-wb-sitelinks-group="$4">
	<colgroup>
		<col class="wb-sitelinks-sitename" />
		<col class="wb-sitelinks-siteid" />
		<col class="wb-sitelinks-link" />
		<col class="wb-editsection" />
	</colgroup>
	<thead>
		$1 <!-- wb-sitelinks-thead -->
	</thead>
	<tbody>
		$2 <!-- [0,*] wb-sitelink -->
	</tbody>
	<tfoot>
		$3 <!-- wb-sitelinks-tfoot -->
	</tfoot>
</table>
HTML;

	$templates['wb-sitelinks-thead'] =
<<<HTML
<tr class="wb-sitelinks-columnheaders">
	<th class="wb-sitelinks-sitename">$1</th>
	<th class="wb-sitelinks-siteid">$2</th>
	<th class="wb-sitelinks-link">$3</th>
	<th class="unsortable"></th>
</tr>
HTML;

	$templates['wb-sitelinks-tfoot'] =
<<<HTML
<tr>
	<td colspan="3" class="wb-sitelinks-placeholder">$1</td>
	$2 <!-- wb-editsection( param1: 'td' ) -->
</tr>
HTML;

	$templates['wb-sitelink'] =
<<<HTML
<tr class="wb-sitelinks-$7">
	<td class="wb-sitelinks-sitename wb-sitelinks-sitename-$7" lang="$1" dir="auto">$2</td>
	<td class="wb-sitelinks-siteid wb-sitelinks-siteid-$7">$3</td>
	<td class="wb-sitelinks-link wb-sitelinks-link-$7" lang="$1"><span class="wb-sitelinks-badges">$8</span><span class="wb-sitelinks-page"><a href="$4" hreflang="$1" dir="auto">$5</a></span></td>
	$6
</tr>
HTML;

	$templates['wb-sitelink-unknown'] =
<<<HTML
<tr class="wb-sitelinks-site-unknown">
	<td class="wb-sitelinks-sitename wb-sitelinks-sitename-unknown"></td>
	<td class="wb-sitelinks-siteid wb-sitelinks-siteid-unknown">$2</td>
	<td class="wb-sitelinks-link wb-sitelinks-link-unknown">$3</td>
	$4
</tr>
HTML;

	$templates['wb-sitelink-new'] =
<<<HTML
<tr>
	<td colspan="2" class="wb-sitelinks-sitename"></td>
	<td class="wb-sitelinks-link"><span class="wb-sitelinks-badges"></span><span class="wb-sitelinks-page"></span></td>
	<td></td><!-- cell for toolbar -->
</tr>
HTML;

	$templates['wb-property-datatype'] =
<<<HTML
<div class="wb-datatype">
	<div class="wb-datatype-value">$1</div>
</div>
HTML;

	return $templates;
} );
