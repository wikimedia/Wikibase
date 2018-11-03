/**
 * @license GPL-2.0-or-later
 * @author H. Snater < mediawiki@snater.com >
 */
( function ( mw, $ ) {
	'use strict';

	/**
	 * Template cache that stores the parameter types templates have been generated with. These
	 * templates do not need to be validated anymore allowing to skip the validation process.
	 * @type {Object}
	 */
	var cache = {};

	/**
	 * Returns the type of the specified parameter.
	 *
	 * @param {*} param
	 * @return {string}
	 */
	function getParameterType( param ) {
		return ( param instanceof jQuery ) ? 'jQuery' : typeof param;
	}

	/**
	 * Checks whether a specific template has been initialized with the types of the specified
	 * parameters before.
	 *
	 * @param {string} key Template id.
	 * @param {*[]} params
	 * @return {boolean}
	 */
	function areCachedParameterTypes( key, params ) {
		if ( !cache[ key ] ) {
			return false;
		}

		for ( var i = 0; i < cache[ key ].length; i++ ) {
			if ( params.length !== cache[ key ][ i ].length ) {
				return false;
			}

			for ( var j = 0; j < params.length; j++ ) {
				if ( getParameterType( params[ j ] ) !== cache[ key ][ i ][ j ] ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Strips HTML tags that may be generated automatically like <tbody> as well as all node
	 * attributes.
	 *
	 * @param {string} string
	 * @return {string}
	 */
	function stripAutoGeneratedHtml( string ) {
		string = string.replace( /<\/?t(?:head|body|foot)\b[^>]*>/gi, '' );

		// strip white space between tags as well since it might cause interference
		string = string.replace( />\s+</g, '><' );

		// Strip all attributes since they are not necessary for validating the HTML and would cause
		// interference in Firefox which re-converts &lt; and &gt; back to < and > when parsing by
		// setting through $.html().
		// Additionally, rip off any XML notation since jQuery will parse to HTML.
		return string.replace( /(<\w+)\b(?:[^"'>]+|"[^"]*"|'[^']*')*>/g, '$1>' );
	}

	/**
	 * Checks whether the HTML to be created out of a jQuery wrapped element is actually valid.
	 *
	 * @param {mediaWiki.Template} template
	 * @param {jQuery} $wrappedTemplate
	 * @return {boolean}
	 */
	function isValidHtml( template, $wrappedTemplate ) {
		// HTML node automatically creates a body tag for certain elements that fit right into the
		// body - not for tags like <tr>:
		var parsedTemplate = ( $wrappedTemplate.children( 'body' ).length )
			? $wrappedTemplate.children( 'body' ).html()
			: $wrappedTemplate.html();

		var strippedTemplate = stripAutoGeneratedHtml( template.plain() ),
			strippedParsedTemplate = stripAutoGeneratedHtml( parsedTemplate );

		// Nodes or text got lost while being parsed which indicates that the generated HTML would
		// be invalid:
		return strippedTemplate === strippedParsedTemplate;
	}

	/**
	 * Adds a template to the cache.
	 *
	 * @param {string} key Template id.
	 * @param {*[]} params Original template parameters.
	 */
	function addToCache( key, params ) {
		var paramTypes = [];

		if ( !cache[ key ] ) {
			cache[ key ] = [];
		}

		for ( var i = 0; i < params.length; i++ ) {
			var parameterType = getParameterType( params[ i ] );
			if ( parameterType === 'object' ) {
				// Cannot handle some generic object.
				return;
			} else {
				paramTypes.push( parameterType );
			}
		}

		cache[ key ].push( paramTypes );
	}

	/*
	 * Object constructor for templates.
	 * mediawiki.jQueryMsg replaces mw.Message's native simple parser method performing some
	 * replacements when certain characters are detected in the message string. Since such replacing
	 * could interfere with templates, the simple parser is re-implemented in the Template
	 * constructor.
	 *
	 * @constructor
	 */
	mw.WbTemplate = function () { mw.Message.apply( this, arguments ); };
	mw.WbTemplate.prototype = $.extend(
		{},
		mw.Message.prototype,
		{ constructor: mw.WbTemplate }
	);

	/**
	 * Returns the parsed plain template. (Overridden due to IE8 returning objects instead of
	 * strings from mw.Message's native plain() method.)
	 *
	 * @see mw.Message.plain
	 *
	 * @return {string}
	 */
	mw.WbTemplate.prototype.plain = function () {
		return this.parser();
	};

	/**
	 * @see mw.Message.parser
	 *
	 * @return {string}
	 */
	mw.WbTemplate.prototype.parser = function () {
		var parameters = this.parameters;
		return this.map.get( this.key ).replace( /\$(\d+)/g, function ( str, match ) {
			var index = parseInt( match, 10 ) - 1;
			return parameters[ index ] !== undefined ? parameters[ index ] : '$' + match;
		} );
	};

	/**
	 * Returns a template filled with the specified parameters, similar to wfTemplate().
	 *
	 * @see mw.message
	 *
	 * @param {string} key Key of the template to get.
	 * @param {string|string[]|jQuery} [parameter1] First argument in a list of variadic arguments,
	 *        each a parameter for $N replacement in templates. Instead of making use of variadic
	 *        arguments, an array may be passed as first parameter.
	 * @return {jQuery}
	 *
	 * @throws {Error} if the generated template's HTML is invalid.
	 */
	mw.wbTemplate = function ( key, parameter1 /* [, parameter2[, ...]] */ ) {
		var i,
			params = [],
			template,
			$wrappedTemplate,
			tempParams = [],
			delayedParams = [];

		if ( parameter1 !== undefined ) {
			if ( Array.isArray( parameter1 ) ) {
				params = parameter1;
			} else { // support variadic arguments
				params = Array.prototype.slice.call( arguments );
				params.shift();
			}
		}

		// Pre-parse the template inserting strings and placeholder nodes for jQuery objects jQuery
		// objects will be appended after the template has been parsed to not lose any references:
		for ( i = 0; i < params.length; i++ ) {
			if ( typeof params[ i ] === 'string' || params[ i ] instanceof String ) {
				// insert strings into the template directly but have them parsed by the browser
				// to detect HTML entities properly (e.g. a &nbsp; in Firefox would show up as a
				// space instead of an entity which would cause an invalid HTML error)
				tempParams.push( $( '<div/>' ).html( mw.html.escape( params[ i ] ) ).html() );
			} else if ( params[ i ] instanceof jQuery ) {
				// construct temporary placeholder nodes
				// (using an actual invalid class name to not interfere with any other node)
				var nodeName = params[ i ][ 0 ].nodeName.toLowerCase();
				tempParams.push( '<' + nodeName + ' class="--mwTemplate"></' + nodeName + '>' );
				delayedParams.push( params[ i ] );
			} else {
				throw new Error( 'mw.WbTemplate: Wrong parameter type. Pass either String or jQuery.' );
			}
		}

		template = new mw.WbTemplate( mw.wbTemplates.store, key, tempParams );

		// Wrap template inside a html container to be able to easily access all temporary nodes and
		// insert any jQuery objects:
		$wrappedTemplate = $( '<html/>' ).html( template.plain() );

		if ( !areCachedParameterTypes( key, params ) ) {
			if ( !isValidHtml( template, $wrappedTemplate ) ) {
				throw new Error( 'mw.wbTemplate: Tried to generate invalid HTML for template "'
					+ key + '"' );
			}
			addToCache( key, params );
		}

		// Replace temporary nodes with actual jQuery nodes:
		$wrappedTemplate.find( '.--mwTemplate' ).each( function ( i ) {
			$( this ).replaceWith( delayedParams[ i ] );
		} );

		return ( $wrappedTemplate.children( 'body' ).length )
			? $wrappedTemplate.children( 'body' ).contents()
			: $wrappedTemplate.contents();
	};

	/**
	 * Fetches a template and fills it with specified parameters. The template has to have a single
	 * root DOM element. All of its child nodes will then be appended to the jQuery object's DOM
	 * nodes.
	 *
	 * @see mw.template
	 *
	 * @param {string} template
	 * @param {string|string[]|jQuery} parameter1 First argument in a list of variadic arguments,
	 *        each a parameter for $N replacement in templates. Instead of making use of variadic
	 *        arguments, an array may be passed as first parameter.
	 * @return {jQuery}
	 */
	$.fn.applyTemplate = function ( template, parameter1 /* [, parameter2[, ...]] */ ) {
		var $template = mw.wbTemplate.apply( null, arguments );

		if ( $template.length !== 1 ) {
			throw new Error( 'Can not apply a template with more or less than one root node.' );
		}

		var attributes = $template.getAttrs();

		for ( var name in attributes ) {
			if ( name === 'class' ) {
				this.addClass( attributes[ name ] );
			} else if ( Object.prototype.hasOwnProperty.call( attributes, name ) ) {
				this.attr( name, attributes[ name ] );
			}
		}

		this.empty().append( $template.contents() );

		return this;
	};

}( mediaWiki, jQuery ) );
