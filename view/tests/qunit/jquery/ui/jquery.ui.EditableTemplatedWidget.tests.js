/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */
( function( mw, $, QUnit ) {
	'use strict';

QUnit.module( 'jquery.ui.EditableTemplatedWidget', QUnit.newMwEnvironment( {
	setup: function() {
		$.widget( 'test.editablewidget', {
			_create: function() {
				this._initialValue = this.options.value;
			},
			_draw: function() {},
			_save: function() {
				return $.Deferred().resolve().promise();
			},
			value: function( value ) {
				if( value === undefined ) {
					this.option( 'value', value );
				} else {
					return this.option( 'value' );
				}
			},
			isEmpty: function() {
				return !this.option( 'value' );
			},
			isValid: function() {
				return !!this.option( 'value' );
			},
			isInitialValue: function() {
				return this.option( 'value' ) === this._initialValue;
			}
		} );
	},
	teardown: function() {
		delete( $.test.editablewidget );

		$( '.test_edittoolbar' ).each( function() {
			var $edittoolbar = $( this ),
				edittoolbar = $edittoolbar.data( 'edittoolbar' );

			if( edittoolbar ) {
				edittoolbar.destroy();
			}

			$edittoolbar.remove();
		} );
	}
} ) );

QUnit.test( 'Create & destroy', function( assert ) {
	var testSets = [
		[
			'<div><span>$1</span></div>',
			{
				templateParams: ['test']
			}
		]
	];

	for( var i = 0; i < testSets.length; i++ ) {
		mw.wbTemplates.store.set( 'templatedWidget-test', testSets[i][0] );

		var $subject = $( '<div/>' );

		$subject.editablewidget( $.extend( {
			template: 'templatedWidget-test'
		}, testSets[i][1] ) );

		assert.ok(
			$subject.data( 'editablewidget' ) instanceof $.test.editablewidget,
			'Test set #' + i + ': Initialized widget.'
		);

		$subject.data( 'editablewidget' ).destroy();

		assert.ok(
			$subject.data( 'editablewidget' ) === undefined,
			'Destroyed widget.'
		);
	}
} );

}( mediaWiki, jQuery, QUnit ) );
