<?php

namespace Wikibase\Lib;

use DataValues\StringValue;
use Html;
use InvalidArgumentException;
use Title;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;

/**
 * Formats a StringValue as an HTML link.
 *
 * @since 0.5
 *
 * @todo Use MediaWiki renderer
 *
 * @licence GNU GPL v2+
 * @author Adrian Lang
 */
class CommonsLinkFormatter implements ValueFormatter {

	/**
	 * @var array HTML attributes to use on the generated <a> tags.
	 */
	private $attributes;

	/**
	 * @param FormatterOptions|null $options
	 */
	public function __construct( FormatterOptions $options = null ) {
		// @todo configure from options
		$this->attributes = array(
			'class' => 'extiw'
		);
	}

	/**
	 * @see ValueFormatter::format
	 *
	 * Formats the given commons file name as an HTML link
	 *
	 * @since 0.5
	 *
	 * @param StringValue $value The commons file name to turn into a link
	 *
	 * @throws InvalidArgumentException
	 * @return string HTML
	 */
	public function format( $value ) {
		if ( !( $value instanceof StringValue ) ) {
			throw new InvalidArgumentException( 'Data value type mismatch. Expected a StringValue.' );
		}

		$fileName = $value->getValue();
		// We are using NS_MAIN only because makeTitleSafe requires a valid namespace
		// We cannot use makeTitle because it does not secureAndSplit()
		$title = Title::makeTitleSafe( NS_MAIN, $fileName );
		if ( $title === null ) {
			return htmlspecialchars( $fileName );
		}

		$attributes = array_merge( $this->attributes, array(
			'href' => '//commons.wikimedia.org/wiki/File:' . $title->getPartialURL()
		) );
		$html = Html::element( 'a', $attributes, $title->getText() );

		return $html;
	}

}
