wikibase.view.ViewController = ( function( wb ) {
'use strict';

/**
 * A controller for editing wikibase datamodel values through wikibase views
 *
 * @class wikibase.view.ViewController
 * @licence GNU GPL v2+
 * @since 0.5
 * @author Adrian Heine < adrian.heine@wikimedia.de >
 * @abstract
 * @constructor
 */
var SELF = function() {};

/**
 * Start editing
 */
SELF.prototype.startEditing = util.abstractMember;

/**
 * Stop editing
 *
 * @param {boolean} dropValue Whether the current value should be kept and
 * persisted or dropped
 */
SELF.prototype.stopEditing = util.abstractMember;

/**
 * Cancel editing and drop value
 */
SELF.prototype.cancelEditing = util.abstractMember;

/**
 * Set or clear error
 *
 * @param {mixed|undefined} [error] The error or undefined, if error should be
 * cleared
 */
SELF.prototype.setError = util.abstractMember;

/**
 * Remove the value currently represented in the view
 */
SELF.prototype.remove = util.abstractMember;

return SELF;

} )( wikibase );
