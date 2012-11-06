/**
 * QUnit tests for toolbar edit group
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @since 0.1
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 */

( function( wb, $, QUnit, undefined ) {
	'use strict';

	/**
	 * Factory for creating a new EditGroup object suited for testing.
	 *
	 * @return  {wb.ui.Toolbar.EditGroup}
	 */
	var newTestEditGroup = function() {
		var node = $( '<div/>', { id: 'subject' } );
		$( '<div/>', { id: 'parent' } ).append( node );
		var propertyEditTool = new wb.ui.PropertyEditTool( node );
		var toolbar = propertyEditTool._buildSingleValueToolbar();
		return toolbar.editGroup;
	};

	QUnit.module( 'wikibase.ui.Toolbar.EditGroup', QUnit.newWbEnvironment() );

	QUnit.test( 'init check', function( assert ) {

		var editGroup = newTestEditGroup();

		assert.ok(
			editGroup.innerGroup instanceof wb.ui.Toolbar.Group,
			'initiated inner edit group'
		);

		assert.ok(
			editGroup.tooltipAnchor instanceof wb.ui.Toolbar.Label,
			'initiated tooltip'
		);

		assert.equal(
			editGroup.tooltipAnchor.stateChangeable,
			false,
			'tooltip state is not changeable'
		);

		assert.ok(
			editGroup.btnEdit instanceof wb.ui.Toolbar.Button,
			'initiated edit button'
		);

		assert.ok(
			editGroup.btnCancel instanceof wb.ui.Toolbar.Button,
			'initiated cancel button'
		);

		assert.ok(
			editGroup.btnSave instanceof wb.ui.Toolbar.Button,
			'initiated save button'
		);

		assert.ok(
			editGroup.btnRemove instanceof wb.ui.Toolbar.Button,
			'initiated remove button'
		);

		assert.equal(
			editGroup._options.displayRemoveButton,
			true,
			'remove button will not be displayed'
		);

		assert.equal(
			editGroup.renderItemSeparators,
			false,
			'item separators will not be displayed'
		);

		assert.equal(
			editGroup.innerGroup.hasElement( editGroup.btnEdit ),
			true,
			'edit button is in inner group'
		);

		editGroup.destroy();

		assert.equal(
			editGroup.innerGroup,
			null,
			'destroyed inner group'
		);

		assert.equal(
			editGroup.tooltipAnchor,
			null,
			'destroyed tooltip'
		);

		assert.equal(
			editGroup.btnEdit,
			null,
			'destroyed edit button'
		);

		assert.equal(
			editGroup.btnCancel,
			null,
			'destroyed cancel button'
		);

		assert.equal(
			editGroup.btnSave,
			null,
			'destroyed save button'
		);

		assert.equal(
			editGroup.btnRemove,
			null,
			'destroyed remove button'
		);

	} );

}( wikibase, jQuery, QUnit ) );
