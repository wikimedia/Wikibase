<?php

namespace Wikibase\Lib;

use DataValues\TimeValue;
use Language;
use Message;
use ValueFormatters\FormatterOptions;
use ValueFormatters\TimeIsoFormatter;
use ValueFormatters\ValueFormatter;
use ValueFormatters\ValueFormatterBase;

/**
 * @since 0.4
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Adam Shorland
 *
 * @todo move me to DataValues-time
 */
class MwTimeIsoFormatter extends ValueFormatterBase implements TimeIsoFormatter {

	/**
	 * MediaWiki language object.
	 * @var Language
	 */
	protected $language;

	/**
	 * @var string[]
	 */
	private static $dayPlaceholders = array(
		/** default as regex */ 'j\.?',
		'cdo' => 'j "hô̤" (l)',
		'cu' => 'j числа,',
		'fur' => 'j "di"',
		'gan' => '月j日 (l)',
		'gan-hans' => '月j日 (l)',
		'gan-hant' => '月j日 (l)',
		'gl' => 'j \d\e',
		'ii' => '月j日 (l)',
		'ja' => '月j日 (D)',
		'mwl' => 'j \d\e',
		'nan' => 'j-"ji̍t" (l)',
		'pt' => 'j \d\e',
		'pt-br' => 'j "de"',
		'uz' => 'j-',
		'vi' => '"ngày" j "tháng"',
		'vo' => 'j"id"',
		'vot' => 'j.',
		'wuu' => '月j日 (l)',
		'yue' => '月j號 (l)'
	);

	/**
	 * @var string[]
	 */
	private static $monthPlaceholders = array(
		/** default as regex */ '([FM]|xg)',
		'cdo' => 'n "nguŏk"',
		'crh' => '"s." xg',
		'crh-latn' => '"s." xg',
		'cs' => 'n.',
		'eo' => 'M.',
		'fi' => 'F"ta"',
		'fit' => 'F"ta"',
		'gan' => '年n',
		'gan-hans' => '年n',
		'gan-hant' => '年n',
		'gl' => 'F \d\e',
		'ii' => '年n',
		'ja' => '年n',
		'kk' => '"ж." xg',
		'mwl' => 'F \d\e',
		'nan' => 'n-"goe̍h" ',
		'oc' => 'F "de"',
		'pt' => 'F \d\e',
		'pt-br' => 'F "de"',
		'vi' => 'n "năm"',
		'vot' => 'F"ta"',
		'wuu' => '年n',
		'yue' => '年n',
	);

	/**
	 * @param FormatterOptions $options
	 */
	public function __construct( FormatterOptions $options ) {
		$this->options = $options;

		$this->options->defaultOption( ValueFormatter::OPT_LANG, 'en' );

		$this->language = Language::factory(
			$this->options->getOption( ValueFormatter::OPT_LANG )
		);
	}

	/**
	 * @see ValueFormatter::format
	 */
	public function format( $value ) {
		return $this->formatDate(
			$value->getTime(),
			$value->getPrecision()
		);
	}

	/**
	 * @see TimeIsoFormatter::formatDate
	 */
	public function formatDate( $extendedIsoTimestamp, $precision ) {
		/**
		 * $matches for +00000002013-07-16T01:02:03Z
		 * [0] => +00000002013-07-16T00:00:00Z
		 * [1] => +
		 * [2] => 00000002013
		 * [3] => 0000000
		 * [4] => 2013
		 * [5] => 07
		 * [6] => 16
		 * [7] => 01
		 * [8] => 02
		 * [9] => 03
		 */
		$regexSuccess = preg_match( '/^(\+|\-)((\d{0,7})?(\d{4}))-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})Z/',
			$extendedIsoTimestamp, $matches );

		if( !$regexSuccess || intval( $matches[2] ) === 0 ) {
			return $extendedIsoTimestamp;
		}
		$isBCE = ( $matches[1] === '-' );

		// Positive 4-digit year allows using Language object.
		$fourDigitYearTimestamp = str_pad(
			substr( $extendedIsoTimestamp, strlen( $matches[1] . $matches[3] ) ),
			20, // This is the length of 2013-07-16T00:00:00Z
			'0',
			STR_PAD_LEFT
		);

		$timestamp = wfTimestamp( TS_MW, $fourDigitYearTimestamp );
		$localisedDate = $this->language->sprintfDate(
			$this->getDateFormat( $precision ),
			$timestamp
		);

		// we do not handle parsing arabic, farsi, etc. digits (bug 63732)
		$normalisedDate = $this->normaliseDigits( $localisedDate );

		//If we cant reliably fix the year return the full timestamp,
		//  this should never happen as sprintfDate should always return a 4 digit year
		if( !$this->canFormatYear( $normalisedDate, $matches ) ) {
			return $extendedIsoTimestamp;
		}

		$formattedDate = str_replace(
			$matches[4],
			$this->formatYear( $matches[2], $precision, $isBCE ),
			$normalisedDate
		);

		return $formattedDate;
	}

	/**
	 * @param string $date
	 *
	 * @return string
	 */
	private function normaliseDigits( $date ) {
		return $this->language->parseFormattedNumber( $date );
	}

