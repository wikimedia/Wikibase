<?php

namespace Wikibase\RDF;

/**
 * RdfWriter implementation for generating Turtle output.
 *
 * @license GPL 2+
 * @author Daniel Kinzler
 */
class TurtleRdfWriter extends N3RdfWriterBase {

	public function __construct( $role = parent::DOCUMENT_ROLE, BNodeLabeler $labeler = null, N3Quoter $quoter = null ) {
		parent::__construct( $role, $labeler, $quoter );
		$this->transitionTable[self::STATE_OBJECT] = array(
			self::STATE_DOCUMENT => " .\n",
			self::STATE_SUBJECT => " .\n\n",
			self::STATE_PREDICATE => " ;\n\t",
			self::STATE_OBJECT => " ,\n\t\t",
			self::STATE_DRAIN => " .\n",
		);
		$this->transitionTable[self::STATE_DOCUMENT][self::STATE_SUBJECT] = "\n";
		$this->transitionTable[self::STATE_SUBJECT][self::STATE_PREDICATE] = " ";
		$this->transitionTable[self::STATE_PREDICATE][self::STATE_OBJECT] = " ";
	}

	protected function writePrefix( $prefix, $uri ) {
		$this->write( "@prefix $prefix: <" . $this->quoter->escapeIRI( $uri ) . "> .\n" );
	}

	protected function writeSubject( $base, $local = null ) {
		if( $local !== null ) {
			$this->write( "$base:$local" );
		} else {
			$this->writeIRI( $base );
		}
	}

	protected function writePredicate( $base, $local = null ) {
		if( $base === 'a' ) {
			$this->write( 'a' );
			return;
		}
		if( $local !== null ) {
			$this->write( "$base:$local" );
		} else {
			$this->writeIRI( $base );
		}
	}

	protected function writeResource( $base, $local = null ) {
		if( $local !== null) {
			$this->write( "$base:$local" );
		} else {
			$this->writeIRI( $base );
		}
	}

// 	protected function writeValue( $value, $typeBase = null, $typeLocal = null  ) {
// 		//TODO: shorthand form for xsd:integer|decimal|double|boolean
// 		parent::writeValue( $value, $typeBase, $typeLocal );
// 	}

	/**
	 * @param string $role
	 * @param BNodeLabeler $labeler
	 *
	 * @return RdfWriterBase
	 */
	protected function newSubWriter( $role, BNodeLabeler $labeler ) {
		$writer = new self( $role, $labeler, $this->quoter );

		return $writer;
	}

	/**
	 * @return string a MIME type
	 */
	public function getMimeType() {
		return 'text/turtle; charset=UTF-8';
	}



}
