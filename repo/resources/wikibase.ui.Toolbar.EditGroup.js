/**
 * JavasSript for 'Wikibase' property edit tool toolbar groups with basic edit functionality
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase
 * 
 * @since 0.1
 * @file wikibase.ui.Toolbar.EditGroup.js
 * @ingroup Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 */
"use strict";

/**
 * Extends the basic toolbar group element with buttons essential for editing stuff.
 * Basically '[edit]' which gets expanded to '[cancel|save]' when hit.
 * This also interacts with a given editable value.
 * 
 * @todo might be worth refactoring this so it won't require the editableValue as parameter.
 */
window.wikibase.ui.Toolbar.EditGroup = function( editableValue ) {
	if( typeof editableValue != 'undefined' ) {
		this._init();
	}
	//window.wikibase.ui.Toolbar.Group.call( this );
};
window.wikibase.ui.Toolbar.EditGroup.prototype = Object.create( window.wikibase.ui.Toolbar.Group.prototype );
$.extend( window.wikibase.ui.Toolbar.EditGroup.prototype, {

	/**
	 * @var window.wikibase.ui.Toolbar.Button
	 */
	btnEdit: null,
	
	/**
	 * @var window.wikibase.ui.Toolbar.Button
	 */
	btnCancel: null,
	
	/**
	 * @var window.wikibase.ui.Toolbar.Button
	 */
	btnSave: null,

	/**
	 * @var window.wikibase.ui.Toolbar.Button
	 */
	btnRemove: null,
	
	/**
	 * @var window.wikibase.ui.PropertyEditTool.EditableValue
	 */
	_editableValue: null,

	/**
	 * Element holding the tooltips image with the tooltip itself attached.
	 * @var window.wikibase.ui.Toolbar.Label
	 */
	tooltipAnchor: null,
	
	/**
	 * Inner group needed to visually separate tooltip and edit buttons, this one holds the edit buttons.
	 * @var window.wikibase.ui.Toolbar.Group
	 */
	innerGroup: null,
	
	/**
	 * @param window.wikibase.ui.PropertyEditTool.EditableValue editableValue the editable value
	 *        the toolbar should interact with.
	 */
	_init: function( editableValue ) {
		this._editableValue = editableValue;
		
		window.wikibase.ui.Toolbar.Group.prototype._init.call( this );
	},
	
	_initToolbar: function() {
		// call prototypes base function to append toolbar itself:
		window.wikibase.ui.Toolbar.prototype._initToolbar.call( this );
		
		// create a group inside the group so we can separate the tooltip visually
		this.innerGroup = new window.wikibase.ui.Toolbar.Group();
		this.addElement( this.innerGroup );

		this.tooltipAnchor = new window.wikibase.ui.Toolbar.Label( $( '<span/>', {
			'class': 'mw-help-field-hint',
			style: 'display:inline;text-decoration:none;',
			html: '&nbsp;' // TODO find nicer way to hack Webkit browsers to display tooltip image (see also css)
		} ) );
		this.tooltipAnchor.setTooltip( this._editableValue.getInputHelpMessage() );

		// now create the buttons we need for basic editing:
		var button = window.wikibase.ui.Toolbar.Button;
		
		this.btnEdit = new button( mw.msg( 'wikibase-edit' ) );
		$( this.btnEdit ).on( 'action', $.proxy( function( event ) {
			this._editActionHandler();
		}, this ) );
		
		this.btnCancel = new button( mw.msg( 'wikibase-cancel' ) );
		$( this.btnCancel ).on( 'action', $.proxy( function( event ) {
			this._cancelActionHandler();
		}, this ) );

		this.btnSave = new button( mw.msg( 'wikibase-save' ) );
		$( this.btnSave ).on( 'action', $.proxy( function( event ) {
			this._saveActionHandler();
		}, this ) );

		// add 'edit' button only for now:
		this.innerGroup.addElement( this.btnEdit );

		// initialize remove button:
		this.btnRemove = new button( mw.msg( 'wikibase-remove' ) );
		$( this.btnRemove ).on( 'action', $.proxy( function( event ) {
			this._removeActionHandler();
		}, this ) );
		if ( this.displayRemoveButton ) {
			this.innerGroup.addElement( this.btnRemove );
		}

		$( this._editableValue ).on( 'afterSaveComplete', $.proxy( function( event ) {
			this.tooltipAnchor.getTooltip().hide();
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
		}, this ) );

	},
	
	_editActionHandler: function() {
		this.addElement( this.tooltipAnchor, 0 ); // add tooltip before edit commands
		this.innerGroup.removeElement( this.btnEdit );
		if ( this.displayRemoveButton ) {
			this.innerGroup.removeElement( this.btnRemove );
		}
		this.innerGroup.addElement( this.btnSave );
		this.innerGroup.addElement( this.btnCancel );

		this._editableValue.startEditing();
	},
	_cancelActionHandler: function() {
		this._leaveAction( false );
	},
	_saveActionHandler: function() {
		this._leaveAction( true );
	},
	_removeActionHandler: function() {
		this._editableValue.remove();
	},
	
	_leaveAction: function( save ) {
		this._editableValue.stopEditing( save );
	},

	destroy: function() {
		window.wikibase.ui.Toolbar.Group.prototype.destroy.call( this );
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
	 * @see window.wikibase.ui.Toolbar.Group.renderItemSeparators
	 */
	renderItemSeparators: false,
	
	/**
	 * If this is set to true, the edit toolbar will add a button 'remove' besides the 'edit' command.
	 * @var bool
	 */
	displayRemoveButton: false
} );
