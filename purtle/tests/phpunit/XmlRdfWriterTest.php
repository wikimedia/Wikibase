<?php

namespace Wikimedia\Purtle\Tests;

use Wikimedia\Purtle\XmlRdfWriter;
use Wikimedia\Purtle\RdfWriter;

/**
 * @covers Wikimedia\Purtle\XmlRdfWriter
 * @covers Wikimedia\Purtle\RdfWriterBase
 *
 * @group Purtle
 * @group RdfWriter
 *
 * @license GNU GPL v2+
 * @author Daniel Kinzler
 */
class XmlRdfWriterTest extends RdfWriterTestBase {

	protected function getFileSuffix() {
		return 'rdf';
	}

	/**
	 * @return RdfWriter
	 */
	protected function newWriter() {
		return new XmlRdfWriter();
	}

}
