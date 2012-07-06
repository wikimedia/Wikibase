<?php

namespace STTLanguage;

/**
 * File defining the hook handlers for the 'Stick to That Language' extension.
 *
 * @since 0.1
 *
 * @file StickToThatLanguage.hooks.php
 * @ingroup STTLanguage
 *
 * @licence GNU GPL v2+
 * @author Daniel Werner
 */
final class Hooks {
	/**
	 * Registers PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.1
	 *
	 * @param array &$files
	 * @return bool
	 */
	public static function registerUnitTests( array &$files ) {
		$files[] = Ext::getDir() . '/tests/phpunit/ExtTest.php'; // STTLanguage\Ext (extension class)
		return true;
	}

	/**
	 * Adds the user preference for choosing other languages the user can speak.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * @since 0.1
	 *
	 * @param \User $user
	 * @param array &$preferences
	 * @return bool
	 */
	public static function onGetPreferences( \User $user, array &$preferences ) {
		$preferences['sttl-languages'] = array(
			'type' => 'multiselect',
			'usecheckboxes' => false,
			'label-message' => 'sttl-setting-languages',
			'options' => $preferences['language']['options'], // all languages available in 'language' selector
			'section' => 'personal/i18n',
			'prefix' => 'sttl-languages-',
		);

		return true;
	}

	/**
	 * Called after fetching the core default user options.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGetDefaultOptions
	 *
	 * @param array &$defaultOptions
	 * @return bool
	 */
	public static function onUserGetDefaultOptions( array &$defaultOptions ) {
		// pre-select default language in the list of fallback languages
		$defaultLang = $defaultOptions['language'];
		$defaultOptions[ 'sttl-languages-' . $defaultLang ] = 1;

		return true;
	}

	/**
	 * Used to build the global language selector if activated
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateOutputPageBeforeExec
	 *
	 * @since 0.1
	 *
	 * @param \SkinTemplate $sk
	 * @param \QuickTemplate $tpl
	 * @return bool
	 */
	public static function onSkinTemplateOutputPageBeforeExec( \SkinTemplate &$sk, \QuickTemplate &$tpl ) {
		global $egSTTLanguageDisplaySelector, $egSTTLanguageTopLanguages;
		if( ! $egSTTLanguageDisplaySelector ) {
			return true; // option for disabling the selector is active
		}

		// NOTE: usually we would add the module styles for the selector here, but apparently at this point
		//       it is too late to add any styles to the OutputPage.

		// Title of our item:
		$title = $sk->getOutput()->getTitle();
		$user = $sk->getUser();

		$langUrls = array();
		$topLangUrls = array();

		$topLanguages = $user->isLoggedIn()
			? Ext::getUserLanguageCodes( $user ) // display users preferred languages on top
			: $egSTTLanguageTopLanguages;

		foreach( \Language::fetchLanguageNames() as $code => $name ) {
			if( $code === $sk->getLanguage()->getCode() ) {
				continue; // don't add language the page is displayed in
			}

			// build information for the skin to generate links for all languages:
			$url = array(
				'href' => $title->getFullURL( array( 'uselang' => $code ) ),
				'text' => $name,
				'title' => $title->getText(),
				'class' => "sttl-lang-$code", // site-links use 'interwiki-' which seems inappropriate in this case
				'lang' => $code,
				'hreflang' => $code,
			);

			$topIndex =  array_search( $code, $topLanguages );
			if( $topIndex !== false ) {
				// language is considered a 'top' language
				$url['class'] .= ' sttl-toplang';
				$topLangUrls[ $topIndex ] = $url;
			} else {
				$langUrls[] = $url;
			}
		}

		if( count( $topLangUrls ) ) { // can be empty when the only language in here is the one displayed!
			// make sure top languages are still in defined order and there are no gaps:
			ksort( $topLangUrls );
			$topLangUrls = array_values( $topLangUrls );
			// add another css class for the last preferred, after that, other languages start:
			$topLangUrls[ count( $topLangUrls ) - 1 ]['class'] .= ' sttl-lasttoplang';
		}

		// put preferred languages on top and add others:
		$language_urls = array_merge( $topLangUrls, $langUrls );

		// define these languages as languages for the sitebar within the skin:
		$tpl->setRef( 'language_urls', $language_urls );

		return true;
	}

