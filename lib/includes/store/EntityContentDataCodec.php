<?php

namespace Wikibase\Store;

use InvalidArgumentException;
use MWContentSerializationException;

/**
 * A codec for use by EntityContent resp EntityHandler subclasses for the
 * serialization and deserialization of EntityContent objects.
 *
 * This class only deals with the representation of EntityContent as an
 * array structure, not with EntityContent objects.
 *
 * @since 0.5
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class EntityContentDataCodec {

	/**
	 * Returns the supported serialization formats as a list of strings.
	 *
	 * @return string[]
	 */
	public function getSupportedFormats() {
		return array(
			CONTENT_FORMAT_JSON,
			CONTENT_FORMAT_SERIALIZED
		);
	}

	/**
	 * @return string
	 */
	public function getDefaultFormat() {
		return CONTENT_FORMAT_JSON;
	}

	/**
	 * Returns a sanitized version of $format, or throws an exception if $format
	 * is invalid.
	 *
	 * @param string|null $format The requested format. If null, getDefaultFormat() will
	 * be consulted.
	 *
	 * @return string The format to actually use.
	 * @throws InvalidArgumentException If $format is not supported.
	 */
	private function sanitizeFormat( $format ) {
		if ( $format === null ) {
			$format = $this->getDefaultFormat();
		} elseif ( !in_array( $format, $this->getSupportedFormats() ) ) {
			throw new InvalidArgumentException( "Unsupported format: $format" );
		}

		return $format;
	}

	/**
	 * Encodes the given array structure as a blob using the given serialization format.
	 *
	 * @see EntityContent::toArray()
	 * @see EntityHandler::serializeContent()
	 *
	 * @param array $data A nested data array representing an EntityContent object.
	 * @param string|null $format The desired serialization format.
	 *
	 * @return string the blob
	 * @throws InvalidArgumentException If the format is not supported.
	 */
	public function encodeBlob( array $data, $format ) {
		$format = $this->sanitizeFormat( $format );

		switch ( $format ) {
			case CONTENT_FORMAT_SERIALIZED:
				$blob = serialize( $data );
				break;
			case CONTENT_FORMAT_JSON:
				$blob = json_encode( $data );
				break;
			default:
				throw new InvalidArgumentException( "Unsupported format: $format" );
		}

		return $blob;
	}

	/**
	 * Decodes the given blob into an array structure representing an EntityContent
	 * object.
	 *
	 * @see EntityHandler::unserializeContent
	 *
	 * @param string $blob The data blob to deserialize
	 * @param string|null $format The serialization format of $blob
	 *
	 * @return array An array representation of an EntityContent object
	 * @throws InvalidArgumentException If the format is not supported.
	 * @throws MWContentSerializationException If the blob could not be decoded.
	 */
	public function decodeBlob( $blob, $format ) {
		if ( !is_string( $blob ) ) {
			throw new InvalidArgumentException( '$blob must be a string' );
		}

		$format = $this->sanitizeFormat( $format );

		wfSuppressWarnings();
		switch ( $format ) {
			case CONTENT_FORMAT_SERIALIZED:
				$data = unserialize( $blob );
				break;
			case CONTENT_FORMAT_JSON:
				$data = json_decode( $blob, true );
				break;
			default:
				throw new InvalidArgumentException( "Unsupported format: $format" );
		}
		wfRestoreWarnings();

		if ( $data === false || $data === null ) {
			throw new MWContentSerializationException( "Failed to decode as $format" );
		}

		return $data;
	}

}
