<?php

namespace Wikibase\Repo\View;

use Message;
use SpecialPage;
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
class SectionEditLinkGenerator {

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

	/**
	 * Returns a toolbar with an edit link. In JavaScript, an enhanced toolbar will be initialized
	 * on top of the generated HTML.
	 *
	 * @since 0.2
	 *
	 * @param string $specialPageName the special page for the button
	 * @param string[] $specialPageUrlParams Additional URL params for the special page
	 * @param string $cssClassSuffix Suffix of the css class applied to the toolbar button node
	 * @param Message $message the message to show on the link
	 * @param bool $enabled can be set to false to display the button disabled
	 *
	 * @return string
	 */
	public function getHtmlForEditSection(
		$specialPageName,
		array $specialPageUrlParams,
		$cssClassSuffix,
		Message $message,
		$enabled = true
	) {

		$editUrl = $enabled ? $this->getEditUrl( $specialPageName, $specialPageUrlParams ) : null;
		$toolbarButton = $this->getToolbarButton( $cssClassSuffix, $message->text(), $editUrl );

		return $this->templateFactory->render( 'wikibase-toolbar-container',
			$this->templateFactory->render( 'wikibase-toolbar',
				'',
				$toolbarButton
			)
		);
	}

	/**
	 * Get an empty edit section container
	 * @return string
	 */
	public function getEmptyEditSectionContainer() {
		return $this->getEditSectionContainer( '' );
	}

	private function getEditSectionContainer( $content ) {
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

}
