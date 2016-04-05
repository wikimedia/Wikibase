<?php

namespace Wikibase\Repo\Test;

use DataTypes\DataType;
use DataTypes\DataTypeFactory;
use InvalidArgumentException;
use PHPUnit_Framework_TestCase;
use User;
use Wikibase\DataModel\Entity\Property;
use Wikibase\DataModel\Entity\PropertyId;
use Wikibase\EntityRevision;
use Wikibase\Lib\Store\EntityRevisionLookup;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Lib\Store\StorageException;
use Wikibase\Repo\PropertyDataTypeChanger;

/**
 * @covers Wikibase\Repo\PropertyDataTypeChanger
 *
 * @since 0.5
 *
 * @group WikibaseRepo
 * @group Wikibase
 *
 * @license GPL-2.0+
 * @author Marius Hoch
 */
class PropertyDataTypeChangerTest extends PHPUnit_Framework_TestCase {

	public function testChangeDataType_success() {
		$propertyId = new PropertyId( 'P42' );

		$expectedProperty = new Property( $propertyId, null, 'shinydata' );

		$entityStore = $this->getMock( EntityStore::class );
		$entityStore->expects( $this->once() )
			->method( 'saveEntity' )
			->with(
				$expectedProperty,
				'Changed data type from rustydata to shinydata',
				$this->isInstanceOf( User::class ),
				EDIT_UPDATE, 6789
			)
			->will( $this->returnValue( new EntityRevision( $expectedProperty, 6790 ) ) );

		$propertyDataTypeChanger = $this->getPropertyDataTypeChanger( $entityStore );
		$propertyDataTypeChanger->changeDataType( $propertyId, $this->getMock( User::class ), 'shinydata' );
	}

	public function testChangeDataType_propertyNotFound() {
		$propertyId = new PropertyId( 'P43' );

		$entityStore = $this->getMock( EntityStore::class );

		$propertyDataTypeChanger = $this->getPropertyDataTypeChanger( $entityStore );

		$this->setExpectedException(
			StorageException::class,
			"Could not load property: P43"
		);
		$propertyDataTypeChanger->changeDataType( $propertyId, $this->getMock( User::class ), 'shinydata' );
	}

	public function testChangeDataType_saveFailed() {
		$propertyId = new PropertyId( 'P42' );

		$expectedProperty = new Property( $propertyId, null, 'shinydata' );
		$storageException = new StorageException( 'whatever' );

		$entityStore = $this->getMock( EntityStore::class );
		$entityStore->expects( $this->once() )
			->method( 'saveEntity' )
			->with(
				$expectedProperty,
				'Changed data type from rustydata to shinydata',
				$this->isInstanceOf( User::class ),
				EDIT_UPDATE, 6789
			)
			->will( $this->throwException( $storageException ) );

		$propertyDataTypeChanger = $this->getPropertyDataTypeChanger( $entityStore );

		$this->setExpectedException( StorageException::class );
		$propertyDataTypeChanger->changeDataType( $propertyId, $this->getMock( User::class ), 'shinydata' );
	}

	public function testChangeDataType_mismatchingDataValueTypes() {
		$propertyId = new PropertyId( 'P42' );

		$entityStore = $this->getMock( EntityStore::class );

		$propertyDataTypeChanger = $this->getPropertyDataTypeChanger( $entityStore );

		$this->setExpectedException(
			InvalidArgumentException::class,
			"New and old data type must have the same data value type."
		);
		$propertyDataTypeChanger->changeDataType( $propertyId, $this->getMock( User::class ), 'otherdatatype' );
	}

	private function getPropertyDataTypeChanger( EntityStore $entityStore ) {
		$entityRevisionLookup = $this->getMock( EntityRevisionLookup::class );

		$entityRevisionLookup->expects( $this->once() )
			->method( 'getEntityRevision' )
			->will( $this->returnCallback( function( PropertyId $propertyId ) {
				if ( $propertyId->getSerialization() === 'P42' ) {
					$property = new Property(
						new PropertyId( 'P42' ),
						null,
						'rustydata'
					);

					return new EntityRevision( $property, 6789, '20151015195144' );
				} else {
					return null;
				}
			} ) );

		return new PropertyDataTypeChanger( $entityRevisionLookup, $entityStore, $this->getDataTypeFactory() );
	}

	private function getDataTypeFactory() {
		$dataTypes = [];
		$dataTypes[] = new DataType( 'rustydata', 'kittens' );
		$dataTypes[] = new DataType( 'shinydata', 'kittens' );
		$dataTypes[] = new DataType( 'otherdatatype', 'puppies' );

		return DataTypeFactory::newFromTypes( $dataTypes );
	}

}
