<?php declare( strict_types=1 );

namespace Wikibase\Repo\RestApi\Application\Validation;

/**
 * @license GPL-2.0-or-later
 */
interface DescriptionLanguageCodeValidator {

	public const CODE_INVALID_LANGUAGE = 'description-language-code-validator-code-invalid-language-code';

	public const CONTEXT_LANGUAGE = 'description-language-code-validator-context-language';

	public function validate( string $language ): ?ValidationError;

}