	/**
	 * Used to include the language selectors required resource loader module.
	 *
	 * NOTE: can't do this in onSkinTemplateOutputPageBeforeExec. Seems like at that point of time it is not
	 *       possible to add them.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/BeforePageDisplay
	 *
	 * @since 0.1
	 *
	 * @param \OutputPage $out
	 * @param \Skin $skin
	 * @return bool
	 */
	public static function onBeforePageDisplay( \OutputPage &$out, \Skin &$skin ) {
		// add styles for the language selector:
		global $egSTTLanguageDisplaySelector;

		if( $egSTTLanguageDisplaySelector ) {
			// add styles for language selector:
			$out->addModuleStyles( 'sticktothatlanguage' );
		}

		return true;
	}

	/**
	 * Makes the language sticky (mostly for links of the page content)
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LinkBegin
	 *
	 * @param $skin
	 * @param $target
	 * @param $text
	 * @param $customAttribs
	 * @param $query
	 * @param $options
	 * @param $ret
	 * @return bool true
	 */
	public static function onLinkBegin( $skin, $target, &$text, &$customAttribs, &$query, &$options, &$ret ) {
		global $wgLang, $wgLanguageCode;

		if( is_string( $query ) ) {
			// this check is not yet in MW core (pending on review in 1.20). This can actually be a string
			$query = wfCgiToArray( $query );
		}

		if( $wgLang->getCode() !== $wgLanguageCode   // cache friendly!
			&& array_key_exists( 'uselang', $query ) // don't add it if there is a uselang set to that url already!
		) {
			// this will add the 'uselang' parameter to each link generated with Linker::link()
			$query[ 'uselang' ] = $wgLang->getCode();
		}
		return true;
	}

	/**
	 * Makes the language stick (mostly for links other than the page content)
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetLocalURL::Internal
	 *
	 * @since 0.1
	 *
	 * @param \Title $title
	 * @param string $url
	 * @return bool true
	 */
	public static function onGetLocalUrlInternally( \Title $title, &$url ) {
		global $wgLang, $wgLanguageCode;
		if( $wgLang->getCode() !== $wgLanguageCode    // squid-cache friendly!
			&& !preg_match( '/[&\?]uselang=/i', $url ) // don't add it if there is a uselang set to that url already!
		) {
			// this will add the 'uselang' parameter to each link returned by Title::getLocalURL()
			$url = wfAppendQuery( $url, 'uselang=' . $wgLang->getCode() );
		}
		return true;
	}

	/**
	 * Used to make the language sticky for all forms. This is done by actually grabbing the php output which is about
	 * to be sent to the user and running a regular expression over it to get the 'uselang' parameter into the post
	 * request of the form.
	 *
	 * @Todo: this is the top of the hackiness of this extension. It's double evil since we are running a regular
	 *        expression on the overall output, and because we are grabbing the output buffer directly and setting its
	 *        value with the regex-modified version of the output. This should be done differently, probably a cookie
	 *        solution would be the best after all.
	 *
	 * @since 0.1
	 *
	 * @param \OutputPage $out
	 * @return bool true
	 */
	public static function onAfterFinalPageOutput( \OutputPage $out ) {
		global $wgLang;
		$startTime = microtime();

		// removes everything from the output buffer and returns it, so we can actually modify it:
		$output = ob_get_clean();

		// regex which picks <form> if it is valid HTML (allows self-closing tags):
		/*
		$recursiveDomRegex = '(?P<innerDOM> <(\w+)(?:\s+[^>]*|)>(?: (?> (?!<\w+(?:\s+[^>]*|)[^\/]> | <\/\w+> ).)* | (?&innerDOM) )*?<\/ \4 > )*';
		$regex = '/(<form(?:\s+[^>]*|)>)(' . $recursiveDomRegex . '<\/form>)/xs';
		*/
		// NOTE: recursive regex will fail on action=edit since there would be too many recursions... lets build a simple one:
		$regex = '/(<form(?:\s+[^>]*|)>)()/s'; // $2 just to keep it compatible to recursive regex above when testing^^

		// the hidden input field we want to inject into each <form> field:
		$langInfo = \Html::hidden( 'uselang', $wgLang->getCode() );

		// replacement contains the hidden input field
		$replacement = "\n" . '$1' . $langInfo . "\n" . '<!-- STTLanguage hacked this form -->' . '$2';

		// replace <form>'s content with content + hidden 'uselang' field
		echo preg_replace( $regex, $replacement, $output );
		echo sprintf( '<!-- STTLanguage <form> \'uselang\' injection done in  %01.3f secs -->', microtime() - $startTime ) . "\n";

		return true;
	}
}
