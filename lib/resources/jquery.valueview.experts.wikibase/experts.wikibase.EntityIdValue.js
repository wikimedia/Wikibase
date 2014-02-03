/**
 * @licence GNU GPL v2+
 * @author Daniel Werner < daniel.werner@wikimedia.de >
 */
( function( mw, wb, $, vv ) {
	'use strict';

	// temporarily define a hard coded prefix map until we get this from the server
	var WB_ENTITIES_PREFIXMAP = {
		'Q': 'item',
		'P': 'property'
	};

	var PARENT = vv.BifidExpert,
		editableExpert = vv.experts.wikibase.EntityIdInput;

	/**
	 * Helper for building a pretty link or info about an Entity.
	 *
	 * @param {string} entityId
	 * @param {wikibase.store.EntityStore} entityStore
	 * @return jQuery
	 */
	function buildEntityRefDom( entityId, entityStore ) {
		var $container = $( '<span/>' ),
			deferred = $.Deferred();

		entityStore.get( entityId )
		.done( function( fetchedEntity ) {
			var $dom;

			if( !fetchedEntity ) {
				// Entity missing or deleted, generate info:
				$dom = wb.utilities.ui.buildMissingEntityInfo( entityId, wb.Item );
			}

			var $label = wb.utilities.ui.buildPrettyEntityLabel( fetchedEntity.getContent() );

			$dom = $( '<a/>', {
				href: fetchedEntity.getTitle().getUrl()
			} ).append( $label );

			deferred.resolve( $dom );
		} );

		deferred.promise().done( function( $dom ) {
			$container.append( $dom );
		} );

		return $container;
	}

	/**
	 * Valueview expert for handling Wikibase Entity references.
	 *
	 * @since 0.4
	 *
	 * @constructor
	 * @extends jQuery.valueview.experts.StringValue
	 */
	vv.experts.wikibase.EntityIdValue = vv.expert( 'entityidvalue', PARENT, {
		/**
		 * @see jQuery.valueview.BifidExpert._editableExpert
		 */
		_editableExpert: editableExpert,

		/**
		 * @see jQuery.valueview.BifidExpert._staticExpert
		 */
		_staticExpert: vv.experts.StaticDom,

		/**
		 * @see jQuery.valueview.BifidExpert._staticExpertOptions
		 */
		_staticExpertOptions: {
			domBuilder: function( currentRawValue, viewState ) {
				if( !currentRawValue ) {
					return '';
				}

				// We have to check for string or instance of wb.EntityId since the EntityIdInput
				// expert has this huge flaw that it takes a wb.EntityId but returns a string as
				// raw value. This is all related to the current entity ID mess.
				var entityId = currentRawValue instanceof wb.EntityId
					? currentRawValue.getPrefixedId( WB_ENTITIES_PREFIXMAP )
					: currentRawValue;

				// domBuilder is called on StaticDom._options, which has entityStore
				return buildEntityRefDom( entityId, this.entityStore );
			},
			baseExpert: editableExpert
		},

		/**
		 * @see jQuery.valueview.Expert.valueCharacteristics
		 */
		valueCharacteristics: function() {
			return { prefixmap: WB_ENTITIES_PREFIXMAP };
		}
	} );

	// Make the above expert available for wb.EntityId data value handling.
	// TODO: Move this in some kind of higher initialization file once we have more like this:
	mw.ext.valueView.expertProvider.registerExpert(
		wb.EntityId,
		vv.experts.wikibase.EntityIdValue
	);

}( mediaWiki, wikibase, jQuery, jQuery.valueview ) );
