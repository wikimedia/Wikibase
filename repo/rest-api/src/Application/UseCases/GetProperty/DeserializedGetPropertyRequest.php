<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\UseCases\GetProperty;

use Wikibase\Repo\RestApi\Application\UseCases\DeserializedPropertyFieldsRequest;
use Wikibase\Repo\RestApi\Application\UseCases\DeserializedPropertyIdRequest;

/**
 * @license GPL-2.0-or-later
 */
interface DeserializedGetPropertyRequest extends DeserializedPropertyIdRequest, DeserializedPropertyFieldsRequest {
}
