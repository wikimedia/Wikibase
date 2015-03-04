<?php

namespace Wikibase\Repo\View;

use Message;
use SpecialPage;
use Wikibase\DataModel\Entity\EntityId;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\DataModel\Statement\Statement;
use Wikibase\Template\TemplateFactory;

/**
 * Generates HTML for a section edit link
 *
 * @since 0.5
 * @licence GNU GPL v2+
 *
 * @author H. Snater < mediawiki@snater.com >
 * @author Daniel Werner
 * @author Daniel Kinzler
 */
class ToolbarEditSectionGenerator implements EditSectionGenerator {

	/**
	 * @var TemplateFactory
	 */
	private $templateFactory;

	/**
	 * @param TemplateFactory $templateFactory
	 */
	public function __construct( TemplateFactory $templateFactory ) {
		$this->templateFactory = $templateFactory;
	}

	public function getSiteLinksEditSection( EntityId $entityId = null ) {
		$specialPageUrlParams = array();

		if ( $entityId !== null ) {
			$specialPageUrlParams[] = $entityId->getSerialization();
		}

		return $this->getHtmlForEditSection(
			'SetSiteLink',
			$specialPageUrlParams
		);
	}

	/**
	 * Returns HTML allowing to edit the section containing label, description and aliases.
	 *
	 * @param string $languageCode
	 * @param EntityId|null $entityId
	 * @return string
	 */
	public function getLabelDescriptionAliasesEditSection( $languageCode, EntityId $entityId = null ) {
		if ( $entityId === null ) {
			return '';
		}
		return $this->getHtmlForEditSection(
			'SetLabelDescriptionAliases',
			array( $entityId->getSerialization(), $languageCode )
		);
	}

	/**
	 * Returns a toolbar with an edit link. In JavaScript, an enhanced toolbar will be initialized
	 * on top of the generated HTML.
	 *
	 * @since 0.2
	 *
	 * @param string $specialPageName the special page for the button
	 * @param string[] $specialPageUrlParams Additional URL params for the special page
	 *
	 * @return string
	 */
	private function getHtmlForEditSection(
		$specialPageName,
		array $specialPageUrlParams
	) {

		$editUrl = $this->getEditUrl( $specialPageName, $specialPageUrlParams );
		$toolbarButton = $this->getToolbarButton( 'edit', wfMessage( 'wikibase-edit' )->text(), $editUrl );

		return $this->getToolbarContainer(
			$this->templateFactory->render( 'wikibase-toolbar',
				'',
				$toolbarButton
			)
		);
	}

	private function getToolbarContainer( $content ) {
		return $this->templateFactory->render( 'wikibase-toolbar-container', $content );
	}

	/**
	 * Get the Url to an edit special page
	 *
	 * @param string $specialPageName The special page to link to
	 * @param string[] $specialPageUrlParams Additional URL params for the special page
	 *
	 * @return string
	 */
	private function getEditUrl( $specialPageName, array $specialPageUrlParams ) {
		if ( empty( $specialPageUrlParams ) ) {
			return null;
		}

		$subPage = implode( '/', array_map( 'wfUrlencode', $specialPageUrlParams ) );
		$specialPageTitle = SpecialPage::getTitleFor( $specialPageName, $subPage );

		return $specialPageTitle->getLocalURL();
	}

	/**
	 * @param string $cssClassSuffix
	 * @param string $buttonLabel the message to show on the toolbar button link
	 * @param string|null $editUrl The edit url
	 *
	 * @return string
	 */
	private function getToolbarButton( $cssClassSuffix, $buttonLabel, $editUrl = null ) {
		if ( $editUrl === null ) {
			return '';
		}

		return $this->templateFactory->render(
			'wikibase-toolbar-bracketed',
			$this->templateFactory->render(
				'wikibase-toolbar-button',
				'wikibase-toolbar-button-' . $cssClassSuffix,
				$editUrl,
				$buttonLabel
			)
		);
	}

	public function getAddStatementToGroupSection( PropertyId $propertyId, EntityId $entityId = null ) {
		// This is just an empty toolbar wrapper. It's used as a marker to the JavaScript so that it places
		// the toolbar at the right position in the DOM. Without this, the JavaScript would just append the
		// toolbar to the end of the element.
		// TODO: Create special pages, link to them
		return $this->getToolbarContainer( '' );
	}

	public function getStatementEditSection( Statement $statement ) {
		// This is just an empty toolbar wrapper. It's used as a marker to the JavaScript so that it places
		// the toolbar at the right position in the DOM. Without this, the JavaScript would just append the
		// toolbar to the end of the element.
		// TODO: Create special pages, link to them
		return $this->getToolbarContainer( '' );
	}

}
