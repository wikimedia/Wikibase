<?php

namespace Wikibase\Validators;

use DataValues\IllegalValueException;
use InvalidArgumentException;
use ValueValidators\Result;
use ValueValidators\ValueValidator;

/**
 * The DataFieldValidator class allows the validation of a single field of a complex
 * DataValues object based on the DataValue's array representation.
 *
 * @since 0.4
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class DataFieldValidator implements ValueValidator {

	/**
	 * @var string
	 */
	protected $field;

	/**
	 * @var ValueValidator
	 */
	protected $validator;

	/**
	 * @param string|int     $field     The field on the target DataValue's array representation to check
	 * @param ValueValidator $validator The validator to apply to the given field
	 *
	 * @throws InvalidArgumentException
	 */
	public function __construct( $field, ValueValidator $validator ) {
		if ( !is_string( $field ) && !is_int( $field ) ) {
			throw new InvalidArgumentException( '$field need to be a string or int' );
		}

		$this->field = $field;

		$this->validator = $validator;
	}

	/**
	 * @see ValueValidator::validate()
	 *
	 * @param array $data The value to validate
	 *
	 * @return Result
	 * @throws InvalidArgumentException
	 * @throws IllegalValueException
	 */
	public function validate( $data ) {
		if ( !is_array( $data ) ) {
			//XXX: or should this just be reported as invalid?
			throw new InvalidArgumentException( "DataValue is not represented as an array" );
		}

		if ( !array_key_exists( $this->field, $data ) ) {
			//XXX: or should this just be reported as invalid?
			throw new InvalidArgumentException( "DataValue's array representation does not "
				. "contain the field " . $this->field );
		}

		$fieldValue = $data[$this->field];

		$result = $this->validator->validate( $fieldValue );

		// TODO: include the field name in the error report
		return $result;
	}

	/**
	 * @see ValueValidator::setOptions()
	 *
	 * @param array $options
	 */
	public function setOptions( array $options ) {
		// Do nothing. This method shouldn't even be in the interface.
	}
}
