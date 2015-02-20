<?php

// This is a IDE helper to understand class aliasing.
// It should not be included anywhere.
// Actual aliasing happens in the entry point using class_alias.

namespace { throw new Exception( 'This code is not meant to be executed' ); }

namespace Wikibase\DataModel\Claim {

	/**
	 * @deprecated since 3.0.0, use the base class instead.
	 */
	class Claim extends \Wikibase\DataModel\Statement\Statement {}

}
