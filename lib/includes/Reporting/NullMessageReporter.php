<?php

namespace Wikibase\Lib\Reporting;

/**
 * Mock implementation of the MessageReporter interface that
 * does nothing with messages it receives.
 *
 * @since 0.4
 *
 * @license GPL-2.0+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 *
 * @codeCoverageIgnore
 */
class NullMessageReporter implements MessageReporter {

	/**
	 * @see MessageReporter::reportMessage
	 *
	 * @param string $message
	 */
	public function reportMessage( $message ) {
		// no-op
	}

}
