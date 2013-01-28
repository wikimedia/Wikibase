<?php

namespace Wikibase;
use Diff\IDiff;
use Diff\Diff;

/**
 * Class for changes that can be represented as a IDiff.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @since 0.1
 *
 * @file
 * @ingroup WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DiffChange extends ChangeRow {

	/**
	 * @since 0.1
	 *
	 * @return IDiff
	 * @throws \MWException
	 */
	public function getDiff() {
		$info = $this->getField( 'info' );

		if ( !array_key_exists( 'diff', $info ) ) {
			throw new \MWException( 'Cannot get the diff when it has not been set yet.' );
		}
		return $info['diff'];
	}

	/**
	 * @since 0.1
	 *
	 * @param IDiff $diff
	 */
	public function setDiff( IDiff $diff ) {
		$info = $this->hasField( 'info' ) ? $this->getField( 'info' ) : array();
		$info['diff'] = $diff;
		$this->setField( 'info', $info );
	}

	/**
	 * @since 0.1
	 *
	 * @param IDiff $diff
	 * @param array|null $fields
	 *
	 * @return DiffChange
	 */
	public static function newFromDiff( IDiff $diff, array $fields = null ) {
		$instance = new static(
			ChangesTable::singleton(),
			$fields,
			true
		);

		$instance->setDiff( $diff );

		return $instance;
	}

	/**
	 * Returns whether the change is empty.
	 * If it's empty, it can be ignored.
	 *
	 * @since 0.1
	 *
	 * @return boolean
	 */
	public function isEmpty() {
		if ( $this->hasField( 'info' ) ) {
			$info = $this->getField( 'info' );

			if ( array_key_exists( 'diff', $info ) ) {
				return $this->getDiff()->isEmpty();
			}
		}

		return true;
	}

	/**
	 * @see ChangeRow::serializeInfo()
	 *
	 * Overwritten to use the array representation of the diff.
	 *
	 * @since 0.4
	 * @param array $info
	 * @return string
	 */
	protected function serializeInfo( array $info ) {
		if ( isset( $info['diff'] ) && $info['diff'] instanceof \Diff\DiffOp ) {
			$info['diff'] = $this->diffToArray( $info['diff'] );
		}

		return parent::serializeInfo( $info );
	}

	/**
	 * @see ChangeRow::unserializeInfo()
	 *
	 * Overwritten to use the array representation of the diff.
	 *
	 * @since 0.4
	 * @param string $str
	 * @return array the info array
	 */
	protected function unserializeInfo( $str ) {
		$info = parent::unserializeInfo( $str );

		if ( isset( $info['diff'] ) && is_array( $info['diff'] ) ) {
			$info['diff'] = $this->arrayToDiff( $info['diff'] );
		}

		return $info;
	}

	/**
	 * Converts a Diff object into its array representation.
	 * May be overwritten by subclasses to provide special handling.
	 *
	 * @since 0.4
	 * @param \Diff\DiffOp $diff
	 * @return array
	 */
	protected function diffToArray( \Diff\DiffOp $diff ) {
		return $diff->toArray();
	}

	/**
	 * Converts an array structure into a Diff object.
	 * May be overwritten by subclasses to provide special handling.
	 *
	 * @since 0.4
	 * @param array $data
	 * @return mixed
	 */
	protected function arrayToDiff( array $data ) {
		static $factory = null;

		if ( $factory == null ) {
			$factory = new WikibaseDiffOpFactory();
		}

		return $factory->newFromArray( $data );
	}
}

class WikibaseDiffOpFactory extends \Diff\DiffOpFactory {
	public function newFromArray( array $diffOp ) {
		$this->assertHasKey( 'type', $diffOp );

		// see EntityDiff::getType() and ItemDiff::getType()
		if ( preg_match( '!^diff/(.*)$!', $diffOp['type'], $matches ) ) {
			$itemType = $matches[1];
			$this->assertHasKey( 'operations', $diffOp );

			$operations = $this->createOperations( $diffOp['operations'] );
			$diff = EntityDiff::newForType( $itemType, $operations );

			return $diff;
		}

		return parent::newFromArray( $diffOp );
	}

	/**
	 * Converts a list of diff operations represented by arrays into a list of
	 * DiffOp objects.
	 *
	 * @todo: pull this up into DiffOpFactory
	 *
	 * @param array $data the input data
	 * @return \Diff\DiffOp[] The diff ops
	 */
	protected function createOperations( array $data ) {
		$operations = array();

		foreach ( $data as $key => $operation ) {
			$operations[$key] = $this->newFromArray( $operation );
		}

		return $operations;
	}
}
