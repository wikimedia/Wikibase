/**
 * JavaScript for 'Wikibase' property edit tool toolbar groups with basic edit functionality
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 *
 * @file
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 *
 *
 * @event edit: Triggered when clicking (hitting enter on) the edit button.
 *        (1) {jQuery.Event}
 *
 * @event save: Triggered when clicking (hitting enter on) the save button.
 *        (1) {jQuery.Event}
 *
 * @event cancel: Triggered when clicking (hitting enter on) the cancel button.
 *        (1) {jQuery.Event}
 *
 * @event remove: Triggered when clicking (hitting enter on) the remove button.
 *        (1) {jQuery.Event}
 *
 * @event render: Triggered when the toolbar shall re-render. Changes the edit group from displaying
 *        buttons for editing to the state of displaying buttons to go into edit mode again.
 *        (1) {jQuery.Event}
 *        (2) {Number} apiAction (see wb.ui.PropertyEditTool.EditableValue.prototype.API_ACTION)
 *        (3) {Boolean} isEmpty Whether EditableValue is empty.
 *        (4) {Boolean} preserveEmptyForm Whether a form element should be display even when the
 *            EditableValue is empty.
 */
( function( mw, wb, $, undefined ) {
'use strict';
var PARENT = wb.ui.Toolbar.Group;

/**
 * Extends the basic toolbar group element with buttons essential for editing stuff.
 * Basically '[edit]' which gets expanded to '[cancel|save]' when hit.
 * This also interacts with a given editable value.
 * @constructor
 * @see wb.ui.Toolbar.Group
 * @since 0.1
 *
 * @todo Should be refactored so it can be used independently from EditableValue.
 */
wb.ui.Toolbar.EditGroup = wb.utilities.inherit( PARENT,
	// Overwritten constructor:
	function( editableValue ) {
		if( editableValue !== undefined ) {
			this.init( editableValue );
		}
	}, {
	/**
	 * @var wb.ui.Toolbar.Button
	 */
	btnEdit: null,

	/**
	 * @var wb.ui.Toolbar.Button
	 */
	btnCancel: null,

	/**
	 * @var wb.ui.Toolbar.Button
	 */
	btnSave: null,

	/**
	 * @var wb.ui.Toolbar.Button
	 */
	btnRemove: null,

	/**
	 * Element holding the tooltips image with the tooltip itself attached.
	 * @var wb.ui.Toolbar.Label
	 */
	tooltipAnchor: null,

	/**
	 * Inner group needed to visually separate tooltip and edit buttons, this one holds the edit buttons.
	 * @var wb.ui.Toolbar.Group
	 */
	innerGroup: null,

	/**
	 * @param wb.ui.PropertyEditTool.EditableValue editableValue the editable value
	 *        the toolbar should interact with.
	 */
	init: function( editableValue ) {
		PARENT.prototype.init.call( this );

		// overwrite tooltip message when editing is restricted
		$( wikibase ).on(
			'restrictEntityPageActions blockEntityPageActions',
			$.proxy(
				function( event ) {
					var messageId = ( event.type === 'blockEntityPageActions' ) ?
						'wikibase-blockeduser-tooltip-message' :
						'wikibase-restrictionedit-tooltip-message';

					this.tooltipAnchor.getTooltip().setContent(
						mw.message( messageId ).escaped()
					);

					this.tooltipAnchor.getTooltip().setGravity( 'nw' );
				}, this
			)
		);

		$( this ).on( 'render', function( event, apiAction, isEmpty, preserveEmptyForm ) {
			this._render( apiAction, isEmpty, preserveEmptyForm );
		} );
	},

	/**
	 * @see wb.ui.Toolbar.prototype._initToolbar()
	 */
	_initToolbar: function() {
		// call prototypes base function to append toolbar itself:
		wb.ui.Toolbar.prototype._initToolbar.call( this );

		// create a group inside the group so we can separate the tooltip visually
		this.innerGroup = new wb.ui.Toolbar.Group();
		this.addElement( this.innerGroup );

		this.tooltipAnchor = new wb.ui.Toolbar.Label( $( '<span/>', {
			'class': 'mw-help-field-hint',
			style: 'display:inline;text-decoration:none;',
			html: '&nbsp;' // TODO find nicer way to hack Webkit browsers to display tooltip image (see also css)
		} ) );
		// initialize the tooltip just to be able to change render variables like gravity
		this.tooltipAnchor.setTooltip( '' );
		this.tooltipAnchor.stateChangeable = false; // tooltip anchor has no disabled/enabled behaviour

		// now create the buttons we need for basic editing:
		var button = wb.ui.Toolbar.Button;

		this.btnEdit = new button( mw.msg( 'wikibase-edit' ) );
		$( this.btnEdit ).on( 'action', $.proxy( function( event ) {
			this.innerGroup.removeElement( this.btnEdit );
			if ( this.displayRemoveButton ) {
				this.innerGroup.removeElement( this.btnRemove );
			}
			this.innerGroup.addElement( this.btnSave );
			this.innerGroup.addElement( this.btnCancel );
			this.addElement( this.tooltipAnchor, 1 ); // add tooltip after edit commands
			$( this ).triggerHandler( 'edit' );
		}, this ) );

		this.btnCancel = new button( mw.msg( 'wikibase-cancel' ) );
		$( this.btnCancel ).on( 'action', $.proxy( function( event ) {
			$( this ).triggerHandler( 'cancel' );
		}, this ) );

		this.btnSave = new button( mw.msg( 'wikibase-save' ) );
		$( this.btnSave ).on( 'action', $.proxy( function( event ) {
			$( this ).triggerHandler( 'save' );
		}, this ) );

		// initialize remove button:
		this.btnRemove = new button( mw.msg( 'wikibase-remove' ) );
		$( this.btnRemove ).on( 'action', $.proxy( function( event ) {
			this.triggerHandler( 'remove' );
		}, this ) );

		this._render(); // initializing the toolbar
	},

	/**
	 * Sets/updates the toolbar tooltip's message.
	 *
	 * @param {String} message Tooltip message
	 */
	setTooltip: function( message ) {
		this.tooltipAnchor.setTooltip( message );
	},

	/**
	 * Renders the toolbar. Omit all parameters to initialize the toolbar.
	 *
	 * @param {Number} apiAction (see wb.ui.PropertyEditTool.EditableValue.prototype.API_ACTION)
	 * @param {Boolean} isEmpty Whether EditableValue is empty.
	 * @param {Boolean} preserveEmptyForm Whether a form element should be display even when the
	 *        EditableValue is empty.
	 */
	_render: function( apiAction, isEmpty, preserveEmptyForm ) {
		var action = wb.ui.PropertyEditTool.EditableValue.prototype.API_ACTION;
		if( apiAction === undefined // initialize
			|| apiAction === action.SAVE
			|| ( apiAction === action.NONE && ( !preserveEmptyForm || !isEmpty ) )
			) {
			// remove buttons for editing and display buttons for going back to edit mode
			this.removeElement( this.tooltipAnchor );
			this.innerGroup.removeElement( this.btnSave );
			this.innerGroup.removeElement( this.btnCancel );
			if ( this.displayRemoveButton ) {
				this.innerGroup.removeElement( this.btnRemove );
			}
			this.innerGroup.addElement( this.btnEdit );
			if ( this.displayRemoveButton ) {
				this.innerGroup.addElement( this.btnRemove );
			}
		}
	},

	/**
	 * Destroys the EditGroup.
	 */
	destroy: function() {
		PARENT.prototype.destroy.call( this );
		if ( this.innerGroup !== null ) {
			this.innerGroup.destroy();
			this.innerGroup = null;
		}
		if ( this.tooltipAnchor !== null ) {
			this.tooltipAnchor.destroy();
			this.tooltipAnchor = null;
		}
		if ( this.btnEdit !== null ) {
			this.btnEdit.destroy();
			this.btnEdit = null;
		}
		if ( this.btnCancel !== null ) {
			this.btnCancel.destroy();
			this.btnCancel = null;
		}
		if ( this.btnSave !== null ) {
			this.btnSave.destroy();
			this.btnSave = null;
		}
		if ( this.btnRemove !== null ) {
			this.btnRemove.destroy();
			this.btnRemove = null;
		}
	},

	/////////////////
	// CONFIGURABLE:
	/////////////////

	/**
	 * @see wb.ui.Toolbar.Group.renderItemSeparators
	 */
	renderItemSeparators: false,

	/**
	 * If this is set to true, the edit toolbar will add a button 'remove' besides the 'edit' command.
	 * @var bool
	 */
	displayRemoveButton: false
} );

} )( mediaWiki, wikibase, jQuery );
