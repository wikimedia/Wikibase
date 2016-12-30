<?php

namespace Wikibase\Repo\Specials\HTMLForm;

use HTMLTextField;
use Wikibase\StringNormalizer;

/**
 * Class representing generic alias field
 *
 * @license GPL-2.0+
 */
class HTMLAliasesField extends HTMLTextField {
	const DELIMITER = '|';

	/**
	 * @var StringNormalizer
	 */
	private $stringNormalizer;

	private $defaultParameters = [
		'placeholder-message' => 'wikibase-aliases-edit-placeholder',
		'label-message' => 'wikibase-aliases-edit-label',
	];

	/**
	 * Can be used without label and placeholder - has some predefined values. <br>
	 * Doesn't accept `filter-callback` parameter. <br>
	 * Doesn't accept `type` parameter. <br>
	 *
	 * @inheritdoc
	 *
	 * @see \HTMLForm There is detailed description of $info array
	 */
	public function __construct( array $params ) {
		if ( isset( $params['filter-callback'] ) ) {
			throw new \InvalidArgumentException(
				"Cannot use `filter-callback` for aliases field. It already has its own filtering"
			);
		}

		if ( isset( $params['type'] ) ) {
			throw new \InvalidArgumentException(
				"Can not use `type` for aliases field"
			);
		}

		$params['type'] = 'text';

		parent::__construct( array_merge( $this->defaultParameters, $params ) );

		$this->stringNormalizer = new StringNormalizer();
	}

	/**
	 * @param string $value
	 * @param array $alldata
	 * @return array
	 */
	public function filter( $value, $alldata ) {
		$aliases = explode( self::DELIMITER, $value );
		$aliases = array_map( [ $this->stringNormalizer, 'trimToNFC' ], $aliases );

		return array_values( array_filter( $aliases ) );
	}

	/**
	 * @param array $value
	 * @param array $alldata
	 * @return bool|\Message|string
	 */
	public function validate( $value, $alldata ) {
		if ( isset( $this->mParams['required'] )
			 && $this->mParams['required'] !== false
			 && $value === []
		) {
			return $this->msg( 'htmlform-required' );
		}

		return parent::validate( $value, $alldata );
	}

	/**
	 * @param array $value
	 * @return \OOUI\ActionFieldLayout|\OOUI\FieldLayout
	 */
	public function getOOUI( $value ) {
		$value = $this->arrayToString( $value );

		return parent::getOOUI( $value );
	}

	/**
	 * @param array $value
	 * @return string
	 */
	public function getTableRow( $value ) {
		$value = $this->arrayToString( $value );

		return parent::getTableRow( $value );
	}

	/**
	 * @param array $value
	 * @return string
	 */
	public function getDiv( $value ) {
		$value = $this->arrayToString( $value );

		return parent::getDiv( $value );
	}

	/**
	 * @param array $value
	 * @return string
	 */
	public function getRaw( $value ) {
		$value = $this->arrayToString( $value );

		return parent::getRaw( $value );
	}

	/**
	 * @param array $value
	 * @return string
	 */
	public function getVForm( $value ) {
		$value = $this->arrayToString( $value );

		return parent::getVForm( $value );
	}

	/**
	 * @param array $value
	 * @return string
	 */
	public function getInline( $value ) {
		$value = $this->arrayToString( $value );

		return parent::getInline( $value );
	}

	/**
	 * @param array $value
	 * @return string
	 */
	private function arrayToString( $value ) {
		return implode( self::DELIMITER, $value );
	}

}
