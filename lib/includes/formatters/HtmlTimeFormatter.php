<?php

namespace Wikibase\Lib;

use DataValues\TimeValue;
use InvalidArgumentException;
use ValueFormatters\FormatterOptions;
use ValueFormatters\ValueFormatter;
use ValueFormatters\ValueFormatterBase;

/**
 * @since 0.5
 *
 * @license GNU GPL v2+
 * @author Adrian Lang < adrian.lang@wikimedia.de >
 * @author Thiemo Mättig
 * @author Daniel Kinzler
 */
class HtmlTimeFormatter extends ValueFormatterBase {

	private static $calendarKeys = array(
		TimeValue::CALENDAR_GREGORIAN => 'valueview-expert-timevalue-calendar-gregorian',
		TimeValue::CALENDAR_JULIAN => 'valueview-expert-timevalue-calendar-julian',
	);

	/**
	 * @var ValueFormatter
	 */
	private $dateTimeFormatter;

	/**
	 * @param FormatterOptions|null $options
	 * @param ValueFormatter $dateTimeFormatter
	 */
	public function __construct( FormatterOptions $options = null, ValueFormatter $dateTimeFormatter ) {
		parent::__construct( $options );

		$this->dateTimeFormatter = $dateTimeFormatter;
	}

	/**
	 * @see ValueFormatter::format
	 *
	 * @since 0.5
	 *
	 * @param TimeValue $value
	 *
	 * @throws InvalidArgumentException
	 * @return string HTML
	 */
	public function format( $value ) {
		if ( !( $value instanceof TimeValue ) ) {
			throw new InvalidArgumentException( 'Data value type mismatch. Expected a TimeValue.' );
		}

		$formatted = $this->dateTimeFormatter->format( $value );

		if ( $this->calendarNameNeeded( $value ) ) {
			$formatted .= '<sup class="wb-calendar-name">'
				. $this->formatCalendarName( $value->getCalendarModel() )
				. '</sup>';
		}

		return $formatted;
	}

	/**
	 * @param TimeValue $value
	 *
	 * @return bool
	 */
	private function calendarNameNeeded( TimeValue $value ) {
		// Loose check if the timestamp string is ISO-ish and starts with a year.
		if ( !preg_match( '/^[-+]?\d+\b/', $value->getTime(), $matches ) ) {
			return true;
		}

		// NOTE: PHP limits overly large values to PHP_INT_MAX. No overflow or wrap-around occurs.
		$year = (int)$matches[0];
		$guessedCalendar = $this->getDefaultCalendar( $year );

		// Always show the calendar if it's different from the "guessed" default.
		if ( $value->getCalendarModel() !== $guessedCalendar ) {
			return true;
		}

		// If precision is year or less precise, don't show the calendar.
		if ( $value->getPrecision() <= TimeValue::PRECISION_YEAR ) {
			return false;
		}

		// If the date is inside the "critical" range where Julian and Gregorian were used
		// in parallel, always show the calendar. Gregorian was made "official" in October 1582 but
		// may already be used earlier. Julian continued to be official until the 1920s in Russia
		// and Greece, see https://en.wikipedia.org/wiki/Julian_calendar.
		if ( $year > 1580 && $year < 1930 ) {
			return true;
		}

		// Otherwise, the calendar is "unsurprising", so don't show it.
		return false;
	}

	/**
	 * This guesses the most likely calendar model based on the given TimeValue,
	 * ignoring the calendar given in the TimeValue. This should always implement the
	 * exact same heuristic as IsoTimestampParser::getCalendarModel().
	 *
	 * @see IsoTimestampParser::getCalendarModel()
	 *
	 * @param int $year
	 *
	 * @return string Calendar URI
	 */
	private function getDefaultCalendar( $year ) {
		// The Gregorian calendar was introduced in October 1582,
		// so we'll default to Julian for all years before 1583.
		return $year <= 1582 ? TimeValue::CALENDAR_JULIAN : TimeValue::CALENDAR_GREGORIAN;
	}

	/**
	 * @param string $calendarModel
	 *
	 * @return string HTML
	 */
	private function formatCalendarName( $calendarModel ) {
		if ( array_key_exists( $calendarModel, self::$calendarKeys ) ) {
			$key = self::$calendarKeys[$calendarModel];
			$lang = $this->getOption( ValueFormatter::OPT_LANG );
			$msg = wfMessage( $key )->inLanguage( $lang );

			if ( $msg->exists() ) {
				return htmlspecialchars( $msg->text() );
			}
		}

		return htmlspecialchars( $calendarModel );
	}

}
