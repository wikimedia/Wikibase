<?php

namespace Wikibase;

use Language;
use LogicException;

/**
 * A Summary object can be used to build complex, translatable summaries.
 *
 * @since 0.1, major refactoring in 0.4
 *
 * @licence GNU GPL v2+
 * @author John Erling Blad
 * @author Daniel Kinzler
 * @author Tobias Gritschacher < tobias.gritschacher@wikimedia.de >
 */
class Summary {

	/**
	 * @var string
	 */
	protected $moduleName;

	/**
	 * @var string
	 */
	protected $actionName;

	/**
	 * @var string
	 */
	protected $languageCode;

	/**
	 * @var array
	 */
	protected $commentArgs;

	/**
	 * @var array
	 */
	protected $summaryArgs;

	/**
	 * @var string
	 */
	protected $userSummary;

	/**
	 * Indicates a specific type of formatting
	 */
	const USE_COMMENT = 2;
	const USE_SUMMARY = 4;
	const USE_ALL = 6;

	/**
	 * Constructs a new Summary
	 *
	 * @since 0.4
	 *
	 * @param string $moduleName The module part of the auto comment
	 * @param string $actionName The action part of the auto comment
	 * @param string $languageCode The language to use as the second auto comment argument
	 * @param array $commentArgs The arguments to the auto comment
	 * @param array $summaryArgs The arguments to the auto summary
	 */
	public function __construct( $moduleName = null, $actionName = null, $languageCode = null,
		$commentArgs = array(), $summaryArgs = array()
	) {
		$this->moduleName = $moduleName;
		$this->actionName = $actionName;
		$this->languageCode = $languageCode === null ? null : (string)$languageCode;
		$this->commentArgs = $commentArgs;
		$this->summaryArgs = $summaryArgs;
	}

	/**
	 * Set the user provided edit summary
	 *
	 * @since 0.4
	 *
	 * @param string $summary edit summary provided by the user
	 */
	public function setUserSummary( $summary = null ) {
		$this->userSummary = $summary === null ? null : (string)$summary;
	}

	/**
	 * Set the language code to use as the second autocomment argument
	 *
	 * @since 0.4
	 *
	 * @param string $lang the language code
	 */
	public function setLanguage( $languageCode = null ) {
		$this->languageCode = $languageCode === null ? null : (string)$languageCode;
	}

	/**
	 * Set the module part of the autocomment
	 *
	 * @since 0.4
	 *
	 * @param string $name
	 */
	public function setModuleName( $name ) {
		$this->moduleName = (string)$name;
	}

	/**
	 * Get the module part of the autocomment
	 *
	 * @since 0.4
	 *
	 * @return string
	 */
	public function getModuleName() {
		return $this->moduleName;
	}

	/**
	 * Set the action part of the autocomment
	 *
	 * @since 0.4
	 *
	 * @param string $name
	 */
	public function setAction( $name ) {
		$this->actionName = $name === null ? null : (string)$name;
	}

	/**
	 * Get the action part of the autocomment
	 *
	 * @since 0.4
	 *
	 * @return string|null
	 */
	public function getActionName() {
		return $this->actionName;
	}

	/**
	 * Get the user-provided edit summary
	 *
	 * @since 0.4
	 *
	 * @return string|null
	 */
	public function getUserSummary() {
		return $this->userSummary;
	}

	/**
	 * Get the language part of the autocomment
	 *
	 * @since 0.4
	 *
	 * @return string|null
	 */
	public function getLanguageCode() {
		return $this->languageCode;
	}

	/**
	 * Format the message key using the object-specific values
	 *
	 * @since 0.3
	 *
	 * @return string with a message key, or possibly an empty string
	 */
	public function getMessageKey() {
		if ( $this->moduleName === null || $this->moduleName === '' ) {
			return $this->actionName;
		} elseif ( $this->actionName === null || $this->actionName === '' ) {
			return $this->moduleName;
		} else {
			return $this->moduleName . '-' . $this->actionName;
		}
	}

	/**
	 * Add auto comment arguments.
	 *
	 * @since 0.4
	 *
	 * @param mixed $args,... Parts to be stringed together
	 */
	public function addAutoCommentArgs( $args /*...*/ ) {
		if ( !is_array( $args ) ) {
			$args = func_get_args();
		}

		$this->commentArgs = array_merge( $this->commentArgs, $args );
	}

	/**
	 * Add arguments to the summary part.
	 *
	 * @since 0.4
	 *
	 * @param mixed $args,... Parts to be stringed together
	 */
	public function addAutoSummaryArgs( $args /*...*/ ) {
		if ( !is_array( $args ) ) {
			$args = func_get_args();
		}

		$this->summaryArgs = array_merge( $this->summaryArgs, $args );
	}

	/**
	 * @return array
	 */
	public function getCommentArgs() {
		return $this->commentArgs;
	}

	/**
	 * @return array
	 */
	public function getAutoSummaryArgs() {
		return $this->summaryArgs;
	}

	/**
	 * @deprecated Use SummaryFormatter instead
	 * @throws LogicException
	 */
	public function toString() {
		throw new LogicException( 'toString() is no longer supported, use SummaryFormatter instead' );
	}

}
