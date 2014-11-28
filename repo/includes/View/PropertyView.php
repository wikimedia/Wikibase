<?php

namespace Wikibase\Repo\View;

use DataTypes\DataType;
use InvalidArgumentException;
use Wikibase\DataModel\Entity\Property;
use Wikibase\EntityRevision;
use Wikibase\Repo\WikibaseRepo;
use Wikibase\Repo\View\FingerprintView;
use Wikibase\Repo\View\ClaimsView;
use Language;

/**
 * Class for creating views for Property instances.
 * For the Property this basically is what the Parser is for WikitextContent.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 * @author H. Snater < mediawiki@snater.com >
 */
class PropertyView extends EntityView {

	/**
	 * @var bool
	 */
	private $displayStatementsOnProperties;

	/**
	 * @param FingerprintView $fingerprintView
	 * @param ClaimsView $claimsView
	 * @param Language $language
	 * @param bool $displayStatementsOnProperties
	 */
	public function __construct( FingerprintView $fingerprintView, ClaimsView $claimsView, Language $language, $displayStatementsOnProperties ) {
		parent::__construct($fingerprintView, $claimsView, $language);

		$this->displayStatementsOnProperties = $displayStatementsOnProperties;
	}

	/**
	 * @see EntityView::getMainHtml
	 */
	public function getMainHtml( EntityRevision $entityRevision, array $entityInfo,
		$editable = true
	) {
		wfProfileIn( __METHOD__ );

		$property = $entityRevision->getEntity();

		if ( !( $property instanceof Property ) ) {
			throw new InvalidArgumentException( '$entityRevision must contain a Property.' );
		}

		$html = parent::getMainHtml( $entityRevision, $entityInfo, $editable );
		$html .= $this->getHtmlForDataType( $this->getDataType( $property ) );

		if ( $this->displayStatementsOnProperties ) {
			$html .= $this->claimsView->getHtml(
				$property->getStatements()->toArray(),
				$entityInfo
			);
		}

		$footer = wfMessage( 'wikibase-property-footer' );

		if ( !$footer->isBlank() ) {
			$html .= "\n" . $footer->parse();
		}

		wfProfileOut( __METHOD__ );
		return $html;
	}

	private function getDataType( Property $property ) {
		return WikibaseRepo::getDefaultInstance()->getDataTypeFactory()
			->getType( $property->getDataTypeId() );
	}

	/**
	 * Builds and returns the HTML representing a property entity's data type information.
	 *
	 * @param DataType $dataType the data type to render
	 *
	 * @return string
	 */
	private function getHtmlForDataType( DataType $dataType ) {
		return wfTemplate( 'wb-section-heading',
			wfMessage( 'wikibase-propertypage-datatype' )->escaped(),
			'datatype'
		)
		. wfTemplate( 'wb-property-datatype',
			htmlspecialchars( $dataType->getLabel( $this->language->getCode() ) )
		);
	}

}
