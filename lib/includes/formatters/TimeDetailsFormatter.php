<?php

namespace Wikibase\Lib;

use DataValues\TimeValue;
use Html;
use InvalidArgumentException;
use Message;
use ValueFormatters\FormatterOptions;
use ValueFormatters\TimeFormatter;
use ValueFormatters\ValueFormatter;
use ValueFormatters\ValueFormatterBase;

/**
 * Formatter for rendering the details of a TimeValue (most useful for diffs) in HTML.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Thiemo Mättig
 */
class TimeDetailsFormatter extends ValueFormatterBase {

	/**
	 * @var ValueFormatter
	 */
	private $timeFormatter;

	/**
	 * @param FormatterOptions|null $options
	 */
	public function __construct( FormatterOptions $options = null ) {
		parent::__construct( $options );

		$this->timeFormatter = new MwTimeIsoFormatter( $this->options );
	}

	/**
	 * Generates HTML representing the details of a TimeValue,
	 * as an itemized list.
	 *
	 * @since 0.5
	 *
	 * @param TimeValue $value The ID to format
	 *
	 * @throws InvalidArgumentException
	 * @return string
	 */
	public function format( $value ) {
		if ( !( $value instanceof TimeValue ) ) {
			throw new InvalidArgumentException( 'Data value type mismatch. Expected an TimeValue.' );
		}

		$html = '';
		$html .= Html::element(
			'h4',
			array( 'class' => 'wb-details wb-time-details wb-time-rendered' ),
			$this->timeFormatter->format( $value )
		);
		$html .= Html::openElement( 'table', array( 'class' => 'wb-details wb-time-details' ) );

		$html .= $this->renderLabelValuePair(
			'isotime',
			$this->getTimeHtml( $value->getTime() )
		);
		$html .= $this->renderLabelValuePair(
			'timezone',
			$this->getTimezoneHtml( $value->getTimezone() )
		);
		$html .= $this->renderLabelValuePair(
			'calendar',
			$this->getCalendarModelHtml( $value->getCalendarModel() )
		);
		$html .= $this->renderLabelValuePair(
			'precision',
			$this->getAmountAndPrecisionHtml( $value->getPrecision() )
		);
		$html .= $this->renderLabelValuePair(
			'before',
			$this->getAmountAndPrecisionHtml( $value->getPrecision(), $value->getBefore() )
		);
		$html .= $this->renderLabelValuePair(
			'after',
			$this->getAmountAndPrecisionHtml( $value->getPrecision(), $value->getAfter() )
		);

		$html .= Html::closeElement( 'table' );

		return $html;
	}

	/**
	 * @param string $time
	 *
	 * @return string HTML
	 */
	private function getTimeHtml( $time ) {
		// Loose check if the ISO-like string contains at least year, month, day and hour.
		if ( !preg_match( '/^([-+])(\d+)(-\d+-\d+T\d+(?::\d+)*)Z?$/i', $time, $matches ) ) {
			return htmlspecialchars( $time );
		}

		// Actual MINUS SIGN (U+2212) instead of HYPHEN-MINUS (U+002D)
		$sign = $matches[1] !== '+' ? "\xE2\x88\x92" : '+';
		// Warning, never cast the year to integer to not run into 32-bit integer overflows!
		$year = ltrim( $matches[2], '0' );
		// Keep the sign. Pad the year. Keep month, day, and time. Drop the trailing "Z".
		return htmlspecialchars( $sign . str_pad( $year, 4, '0', STR_PAD_LEFT ) . $matches[3] );
	}

	/**
	 * @param int $timezone
	 *
	 * @return string HTML
	 */
	private function getTimezoneHtml( $timezone ) {
		// Actual MINUS SIGN (U+2212) instead of HYPHEN-MINUS (U+002D)
		$sign = $timezone < 0 ? "\xE2\x88\x92" : '+';
		$hour = floor( abs( $timezone ) / 60 );
		$minute = abs( $timezone ) - $hour * 60;
		return $sign . sprintf( '%02d:%02d', $hour, $minute );
	}

	/**
	 * @param string $calendarModel
	 *
	 * @return string HTML
	 */
	private function getCalendarModelHtml( $calendarModel ) {
		switch ( $calendarModel ) {
			case TimeFormatter::CALENDAR_GREGORIAN:
				$key = 'valueview-expert-timevalue-calendar-gregorian';
				break;
			case TimeFormatter::CALENDAR_JULIAN:
				$key = 'valueview-expert-timevalue-calendar-julian';
				break;
			default:
				return htmlspecialchars( $calendarModel );
		}

		$lang = $this->getOption( ValueFormatter::OPT_LANG );
		$msg = wfMessage( $key )->inLanguage( $lang );
		return $msg->text();
	}

	/**
	 * @param int $precision
	 * @param int $amount
	 *
	 * @return string HTML
	 */
	private function getAmountAndPrecisionHtml( $precision, $amount = 1 ) {
		$key = 'years';

		switch ( $precision ) {
			case TimeValue::PRECISION_MONTH: $key = 'months'; break;
			case TimeValue::PRECISION_DAY: $key = 'days'; break;
			case TimeValue::PRECISION_HOUR: $key = 'hours'; break;
			case TimeValue::PRECISION_MINUTE: $key = 'minutes'; break;
			case TimeValue::PRECISION_SECOND: $key = 'seconds'; break;
		}

		if ( $precision < TimeValue::PRECISION_YEAR ) {
			// PRECISION_10a becomes 10 years, PRECISION_100a becomes 100 years, and so on.
			$precisionInYears = pow( 10, TimeValue::PRECISION_YEAR - $precision );
			$amount *= $precisionInYears;
		} elseif ( $precision > TimeValue::PRECISION_SECOND ) {
			// Sub-second precisions become 0.1 second, 0.01 second, and so on.
			$precisionInSeconds = pow( 10, $precision - TimeValue::PRECISION_SECOND );
			$amount /= $precisionInSeconds;
		}

		$lang = $this->getOption( ValueFormatter::OPT_LANG );
		$msg = wfMessage( $key, $amount )->inLanguage( $lang );
		return $msg->text();
	}

	/**
	 * @param string $fieldName
	 * @param string $valueHtml
	 *
	 * @return string HTML for the label/value pair
	 */
	private function renderLabelValuePair( $fieldName, $valueHtml ) {
		$html = Html::openElement( 'tr' );

		$html .= Html::element( 'th', array( 'class' => 'wb-time-' . $fieldName ),
			$this->getFieldLabel( $fieldName )->text() );
		$html .= Html::element( 'td', array( 'class' => 'wb-time-' . $fieldName ),
			$valueHtml );

		$html .= Html::closeElement( 'tr' );
		return $html;
	}

	/**
	 * @param string $fieldName
	 *
	 * @return Message
	 */
	private function getFieldLabel( $fieldName ) {
		// Messages:
		// wikibase-timedetails-isotime
		// wikibase-timedetails-timezone
		// wikibase-timedetails-calendar
		// wikibase-timedetails-precision
		// wikibase-timedetails-before
		// wikibase-timedetails-after
		$key = 'wikibase-timedetails-' . strtolower( $fieldName );

		$lang = $this->getOption( ValueFormatter::OPT_LANG );
		$msg = wfMessage( $key )->inLanguage( $lang );
		return $msg;
	}

}
