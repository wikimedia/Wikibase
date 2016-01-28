/**
 * @licence GNU GPL v2+
 * @author H. Snater < mediawiki@snater.com >
 * @author Bene* < benestar.wikimedia@gmail.com >
 */
( function( mw, wb, $ ) {
	'use strict';

	var PARENT = $.ui.TemplatedWidget;

/**
 * Displays multiple fingerprints (see jQuery.wikibase.entitytermsforlanguageview).
 * @since 0.5
 * @extends jQuery.ui.TemplatedWidget
 *
 * @option {Fingerprint} value
 *
 * @option {string[]} userLanguages
 *         A list of languages for which terms should be displayed initially.
 *
 * @option {wikibase.entityChangers.EntityChangersFactory} entityChangersFactory
 *
 * @event change
 *        - {jQuery.Event}
 *
 * @event afterstartediting
 *       - {jQuery.Event}
 *
 * @event stopediting
 *        - {jQuery.Event}
 *        - {boolean} Whether to drop the value.
 *        - {Function} Callback function.
 *
 * @event afterstopediting
 *        - {jQuery.Event}
 *        - {boolean} Whether to drop the value.
 *
 * @event toggleerror
 *        - {jQuery.Event}
 *        - {Error|null}
 */
$.widget( 'wikibase.entitytermsforlanguagelistview', PARENT, {
	options: {
		template: 'wikibase-entitytermsforlanguagelistview',
		templateParams: [
			mw.msg( 'wikibase-entitytermsforlanguagelistview-language' ),
			mw.msg( 'wikibase-entitytermsforlanguagelistview-label' ),
			mw.msg( 'wikibase-entitytermsforlanguagelistview-description' ),
			mw.msg( 'wikibase-entitytermsforlanguagelistview-aliases' ),
			'' // entitytermsforlanguageview
		],
		templateShortCuts: {
			$header: '.wikibase-entitytermsforlanguagelistview-header',
			$listview: '.wikibase-entitytermsforlanguagelistview-listview'
		},
		value: null,
		userLanguages: [],
		entityChangersFactory: null
	},

	/**
	 * @type {jQuery}
	 */
	$listview: null,

	/**
	 * @type {jQuery}
	 */
	$entitytermsforlanguagelistviewMore: null,

	/**
	 * @type {boolean}
	 */
	_isInEditMode: false,

	/**
	 * @type {Object} Map of language codes pointing to list items (in the form of jQuery nodes).
	 */
	_moreLanguagesItems: {},

	/**
	 * @see jQuery.ui.TemplatedWidget._create
	 */
	_create: function() {
		if ( !( this.options.value instanceof wb.datamodel.Fingerprint )
			|| !$.isArray( this.options.userLanguages )
			|| !this.options.entityChangersFactory
		) {
			throw new Error( 'Required option(s) missing' );
		}

		PARENT.prototype._create.call( this );

		this._verifyExistingDom();
		this._createListView();

		this.element.addClass( 'wikibase-entitytermsforlanguagelistview' );
	},

	/**
	 * @see jQuery.ui.TemplatedWidget.destroy
	 */
	destroy: function() {
		// When destroying a widget not initialized properly, shortcuts will not have been created.
		if ( this.$listview ) {
			// When destroying a widget not initialized properly, listview will not have been created.
			var listview = this.$listview.data( 'listview' );

			if ( listview ) {
				listview.destroy();
			}
		}

		if ( this.$entitytermsforlanguagelistviewMore ) {
			this.$entitytermsforlanguagelistviewMore.remove();
		}

		this.element.removeClass( 'wikibase-entitytermsforlanguagelistview' );
		PARENT.prototype.destroy.call( this );
	},

	_verifyExistingDom: function() {
		var $entitytermsforlanguageview = this.element
			.find( '.wikibase-entitytermsforlanguageview' );

		if ( $entitytermsforlanguageview.length === 0 ) {
			// No need to verify an empty DOM
			return;
		}

		// Scrape languages from static HTML:
		var scrapedLanguages = [],
			i;
		$entitytermsforlanguageview.each( function() {
			$.each( $( this ).attr( 'class' ).split( ' ' ), function() {
				if ( this.indexOf( 'wikibase-entitytermsforlanguageview-' ) === 0 ) {
					scrapedLanguages.push(
						this.split( 'wikibase-entitytermsforlanguageview-' )[1]
					);
					return false;
				}
			} );
		} );

		var mismatch = scrapedLanguages.length !== this.options.userLanguages.length;

		if ( !mismatch ) {
			for ( i = 0; i < scrapedLanguages.length; i++ ) {
				if ( scrapedLanguages[i] !== this.options.userLanguages[i] ) {
					mismatch = true;
					break;
				}
			}
		}

		if ( mismatch ) {
			mw.log.warn( 'Existing entitytermsforlanguagelistview DOM does not match configured languages' );
			$entitytermsforlanguageview.remove();
		}
	},

	/**
	 * Creates the listview widget managing the entitytermsforlanguageview widgets
	 *
	 * @private
	 */
	_createListView: function() {
		var self = this,
			listItemWidget = $.wikibase.entitytermsforlanguageview,
			prefix = listItemWidget.prototype.widgetEventPrefix;

		// Fully encapsulate child widgets by suppressing their events:
		this.element
		.on( prefix + 'change.' + this.widgetName, function( event, lang ) {
			event.stopPropagation();
			if ( lang === self.options.userLanguages[0] ) {
				self._trigger( 'change' );
			}
		} )
		.on( prefix + 'toggleerror.' + this.widgetName, function( event, error ) {
			event.stopPropagation();
			self.setError( error );
		} )
		.on(
			[
				prefix + 'create.' + this.widgetName,
				prefix + 'afterstartediting.' + this.widgetName,
				prefix + 'stopediting.' + this.widgetName,
				prefix + 'afterstopediting.' + this.widgetName,
				prefix + 'disable.' + this.widgetName
			].join( ' ' ),
			function( event ) {
				event.stopPropagation();
			}
		);

		this.$listview
		.listview( {
			listItemAdapter: new $.wikibase.listview.ListItemAdapter( {
				listItemWidget: listItemWidget,
				newItemOptionsFn: function( value ) {
					return {
						value: value,
						entityChangersFactory: self.options.entityChangersFactory,
						helpMessage: mw.msg(
							'wikibase-entitytermsforlanguageview-input-help-message',
							wb.getLanguageNameByCode( value.language )
						)
					};
				}
			} ),
			value: $.map( this.options.userLanguages, function( lang ) {
				return self._getValueForLanguage( lang );
			} ),
			listItemNodeName: 'TR'
		} );

		if ( !this.element.find( '.wikibase-entitytermsforlanguagelistview-more' ).length ) {
			this._createEntitytermsforlanguagelistviewMore();
		}
	},

	/**
	 * Creates a button which allows the user to show terms in all languages available.
	 *
	 * @private
	 */
	_createEntitytermsforlanguagelistviewMore: function() {
		if ( $.isEmptyObject( this._getAdditionalLanguages() ) ) {
			return;
		}

		var $moreLanguagesButton = $( '<a/>' )
			.attr( 'href', '#' )
			.click( $.proxy( this._onMoreLanguagesButtonClicked, this ) );
		this._toggleMoreLanguagesButton( $moreLanguagesButton );

		this.$entitytermsforlanguagelistviewMore = $( '<div/>' )
			.addClass( 'wikibase-entitytermsforlanguagelistview-more' )
			.append( $moreLanguagesButton );

		this.element.after( this.$entitytermsforlanguagelistviewMore );
	},

	/**
	 * Click handler for more languages button
	 *
	 * @private
	 */
	_onMoreLanguagesButtonClicked: function( event ) {
		var $button = $( event.target );

		if ( !this._isMoreLanguagesExpanded() ) {
			this._addMoreLanguages();
		} else {
			var previousTop = $button.offset().top;
			this._removeMoreLanguages();
			this._scrollUp( $button, previousTop );
		}

		this._toggleMoreLanguagesButton( $button );
		return false;
	},

	/**
	 * Toggle more language button text between the "wikibase-entitytermsforlanguagelistview-less"
	 * and "wikibase-entitytermsforlanguagelistview-more" messages.
	 *
	 * @param {jQuery} $button
	 * @private
	 */
	_toggleMoreLanguagesButton: function( $button ) {
		$button.text( mw.msg(
			'wikibase-entitytermsforlanguagelistview-'
				+ ( this._isMoreLanguagesExpanded() ? 'less' : 'more' )
		) );
	},

	_isMoreLanguagesExpanded: function() {
		return !$.isEmptyObject( this._moreLanguagesItems );
	},

	/**
	 * Add 'more' languages to listview
	 *
	 * @private
	 */
	_addMoreLanguages: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		for ( var lang in this._getAdditionalLanguages() ) {
			var $item = listview.addItem( this._getValueForLanguage( lang ) );
			if ( this._isInEditMode ) {
				lia.liInstance( $item ).startEditing();
			}
			this._moreLanguagesItems[lang] = $item;
		}
	},

	/**
	 * Remove 'more' languages from listview
	 *
	 * @private
	 */
	_removeMoreLanguages: function() {
		var listview = this.$listview.data( 'listview' );

		$.each( this._moreLanguagesItems, function() {
			listview.removeItem( this );
		} );

		this._moreLanguagesItems = {};
	},

	/**
	 * @return {Object} Map of additional language codes in this fingerprint.
	 * @private
	 */
	_getAdditionalLanguages: function() {
		var fingerprint = this.options.value,
			languages = {};

		fingerprint.getLabels().each( function( lang ) {
			languages[lang] = lang;
		} );
		fingerprint.getDescriptions().each( function( lang ) {
			languages[lang] = lang;
		} );
		fingerprint.getAliases().each( function( lang ) {
			languages[lang] = lang;
		} );

		$.each( this.options.userLanguages, function() {
			delete languages[this];
		} );

		return languages;
	},

	/**
	 * @param {jQuery} $this
	 * @param {int} previousTop
	 * @private
	 */
	_scrollUp: function( $this, previousTop ) {
		var top = $this.offset().top;

		if ( top < $( window ).scrollTop() ) {
			// This does not only keep the toggler visible, it also updates all stick(y)nodes.
			window.scrollBy( 0, top - previousTop );
		}
	},

	/**
	 * @param {string} lang
	 * @return {Object}
	 * @private
	 */
	_getValueForLanguage: function( lang ) {
		var fingerprint = this.options.value;

		return {
			language: lang,
			label: fingerprint.getLabelFor( lang ) || new wb.datamodel.Term( lang, '' ),
			description: fingerprint.getDescriptionFor( lang ) || new wb.datamodel.Term( lang, '' ),
			aliases: fingerprint.getAliasesFor( lang ) || new wb.datamodel.MultiTerm( lang, [] )
		};
	},

	/**
	 * @return {boolean}
	 */
	isEmpty: function() {
		return !!this.$listview.data( 'listview' ).items().length;
	},

	/**
	 * @return {boolean}
	 */
	isValid: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			isValid = true;

		listview.items().each( function() {
			var entitytermsforlanguageview = lia.liInstance( $( this ) );
			isValid = entitytermsforlanguageview.isValid();
			return isValid;
		} );

		return isValid;
	},

	/**
	 * @return {boolean}
	 */
	isInitialValue: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter(),
			isInitialValue = true;

		listview.items().each( function() {
			var entitytermsforlanguageview = lia.liInstance( $( this ) );
			isInitialValue = entitytermsforlanguageview.isInitialValue();
			return isInitialValue;
		} );

		return isInitialValue;
	},

	startEditing: function() {
		if ( this._isInEditMode ) {
			return;
		}

		this._isInEditMode = true;
		this.element.addClass( 'wb-edit' );

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		listview.items().each( function() {
			var entitytermsforlanguageview = lia.liInstance( $( this ) );
			entitytermsforlanguageview.startEditing();
		} );

		this.updateInputSize();

		this._trigger( 'afterstartediting' );
	},

	/**
	 * @param {boolean} [dropValue]
	 */
	stopEditing: function( dropValue ) {
		var self = this;

		if ( !this._isInEditMode || ( !this.isValid() || this.isInitialValue() ) && !dropValue ) {
			return;
		}

		dropValue = !!dropValue;

		this._trigger( 'stopediting', null, [dropValue] );

		this.disable();

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		// TODO: This widget should not need to queue the requests of its encapsulated widgets.
		// However, the back-end produces edit conflicts when issuing multiple requests at once.
		// Remove queueing as soon as the back-end is fixed; see bug T74020.
		var $queue = $( {} ),
			eventNamespace = 'entitytermsforlanguagelistviewstopediting';

		/**
		 * @param {jQuery} $queue
		 * @param {jQuery.wikibase.entitytermsforlanguageview} entitytermsforlanguageview
		 * @param {boolean} dropValue
		 */
		function addStopEditToQueue( $queue, entitytermsforlanguageview, dropValue ) {
			$queue.queue( 'stopediting', function( next ) {
				entitytermsforlanguageview.element
				.one( 'entitytermsforlanguageviewafterstopediting.' + eventNamespace,
					function( event ) {
						entitytermsforlanguageview.element.off( '.' + eventNamespace );
						setTimeout( next, 0 );
					}
				)
				.one( 'entitytermsforlanguageviewtoggleerror.' + eventNamespace,
					function( event ) {
						entitytermsforlanguageview.element.off( '.' + eventNamespace );
						$queue.clearQueue();
						self._resetEditMode();
					}
				);
				entitytermsforlanguageview.stopEditing( dropValue );
			} );
		}

		listview.items().each( function() {
			var entitytermsforlanguageview = lia.liInstance( $( this ) );
			addStopEditToQueue(
				$queue,
				entitytermsforlanguageview,
				dropValue || entitytermsforlanguageview.isInitialValue()
			);
		} );

		$queue.queue( 'stopediting', function() {
			self._afterStopEditing( dropValue );
		} );

		$queue.dequeue( 'stopediting' );
	},

	_resetEditMode: function() {
		this.enable();

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		listview.items().each( function() {
			var entitytermsforlanguageview = lia.liInstance( $( this ) );
			entitytermsforlanguageview.startEditing();
		} );
	},

	/**
	 * @param {boolean} dropValue
	 */
	_afterStopEditing: function( dropValue ) {
		if ( !dropValue ) {
			this.options.value = this.value();
		}
		this._isInEditMode = false;
		this.enable();
		this.element.removeClass( 'wb-edit' );
		this._trigger( 'afterstopediting', null, [dropValue] );
	},

	cancelEditing: function() {
		this.stopEditing( true );
	},

	/**
	 * Updates the size of the input boxes by triggering the inputautoexpand plugin's `expand()`
	 * function.
	 */
	updateInputSize: function() {
		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		listview.items().each( function() {
			var entitytermsforlanguageview = lia.liInstance( $( this ) );

			$.each( ['label', 'description', 'aliases'], function() {
				var $view = entitytermsforlanguageview['$' + this + 'view'],
					autoExpandInput = $view.find( 'input,textarea' ).data( 'inputautoexpand' );

				if ( autoExpandInput ) {
					autoExpandInput.options( {
						maxWidth: $view.width()
					} );
					autoExpandInput.expand( true );
				}
			} );
		} );
	},

	/**
	 * @see jQuery.ui.TemplatedWidget.focus
	 */
	focus: function() {
		var listview = this.$listview.data( 'listview' ),
			$items = listview.items();

		if ( $items.length ) {
			listview.listItemAdapter().liInstance( $items.first() ).focus();
		} else {
			this.element.focus();
		}
	},

	/**
	 * Applies/Removes error state.
	 *
	 * @param {Error} [error]
	 */
	setError: function( error ) {
		if ( error ) {
			this.element.addClass( 'wb-error' );
			this._trigger( 'toggleerror', null, [error] );
		} else {
			this.removeError();
			this._trigger( 'toggleerror' );
		}
	},

	removeError: function() {
		this.element.removeClass( 'wb-error' );

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		listview.items().each( function() {
			var entitytermsforlanguageview = lia.liInstance( $( this ) );
			entitytermsforlanguageview.removeError();
		} );
	},

	/**
	 * @param {Fingerprint} [value]
	 * @return {Fingerprint|*}
	 */
	value: function( value ) {
		if ( value !== undefined ) {
			return this.option( 'value', value );
		}

		var listview = this.$listview.data( 'listview' ),
			lia = listview.listItemAdapter();

		// Clones the current Fingerprint.
		// FIXME: This accesses the private _items property since there is no copy or clone.
		value = new wb.datamodel.Fingerprint(
			new wb.datamodel.TermMap( this.options.value.getLabels()._items ),
			new wb.datamodel.TermMap( this.options.value.getDescriptions()._items ),
			new wb.datamodel.MultiTermMap( this.options.value.getAliases()._items )
		);

		// this only adds all terms visible in the ui to the Fingerprint, all other languages get ignored
		listview.items().each( function() {
			var terms = lia.liInstance( $( this ) ).value();
			value.setLabel( terms.language, terms.label );
			value.setDescription( terms.language, terms.description );
			value.setAliases( terms.language, terms.aliases );
		} );

		return value;
	},

	/**
	 * @see jQuery.ui.TemplatedWidget._setOption
	 */
	_setOption: function( key, value ) {
		if ( key === 'value' ) {
			throw new Error( 'Impossible to set value after initialization' );
		}

		var response = PARENT.prototype._setOption.apply( this, arguments );

		if ( key === 'disabled' ) {
			this.$listview.data( 'listview' ).option( key, value );
		}

		return response;
	}
} );

}( mediaWiki, wikibase, jQuery ) );
