<?php

namespace Wikibase\DataModel\Services\Statement\Filter;

use Wikibase\DataModel\Statement\Statement;
use Wikibase\DataModel\Statement\StatementFilter;

/**
 * An unconditional statement filter that always accepts all statements, and never rejects a
 * statement. This acts as a null implementation in cases where filtering is supported but not
 * needed.
 *
 * @since 3.2
 *
 * @licence GNU GPL v2+
 * @author Thiemo Mättig
 */
class NullStatementFilter implements StatementFilter {

	/**
	 * @see StatementFilter::statementMatches
	 *
	 * @param Statement $statement
	 *
	 * @return bool
	 */
	public function statementMatches( Statement $statement ) {
		return true;
	}

}
