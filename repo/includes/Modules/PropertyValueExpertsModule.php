<?php

namespace Wikibase\Repo\Modules;

use ResourceLoaderContext;
use Wikibase\Lib\DataTypeDefinitions;

/**
 * Module exporting map from property type to expert module name handling this type
 *
 * @note Tested via wikibase.experts.modules.tests.js
 */
class PropertyValueExpertsModule extends \ResourceLoaderModule {

	/**
	 * @var DataTypeDefinitions
	 */
	private $dataTypeDefinitions;

	public function __construct( DataTypeDefinitions $dataTypeDefinitions ) {
		$this->dataTypeDefinitions = $dataTypeDefinitions;
	}

	public function getScript( ResourceLoaderContext $context ) {
		$expertModuleMap = \XML::encodeJsVar( $this->dataTypeDefinitions->getExpertModules() );

		$js = <<<JS
module.exports = ( function () {
	'use strict';
	return $expertModuleMap;
}() );
JS;

		return $js;
	}

	public function getDependencies( ResourceLoaderContext $context = null ) {
		return array_values( $this->dataTypeDefinitions->getExpertModules() );
	}

}
