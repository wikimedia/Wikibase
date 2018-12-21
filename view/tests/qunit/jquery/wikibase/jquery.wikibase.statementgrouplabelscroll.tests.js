/**
 * QUnit tests for "wikibase.statementgrouplabelscroll" jQuery widget.
 *
 * @license GPL-2.0-or-later
 * @author Daniel Werner < daniel.a.r.werner@gmail.com >
 */
( function ( StatementGroupLabelScrollWidget ) {
	'use strict';

	/**
	 * Returns a DOM object within a HTML page suitable for testing the widget on.
	 *
	 * @return {jQuery}
	 * @throws {Error} If the test runs in a non-browser environment or on a unsuitable HTML page.
	 */
	function newTestNode() {
		var $body = $( 'body' );

		if ( !$body.length ) {
			throw new Error( 'Can only run this test on a HTML page with "body" tag in the browser.' );
		}

		return $( '<div/>' ).appendTo( $body );
	}

	QUnit.module( 'jquery.wikibase.statementgrouplabelscroll', {
		afterEach: function () {
			StatementGroupLabelScrollWidget.activeInstances().forEach( function ( instance ) {
				instance.destroy();
				instance.element.remove();
			} );
		}
	} );

	QUnit.test( 'widget definition', function ( assert ) {
		assert.strictEqual(
			typeof StatementGroupLabelScrollWidget,
			'function',
			'"jQuery.wikibase.statementgrouplabelscroll" (widget definition) is defined'
		);

		assert.strictEqual(
			typeof $.fn.statementgrouplabelscroll,
			'function',
			'"jQuery.fn.statementgrouplabelscroll" (widget bridge) is defined'
		);

		assert.strictEqual(
			StatementGroupLabelScrollWidget.activeInstances().length,
			0,
			'Zero active instance of the widget before first instantiation'
		);
	} );

	QUnit.test( 'widget instantiation and destruction', function ( assert ) {
		var $testNode = newTestNode().statementgrouplabelscroll(),
			instance = $testNode.data( 'statementgrouplabelscroll' );

		assert.ok(
			instance instanceof StatementGroupLabelScrollWidget,
			'Widget successfully instantiated'
		);

		assert.strictEqual(
			StatementGroupLabelScrollWidget.activeInstances()[ 0 ],
			instance,
			'Instantiated widget returned by $.wikibase.statementgrouplabelscroll.activeInstances()'
		);

		instance.destroy();

		assert.strictEqual(
			StatementGroupLabelScrollWidget.activeInstances().length,
			0,
			'Zero active instances of the widget after destruction of only active instance'
		);

	} );

}( $.wikibase.statementgrouplabelscroll ) );
