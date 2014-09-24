/**
 * @licence GNU GPL v2+
 * @author Marius Hoch < hoo@online.de >
 */
( function( wb, $ ) {
'use strict';

var PARENT = wb.RepoApi;

/**
 * Provides abstracted access functions for the Wikibase Repo Api handling and returning Wikibase
 * data model objects.
 * @constructor
 * @since 0.4
 * @todo Allow passing actual data model objects to the functions.
 * @todo Return RepoApiError objects when failing.
 */
wb.AbstractedRepoApi = util.inherit( 'wbAbstractedRepoApi', PARENT, {
	/**
	 * Adds a new or updates an existing Reference of a Statement.
	 *
	 * @since 0.4
	 *
	 * @param {string} statementGuid
	 * @param {wb.datamodel.SnakList} snaks
	 * @param {number} baseRevId
	 * @param {string} [referenceHash] A hash of the reference that should be updated.
	 *        If not provided, a new reference is created.
	 * @param {number} [index] The new reference's index. Only needs to be specified if the
	 *        reference's index within the list of all the statement's references shall be changed.
	 * @return {jQuery.Promise} If resolved, this will get a wb.datamodel.Reference object as first parameter
	 *         and the last base revision as second parameter.
	 */
	setReference: function( statementGuid, snaks, baseRevId, referenceHash, index ) {
		return this._abstract(
			PARENT.prototype.setReference.call(
				this, statementGuid, snaks.toJSON(), baseRevId, referenceHash, index
			),
			function( result ) {
				return [
					wb.datamodel.Reference.newFromJSON( result.reference ),
					result.pageinfo
				];
			}
		);
	},

	/**
	 * Creates/Updates an entire claim.
	 *
	 * @param {wb.datamodel.Claim|wb.datamodel.Statement} claim
	 * @param {number} baseRevId
	 * @param {number} [index]
	 * @return {jQuery.Promise}
	 */
	setClaim: function( claim, baseRevId, index ) {
		return this._abstract(
			PARENT.prototype.setClaim.call( this, claim.toJSON(), baseRevId, index ),
			function( result ) {
				return [
					wb.datamodel.Claim.newFromJSON( result.claim ),
					result.pageinfo
				];
			}
		);
	},

	/**
	 * Creates a claim.
	 * @todo Needs testing. It would be necessary to create a property for creating a claim.
	 *       The API does not support setting a data type for an entity at the moment.
	 *
	 * @param {String} entityId Entity id
	 * @param {Number} baseRevId revision id
	 * @param {wb.datamodel.Snak} mainSnak The new Claim's Main Snak.
	 * @return {jQuery.Promise} When resolved, the first parameter in callbacks is the saved
	 *         wb.datamodel.Claim object which holds its final GUID.
	 */
	createClaim: function( entityId, baseRevId, mainSnak ) {
		var params = this._claimApiParams( mainSnak );
		return this._abstract(
			PARENT.prototype.createClaim.call(
				this, entityId, baseRevId, params.snaktype, params.property, params.value
			),
			this._claimApiCallback
		);
	},

	/**
	 * Changes the Main Snak of an existing claim.
	 * @todo Needs testing just like createClaim()!
	 *
	 * @param {String} claimGuid The GUID of the Claim to be changed (wb.datamodel.Claim.getGuid)
	 * @param {Number} baseRevId
	 * @param {wb.datamodel.Snak} mainSnak The new value to be set as the claims Main Snak.
	 * @return {jQuery.Promise} When resolved, the first parameter in callbacks is the changed
	 *         wb.datamodel.Claim object with the updated Main Snak.
	 */
	setClaimValue: function( claimGuid, baseRevId, mainSnak ) {
		var params = this._claimApiParams( mainSnak );
		return this._abstract(
			PARENT.prototype.setClaimValue.call(
				this, claimGuid, baseRevId, params.snaktype, params.property, params.value
			),
			this._claimApiCallback
		);
	},

	/**
	 * Helper function for createClaim and setClaimValue. Both have very similar parameters.
	 *
	 * @since 0.4
	 *
	 * @param {wb.datamodel.Snak} mainSnak
	 * @return {object}
	 *
	 * @throws {Error} If no Snak instance is given as second parameter
	 */
	_claimApiParams: function( mainSnak ) {
		if( !mainSnak instanceof wb.datamodel.Snak ) {
			throw new Error( 'A wikibase.datamodel.Snak object is required as Main Snak' );
		}
		var snakJson = mainSnak.toJSON(),
			params = {
				snaktype: mainSnak.getType(),
				// NOTE: currently 'wbsetclaimvalue' API allows to change snak type but not property,
				//  set it anyhow. Returned promise won't propagate the API warning we will get here.
				property: snakJson.property
			};

		if( snakJson.datavalue !== undefined ) {
			params.value = snakJson.datavalue.value;
		} else {
			params.value = null;
		}

		return params;
	},

	/**
	 * Handles the results of claim api calls
	 *
	 * @since 0.4
	 *
	 * @param {object} result
	 * @return {object}
	 */
	_claimApiCallback: function( result ) {
		return [
			wb.datamodel.Claim.newFromJSON( result.claim ),
			result.pageinfo
		];
	},

	/**
	 * Applies a callback function to the result of a successfully resolved promise, finally
	 * returning a new promise with the callback's result.
	 * @since 0.4
	 *
	 * @param {jQuery.Promise} apiPromise
	 * @param {Function} callbackForAbstraction
	 * @return {jQuery.Promise}
	 */
	_abstract: function( apiPromise, callbackForAbstraction ) {
		var deferred = $.Deferred(),
			self = this;

		apiPromise
		.done( function() {
			var args = callbackForAbstraction.apply( self, arguments );
			deferred.resolve.apply( deferred, args );
		} )
		.fail( deferred.reject );

		return deferred.promise();
	}
} );

}( wikibase, jQuery ) );
