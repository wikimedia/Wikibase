<?php

namespace Wikibase\Repo\ChangeOpDeserializers;

use Wikibase\ChangeOp\ChangeOpException;
use Wikibase\ChangeOp\ChangeOp;
use Wikibase\ChangeOp\ChangeOps;
use Wikibase\Repo\ChangeOp\ChangeOpDeserializer;

class PropertyChangeOpDeserializer implements ChangeOpDeserializer {

	/**
	 * @param array $changeRequest
	 *
	 * @return ChangeOp
	 */
	public function createEntityChangeOp( array $changeRequest ) {
		$changeOps = new ChangeOps();

		if ( array_key_exists( 'labels', $changeRequest ) ) {
		}

		if ( array_key_exists( 'descriptions', $changeRequest ) ) {
		}

		if ( array_key_exists( 'aliases', $changeRequest ) ) {
		}

		if ( array_key_exists( 'claims', $changeRequest ) ) {
		}

		return $changeOps;
	}

}
