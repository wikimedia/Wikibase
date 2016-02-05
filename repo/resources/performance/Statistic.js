( function( wb, $ ) {
	'use strict';

	var MODULE = wb.performance;

	/**
	 * Wikibase performance statistic
	 *
	 * @class wikibase.performance.Statistic
	 * @licence GNU GPL v2+
	 *
	 * @author Jonas Kress
	 * @constructor
	 * @param {PerformanceMarks[]} performanceMarks
	 */
	var SELF = MODULE.Statistic = function( performanceMarks ) {

		this._marks = performanceMarks;
	};

	/**
	 * @property {PerformanceMark[]}
	 * @private
	 **/
	SELF.prototype._marks = null;

	/**
	 * Get HTML
	 * @return {jQuery}
	 **/
	SELF.prototype.getHtml = function() {
		var self = this,
			$div = $( '<div/>' );

		$.each( this._marks, function( key, value ) {

			var durationTotal = self._formatDuration( value.durationTotal ),
				durations = value.durations,
				durationAverage = self._formatDuration( value.durationTotal / durations.length ),
				durationsMinMax = self._getMinMaxDuration( value.durations ),
				durationsMin = self._formatDuration( durationsMinMax[0] ),
				durationsMax = self._formatDuration( durationsMinMax[1] );

			var text = key + ': ' + durationTotal,
				title = null;

			if ( durations.length > 1 ) {
				text = key + ' (' + durations.length + '): ' + durationTotal;
				title = 'Min: ' + durationsMin + '\n'
					+ 'Avg: ' + durationAverage + '\n'
					+ 'Max: ' + durationsMax;
			}

			$div.prepend( $( '<div/>' ).text( text ).attr( 'title', title  ) );
		} );

		return $div;
	};

	SELF.prototype._formatDuration = function( duration ) {
		return ( Math.round( duration ) / 1000 ) + 's';
	};

	/**
	 * Get min average max from duration array
	 * @private
	 * @param {number[]} array
	 * @return {number[]} [Minimum, Average, Maximum]
	 **/
	SELF.prototype._getMinMaxDuration = function( array ) {
		var max = array[0],
			min = array[0];

		$.each( array, function() {
			if ( this > max ) {
				max = this;
			}
			if ( this < min ) {
				min = this;
			}
		} );

		return [min, max];
	};

}( wikibase, jQuery ) );
