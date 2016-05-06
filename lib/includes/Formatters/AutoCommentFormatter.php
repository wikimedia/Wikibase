<?php

namespace Wikibase\Lib;

use Language;
use Message;

/**
 * Formatter for machine-readable autocomments as generated by SummaryFormatter in the repo.
 *
 * @since 0.5
 *
 * @license GPL-2.0+
 * @author Brad Jorsch
 * @author Thiemo Mättig
 * @author Tobias Gritschacher
 * @author Daniel Kinzler
 */
class AutoCommentFormatter {

	/**
	 * @var Language
	 */
	private $language;

	/**
	 * @var string[]
	 */
	private $messagePrefixes;

	/**
	 * Local message lookup cache. The number of summary messages is limited,
	 * so this shouldn't grow beyond a few dozen entries.
	 *
	 * @var Message[]
	 */
	private $messages = array();

	/**
	 * @var LanguageNameLookup
	 */
	private $languageNameLookup;

	/**
	 * @param Language $language
	 * @param string[] $messagePrefixes Prefixes to try when constructing the message key from
	 *        the name given in the autocomment block. Typically something like
	 *        array( "wikibase-item", "wikibase-entity" ).
	 * @param LanguageNameLookup $languageNameLookup
	 */
	public function __construct(
		Language $language,
		array $messagePrefixes,
		LanguageNameLookup $languageNameLookup
	) {
		$this->language = $language;
		$this->messagePrefixes = $messagePrefixes;
		$this->languageNameLookup = $languageNameLookup;
	}

	/**
	 * Gets the summary message
	 *
	 * @param string $name
	 *
	 * @return Message|false
	 */
	private function getSummaryMessage( $name ) {
		if ( isset( $this->messages[$name] ) ) {
			return $this->messages[$name];
		}

		$found = false;
		foreach ( $this->messagePrefixes as $prefix ) {
			$key = "$prefix-summary-$name";
			$msg = wfMessage( $key )->inLanguage( $this->language );

			if ( $msg->exists() && !$msg->isDisabled() ) {
				$found = $msg;
				break;
			}
		}

		$this->messages[$name] = $found;
		return $this->messages[$name];
	}

	/**
	 * Pretty formatting of autocomments.
	 *
	 * @warning This method is used to parse and format autocomment strings from
	 * the revision history. It should remain compatible with any old autocomment
	 * strings that may be in the database.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FormatAutocomments
	 * @see docs/summaries.txt
	 *
	 * @param string $auto the autocomment unformatted
	 *
	 * @return string|null The localized summary (HTML), or null
	 */
	public function formatAutoComment( $auto ) {
		// Split $auto into a message name and parameters.
		// $auto should look like name:param1|param2|...
		if ( !preg_match( '/^([a-z\-]+)\s*(:\s*(.*?)\s*)?$/', $auto, $matches ) ) {
			return null;
		}

		// turn the args to the message into an array
		$args = isset( $matches[3] ) ? explode( '|', $matches[3] ) : array();

		// look up the message
		$msg = $this->getSummaryMessage( $matches[1] );

		if ( $msg === false ) {
			return null;
		}

		$args = $this->getSummaryParams( $matches[1], $args );

		// render the autocomment
		$auto = $msg->params( $args )->parse();
		return $auto;
	}

	/**
	 * Gets the potentially modified arguments for a message.
	 *
	 * @param string $name
	 * @param string[] $args
	 *
	 * @return string[]
	 */
	private function getSummaryParams( $name, array $args ) {
		if ( preg_match( '/^(wbsetlabel|wbsetdescription|wbsetaliases)/', $name ) ) {
			// wikibase-entity-summary-wbsetlabel-add
			// wikibase-entity-summary-wbsetlabel-set
			// wikibase-entity-summary-wbsetlabel-remove
			// wikibase-entity-summary-wbsetlabeldescriptionaliases
			// wikibase-entity-summary-wbsetdescription-add
			// wikibase-entity-summary-wbsetdescription-set
			// wikibase-entity-summary-wbsetdescription-remove
			// wikibase-entity-summary-wbsetaliases-set
			// wikibase-entity-summary-wbsetaliases-add-remove
			// wikibase-entity-summary-wbsetaliases-add
			// wikibase-entity-summary-wbsetaliases-remove
			$args[] = $this->languageNameLookup->getName( $args[1] );
		}
		return $args;
	}

	/**
	 * Wrapps a comment by applying the appropriate directionality markers and pre and/or postfix
	 * separators.
	 *
	 * @note This code should be kept in sync with what Linker::formatAutocomments does.
	 *
	 * @param boolean $pre True if there is text before the comment, so a prefix separator is needed.
	 * @param string $comment the localized comment, as returned by formatAutoComment()
	 * @param boolean $post True if there is text after the comment, so a postfix separator is needed.
	 *
	 * @return string
	 */
	public function wrapAutoComment( $pre, $comment, $post ) {
		if ( $pre ) {
			# written summary $presep autocomment (summary /* section */)
			$pre = wfMessage( 'autocomment-prefix' )->inLanguage( $this->language )->escaped();
		}
		if ( $post ) {
			# autocomment $postsep written summary (/* section */ summary)
			$comment .= wfMessage( 'colon-separator' )->inLanguage( $this->language )->escaped();
		}
		$comment = '<span class="autocomment">' . $comment . '</span>';
		$comment = $pre . $this->language->getDirMark()
			. '<span dir="auto">' . $comment;
		$comment .= '</span>';

		return $comment;
	}

}