	/**
	 * @param string $date
	 * @param array $matches
	 *
	 * @return boolean
	 */
	private function canFormatYear( $date, $matches ) {
		return substr_count( $date, $matches[4] ) === 1;
	}

	/**
	 * Get the dateformat string for the given precision to be used by sprintfDate
	 * @param integer $precision
	 * @return string dateFormat to be used by sprintfDate
	 */
	private function getDateFormat( $precision ) {
		$dateFormat = $this->language->getDateFormatString(
			'date',
			$this->language->getDefaultDateFormat()
		);
		$langCode = $this->language->getCode();

		if( $precision < TimeValue::PRECISION_DAY ) {
			// Remove day placeholder:
			$dayPlaceholder = $this->getDayPlaceholder( $langCode );
			if( $dayPlaceholder !== null ) {
				$dateFormat = str_replace( $dayPlaceholder, '', $dateFormat );
			} else {
				$dateFormat = preg_replace( '/' . self::$dayPlaceholders[0] . '/', '', $dateFormat );
			}
		}

		if( $precision < TimeValue::PRECISION_MONTH ) {
			// Remove month placeholder:
			$monthPlaceholder = $this->getMonthPlaceholder( $langCode );
			if( $monthPlaceholder !== null ) {
				$dateFormat = str_replace( $monthPlaceholder, '', $dateFormat );
			} else {
				$dateFormat = preg_replace( '/' . self::$monthPlaceholders[0] . '/', '', $dateFormat );
			}
		}

		// Trim any odd space chars
		return trim( $dateFormat, chr(226) . chr(128) . chr(143) . ' ' );
	}

	/**
	 * @param string $langCode
	 * @return string|null
	 */
	private function getDayPlaceholder( $langCode ) {
		if( array_key_exists( $langCode, self::$dayPlaceholders ) ) {
			return self::$dayPlaceholders[ $langCode ];
		}
		return null;
	}

	/**
	 * @param string $langCode
	 * @return string|null
	 */
	private function getMonthPlaceholder( $langCode ) {
		if( array_key_exists( $langCode, self::$monthPlaceholders ) ) {
			return self::$monthPlaceholders[ $langCode ];
		}
		return null;
	}

	/**
	 * @param string $fullYear
	 * @param integer $precision
	 * @param bool $isBCE
	 *
	 * @return string the formatted year
	 */
	private function formatYear( $fullYear, $precision, $isBCE ) {
		if( $isBCE ) {
			$msgPrefix = 'wikibase-time-precision-BCE';
		} else {
			$msgPrefix = 'wikibase-time-precision';
		}

		switch( $precision ) {
			case TimeValue::PRECISION_Ga:
				$fullYear = round( $fullYear, -9 );
				$fullYear = substr( $fullYear, 0, -9 );
				return $this->getMessage( $msgPrefix . '-Gannum', $fullYear );
			case TimeValue::PRECISION_100Ma:
				$fullYear = round( $fullYear, -8 );
				$fullYear = substr( $fullYear, 0, -6 );
				return $this->getMessage( $msgPrefix . '-Mannum', $fullYear );
			case TimeValue::PRECISION_10Ma:
				$fullYear = round( $fullYear, -7 );
				$fullYear = substr( $fullYear, 0, -6 );
				return $this->getMessage( $msgPrefix . '-Mannum', $fullYear );
			case TimeValue::PRECISION_Ma:
				$fullYear = round( $fullYear, -6 );
				$fullYear = substr( $fullYear, 0, -6 );
				return $this->getMessage( $msgPrefix . '-Mannum', $fullYear );
			case TimeValue::PRECISION_100ka:
				$fullYear = round( $fullYear, -5 );
				return $this->getMessage( $msgPrefix . '-annum', $fullYear );
			case TimeValue::PRECISION_10ka:
				$fullYear = round( $fullYear, -4 );
				return $this->getMessage( $msgPrefix . '-annum', $fullYear );
			case TimeValue::PRECISION_ka:
				$fullYear = round( $fullYear, -3 );
				$fullYear = substr( $fullYear, 0, -3 );
				return $this->getMessage( $msgPrefix . '-millennium', $fullYear );
			case TimeValue::PRECISION_100a:
				$fullYear = round( $fullYear, -2 );
				$fullYear = substr( $fullYear, 0, -2 );
				return $this->getMessage( $msgPrefix . '-century', $fullYear );
			case TimeValue::PRECISION_10a:
				$fullYear = round( $fullYear, -1 );
				return $this->getMessage( $msgPrefix . '-10annum', $fullYear );
			default:
				//If not one of the above make sure the year have at least 4 digits
				$fullYear = ltrim( $fullYear, '0' );
				if( $isBCE ) {
					$fullYear .= ' BCE';
				}
				return $fullYear;
		}
	}

	/**
	 * @param string $key
	 * @param string $fullYear
	 * @return String
	 */
	private function getMessage( $key, $fullYear ) {
		$message = new Message( $key );
		//FIXME: as the frontend can not parse the translated precisions we only want to present the ENGLISH for now
		//once the frontend is using backend parsers we can switch the translation on
		//See the fix me in: MwTimeIsoParser::reconvertOutputString
		//$message->inLanguage( $this->language );
		$message->inLanguage( new Language() );
		$message->params( array( $fullYear ) );
		return $message->text();
	}

}
