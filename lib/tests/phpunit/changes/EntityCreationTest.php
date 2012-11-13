<?php

namespace Wikibase\Test;
use Wikibase\EntityCreation;
use Wikibase\Entity;

/**
 * Tests for the Wikibase\EntityRefresh class.
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
 * @file
 * @since 0.1
 *
 * @ingroup WikibaseLib
 * @ingroup Test
 *
 * @group Wikibase
 * @group WikibaseLib
 * @group WikibaseChange
 *
 * @licence GNU GPL v2+
 * @author Katie Filbert < aude.wiki@gmail.com >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class EntityCreationTest extends \MediaWikiTestCase {

	protected function getClass() {
		return 'Wikibase\EntityCreation';
	}

	public function newFromEntitiesProvider() {
		return TestChanges::getEntities();
	}

	/**
	 * @dataProvider newFromEntitiesProvider
	 *
	 * @param \Wikibase\Entity $entity
	 */
	public function testNewFromEntity( Entity $entity ) {
		$class = $this->getClass();
		$entityCreation = $class::newFromEntity( $entity );
		$this->assertInstanceOf( $class, $entityCreation );
		$this->assertEquals( $entity, $entityCreation->getEntity() );
	}

}
