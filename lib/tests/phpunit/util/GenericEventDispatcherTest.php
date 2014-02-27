<?php

namespace Wikibase\util\Test;

use Wikibase\DataModel\Entity\ItemId;
use Wikibase\util\GenericEventDispatcher;

/**
 * @covers Wikibase\util\GenericEventDispatcher
 *
 * @since 0.5
 *
 * @group Wikibase
 * @group WikibaseLib
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
class GenericEventDispatcherTest extends \PHPUnit_Framework_TestCase {

	public function testRegisterWatcher_failure() {
		$this->setExpectedException( 'InvalidArgumentException' );

		$watcher = $this->getMock( 'Wikibase\store\EntityStoreWatcher' );
		$dispatcher = new GenericEventDispatcher( 'Wikibase\store\FooBar' );

		// should fail because $watcher doesn't implement FooBar
		$dispatcher->registerWatcher( $watcher );
	}

	public function testDispatch() {
		$q12 = new ItemId( 'Q12' );

		$watcher = $this->getMock( 'Wikibase\store\EntityStoreWatcher' );
		$watcher->expects( $this->once() )
			->method( 'entityDeleted' )
			->with( $this->equalTo( $q12 ) );

		$dispatcher = new GenericEventDispatcher( 'Wikibase\store\EntityStoreWatcher' );

		// check register & dispatch
		$handle = $dispatcher->registerWatcher( $watcher );
		$dispatcher->dispatch( 'entityDeleted', $q12 );

		// check unregister
		$dispatcher->unregisterWatcher( $handle );
		$dispatcher->dispatch( 'entityDeleted', new ItemId( 'Q13' ) );
	}

}
