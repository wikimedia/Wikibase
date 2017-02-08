<?php

/**
 * Welcome to the inside of Wikibase,              <>
 * the software that powers                   /\        /\
 * Wikidata and other                       <{  }>    <{  }>
 * structured data websites.        <>   /\   \/   /\   \/   /\   <>
 *                                     //  \\    //  \\    //  \\
 * It is Free Software.              <{{    }}><{{    }}><{{    }}>
 *                                /\   \\  //    \\  //    \\  //   /\
 *                              <{  }>   ><        \/        ><   <{  }>
 *                                \/   //  \\              //  \\   \/
 *                            <>     <{{    }}>     +--------------------------+
 *                                /\   \\  //       |                          |
 *                              <{  }>   ><        /|  W  I  K  I  B  A  S  E  |
 *                                \/   //  \\    // |                          |
 * We are                            <{{    }}><{{  +--------------------------+
 * looking for people                  \\  //    \\  //    \\  //
 * like you to join us in           <>   \/   /\   \/   /\   \/   <>
 * developing it further. Find              <{  }>    <{  }>
 * out more at http://wikiba.se               \/        \/
 * and join the open data revolution.              <>
 */

/**
 * Entry point for the Wikibase Repository extension.
 *
 * @see README.md
 * @see https://www.mediawiki.org/wiki/Extension:Wikibase_Repository
 *
 * @license GPL-2.0+
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entry point.' );
}

if ( defined( 'WB_VERSION' ) ) {
	// Do not initialize more than once.
	return 1;
}

define( 'WB_VERSION', '0.5 alpha' );

// Needs to be 1.26c because version_compare() works in confusing ways.
if ( version_compare( $GLOBALS['wgVersion'], '1.26c', '<' ) ) {
	die( "<b>Error:</b> Wikibase requires MediaWiki 1.26 or above.\n" );
}

/**
 * Registry of ValueParsers classes or factory callbacks, by datatype.
 * @note: that parsers are also registered under their old names for backwards compatibility,
 * for use with the deprecated 'parser' parameter of the wbparsevalue API module.
 */
$GLOBALS['wgValueParsers'] = array();

// Include the WikibaseLib extension if that hasn't been done yet, since it's required for Wikibase to work.
if ( !defined( 'WBL_VERSION' ) ) {
	include_once __DIR__ . '/../lib/WikibaseLib.php';
}

if ( !defined( 'WBL_VERSION' ) ) {
	throw new Exception( 'Wikibase depends on the WikibaseLib extension.' );
}

if ( !defined( 'WIKIBASE_VIEW_VERSION' ) ) {
	include_once __DIR__ . '/../view/WikibaseView.php';
}

if ( !defined( 'WIKIBASE_VIEW_VERSION' ) ) {
	throw new Exception( 'Wikibase depends on WikibaseView.' );
}

call_user_func( function() {
	global $wgExtensionCredits, $wgGroupPermissions, $wgGrantPermissions, $wgAvailableRights;
	global $wgExtensionMessagesFiles, $wgMessagesDirs;
	global $wgAPIModules, $wgAPIListModules, $wgSpecialPages, $wgHooks;
	global $wgWBRepoSettings, $wgResourceModules, $wgValueParsers, $wgJobClasses;
	global $wgWBRepoDataTypes, $wgWBRepoEntityTypes, $wgEntityPrefixSearchProfiles,
	       $wgCirrusSearchRescoreProfiles, $wgCirrusSearchRescoreFunctionScoreChains;

	$wgExtensionCredits['wikibase'][] = array(
		'path' => __DIR__,
		'name' => 'Wikibase Repository',
		'version' => WB_VERSION,
		'author' => array(
			'The Wikidata team',
		),
		'url' => 'https://www.mediawiki.org/wiki/Extension:Wikibase',
		'descriptionmsg' => 'wikibase-desc',
		'license-name' => 'GPL-2.0+'
	);

	// Registry and definition of data types
	$wgWBRepoDataTypes = require __DIR__ . '/../lib/WikibaseLib.datatypes.php';

	$repoDataTypes = require __DIR__ . '/WikibaseRepo.datatypes.php';

	// merge WikibaseRepo.datatypes.php into $wgWBRepoDataTypes
	foreach ( $repoDataTypes as $type => $repoDef ) {
		$baseDef = isset( $wgWBRepoDataTypes[$type] ) ? $wgWBRepoDataTypes[$type] : array();
		$wgWBRepoDataTypes[$type] = array_merge( $baseDef, $repoDef );
	}

	// constants
	define( 'CONTENT_MODEL_WIKIBASE_ITEM', "wikibase-item" );
	define( 'CONTENT_MODEL_WIKIBASE_PROPERTY', "wikibase-property" );

	// Registry and definition of entity types
	$wgWBRepoEntityTypes = require __DIR__ . '/../lib/WikibaseLib.entitytypes.php';

	$repoEntityTypes = require __DIR__ . '/WikibaseRepo.entitytypes.php';

	// merge WikibaseRepo.entitytypes.php into $wgWBRepoEntityTypes
	foreach ( $repoEntityTypes as $type => $repoDef ) {
		$baseDef = isset( $wgWBRepoEntityTypes[$type] ) ? $wgWBRepoEntityTypes[$type] : array();
		$wgWBRepoEntityTypes[$type] = array_merge( $baseDef, $repoDef );
	}

	// rights
	// names should be according to other naming scheme
	$wgGroupPermissions['*']['item-term'] = true;
	$wgGroupPermissions['*']['property-term'] = true;
	$wgGroupPermissions['*']['item-merge'] = true;
	$wgGroupPermissions['*']['item-redirect'] = true;
	$wgGroupPermissions['*']['property-create'] = true;

	$wgAvailableRights[] = 'item-term';
	$wgAvailableRights[] = 'property-term';
	$wgAvailableRights[] = 'item-merge';
	$wgAvailableRights[] = 'item-redirect';
	$wgAvailableRights[] = 'property-create';

	$wgGrantPermissions['editpage']['item-term'] = true;
	$wgGrantPermissions['editpage']['item-redirect'] = true;
	$wgGrantPermissions['editpage']['item-merge'] = true;
	$wgGrantPermissions['editpage']['property-term'] = true;
	$wgGrantPermissions['createeditmovepage']['property-create'] = true;

	// i18n
	$wgMessagesDirs['Wikibase'] = __DIR__ . '/i18n';
	$wgExtensionMessagesFiles['WikibaseAlias'] = __DIR__ . '/Wikibase.i18n.alias.php';
	$wgExtensionMessagesFiles['WikibaseNS'] = __DIR__ . '/Wikibase.i18n.namespaces.php';

	/**
	 * @var callable[] $wgValueParsers Defines parser factory callbacks by parser name (not data type name).
	 * @deprecated use $wgWBRepoDataTypes instead.
	 */
	$wgValueParsers['wikibase-entityid'] = $wgWBRepoDataTypes['VT:wikibase-entityid']['parser-factory-callback'];
	$wgValueParsers['globecoordinate'] = $wgWBRepoDataTypes['VT:globecoordinate']['parser-factory-callback'];

	// 'null' is not a datatype. Kept for backwards compatibility.
	$wgValueParsers['null'] = function() {
		return new ValueParsers\NullParser();
	};

	// API module registration
	$wgAPIModules['wbgetentities'] = [
		'class' => Wikibase\Repo\Api\GetEntities::class,
		'factory' => function( ApiMain $apiMain, $moduleName ) {
			$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
			$settings = $wikibaseRepo->getSettings();
			$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $apiMain->getContext() );

			$siteLinkTargetProvider = new Wikibase\Repo\SiteLinkTargetProvider(
				$wikibaseRepo->getSiteLookup(),
				$settings->getSetting( 'specialSiteLinkGroups' )
			);

			return new Wikibase\Repo\Api\GetEntities(
				$apiMain,
				$moduleName,
				$wikibaseRepo->getStringNormalizer(),
				$wikibaseRepo->getLanguageFallbackChainFactory(),
				$siteLinkTargetProvider,
				$wikibaseRepo->getStore()->getEntityPrefetcher(),
				$settings->getSetting( 'siteLinkGroups' ),
				$apiHelperFactory->getErrorReporter( $apiMain ),
				$apiHelperFactory->getResultBuilder( $apiMain ),
				$wikibaseRepo->getEntityRevisionLookup(),
				$wikibaseRepo->getEntityIdParser()
			);
		}
	];
	$wgAPIModules['wbsetlabel'] = [
		'class' => Wikibase\Repo\Api\SetLabel::class,
		'factory' => function ( ApiMain $mainModule, $moduleName ) {
			return new Wikibase\Repo\Api\SetLabel(
				$mainModule,
				$moduleName,
				Wikibase\Repo\WikibaseRepo::getDefaultInstance()->getChangeOpFactoryProvider()
					->getFingerprintChangeOpFactory()
			);
		}
	];
	$wgAPIModules['wbsetdescription'] = [
		'class' => Wikibase\Repo\Api\SetDescription::class,
		'factory' => function ( ApiMain $mainModule, $moduleName ) {
			return new Wikibase\Repo\Api\SetDescription(
				$mainModule,
				$moduleName,
				Wikibase\Repo\WikibaseRepo::getDefaultInstance()->getChangeOpFactoryProvider()
					->getFingerprintChangeOpFactory()
			);
		}
	];
	$wgAPIModules['wbsearchentities'] = [
		'class' => Wikibase\Repo\Api\SearchEntities::class,
		'factory' => function( ApiMain $mainModule, $moduleName ) {
			$repo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
			$settings = $repo->getSettings()->getSetting( 'entitySearch' );
			if ( $settings['useCirrus'] ) {
				$entitySearchHelper = new Wikibase\Repo\Api\EntitySearchElastic(
					$repo->getLanguageFallbackChainFactory(),
					$repo->getEntityIdParser(),
					new Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup(
						$repo->getTermLookup(),
						$repo->getLanguageFallbackChainFactory()->newFromLanguage( $repo->getUserLanguage() )
					),
					$repo->getContentModelMappings(),
					$mainModule->getRequest(),
					$settings
				);
			} else {
				$entitySearchHelper = new Wikibase\Repo\Api\EntitySearchHelper(
					$repo->getEntityTitleLookup(),
					$repo->getEntityIdParser(),
					$repo->newTermSearchInteractor( $repo->getUserLanguage()->getCode() ),
					new Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup(
						$repo->getTermLookup(),
						$repo->getLanguageFallbackChainFactory()->newFromLanguage( $repo->getUserLanguage() )
					)
				);
			}

			return new Wikibase\Repo\Api\SearchEntities(
				$mainModule,
				$moduleName,
				$entitySearchHelper,
				$repo->getEntityTitleLookup(),
				$repo->getTermsLanguages(),
				$repo->getEnabledEntityTypes(),
				$repo->getSettings()->getSetting( 'conceptBaseUri' )
			);
		},
	];
	$wgAPIModules['wbsetaliases'] = [
		'class' => Wikibase\Repo\Api\SetAliases::class,
		'factory' => function ( ApiMain $mainModule, $moduleName ) {
			return new Wikibase\Repo\Api\SetAliases(
				$mainModule,
				$moduleName,
				Wikibase\Repo\WikibaseRepo::getDefaultInstance()->getChangeOpFactoryProvider()
					->getFingerprintChangeOpFactory()
			);
		}
	];
	$wgAPIModules['wbeditentity'] = Wikibase\Repo\Api\EditEntity::class;
	$wgAPIModules['wblinktitles'] = Wikibase\Repo\Api\LinkTitles::class;
	$wgAPIModules['wbsetsitelink'] = [
		'class' => Wikibase\Repo\Api\SetSiteLink::class,
		'factory' => function ( ApiMain $mainModule, $moduleName ) {
			return new Wikibase\Repo\Api\SetSiteLink(
				$mainModule,
				$moduleName,
				Wikibase\Repo\WikibaseRepo::getDefaultInstance()->getChangeOpFactoryProvider()
					->getSiteLinkChangeOpFactory()
			);
		}
	];
	$wgAPIModules['wbcreateclaim'] = Wikibase\Repo\Api\CreateClaim::class;
	$wgAPIModules['wbgetclaims'] = Wikibase\Repo\Api\GetClaims::class;
	$wgAPIModules['wbremoveclaims'] = Wikibase\Repo\Api\RemoveClaims::class;
	$wgAPIModules['wbsetclaimvalue'] = Wikibase\Repo\Api\SetClaimValue::class;
	$wgAPIModules['wbsetreference'] = Wikibase\Repo\Api\SetReference::class;
	$wgAPIModules['wbremovereferences'] = Wikibase\Repo\Api\RemoveReferences::class;
	$wgAPIModules['wbsetclaim'] = Wikibase\Repo\Api\SetClaim::class;
	$wgAPIModules['wbremovequalifiers'] = Wikibase\Repo\Api\RemoveQualifiers::class;
	$wgAPIModules['wbsetqualifier'] = [
		'class' => Wikibase\Repo\Api\SetQualifier::class,
		'factory' => function( ApiMain $mainModule, $moduleName ) {
			$wikibaseRepo = \Wikibase\Repo\WikibaseRepo::getDefaultInstance();
			$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $mainModule->getContext() );
			$changeOpFactoryProvider = $wikibaseRepo->getChangeOpFactoryProvider();

			$modificationHelper = new \Wikibase\Repo\Api\StatementModificationHelper(
				$wikibaseRepo->getSnakFactory(),
				$wikibaseRepo->getEntityIdParser(),
				$wikibaseRepo->getStatementGuidValidator(),
				$apiHelperFactory->getErrorReporter( $mainModule )
			);

			return new Wikibase\Repo\Api\SetQualifier(
				$mainModule,
				$moduleName,
				function ( $module ) use ( $apiHelperFactory ) {
					return $apiHelperFactory->getErrorReporter( $module );
				},
				$changeOpFactoryProvider->getStatementChangeOpFactory(),
				$modificationHelper,
				$wikibaseRepo->getStatementGuidParser(),
				function ( $module ) use ( $apiHelperFactory ) {
					return $apiHelperFactory->getResultBuilder( $module );
				},
				function ( $module ) use ( $apiHelperFactory ) {
					return $apiHelperFactory->getEntitySavingHelper( $module );
				}
			);
		}
	];
	$wgAPIModules['wbmergeitems'] = Wikibase\Repo\Api\MergeItems::class;
	$wgAPIModules['wbformatvalue'] = [
		'class' => Wikibase\Repo\Api\FormatSnakValue::class,
		'factory' => function( ApiMain $mainModule, $moduleName ) {
			$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
			$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $mainModule->getContext() );

			return new Wikibase\Repo\Api\FormatSnakValue(
				$mainModule,
				$moduleName,
				$wikibaseRepo->getValueFormatterFactory(),
				$wikibaseRepo->getSnakFormatterFactory(),
				$wikibaseRepo->getDataValueFactory(),
				$apiHelperFactory->getErrorReporter( $mainModule )
			);
		}
	];
	$wgAPIModules['wbparsevalue'] = [
		'class' => Wikibase\Repo\Api\ParseValue::class,
		'factory' => function( ApiMain $mainModule, $moduleName ) {
			$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
			$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $mainModule->getContext() );

			return new Wikibase\Repo\Api\ParseValue(
				$mainModule,
				$moduleName,
				$wikibaseRepo->getDataTypeFactory(),
				$wikibaseRepo->getValueParserFactory(),
				$wikibaseRepo->getDataTypeValidatorFactory(),
				$wikibaseRepo->getExceptionLocalizer(),
				$wikibaseRepo->getValidatorErrorLocalizer(),
				$apiHelperFactory->getErrorReporter( $mainModule )
			);
		}
	];
	$wgAPIModules['wbavailablebadges'] = Wikibase\Repo\Api\AvailableBadges::class;
	$wgAPIModules['wbcreateredirect'] = [
		'class' => Wikibase\Repo\Api\CreateRedirect::class,
		'factory' => function( ApiMain $apiMain, $moduleName ) {
			$wikibaseRepo = \Wikibase\Repo\WikibaseRepo::getDefaultInstance();
			$apiHelperFactory = $wikibaseRepo->getApiHelperFactory( $apiMain->getContext() );
			return new Wikibase\Repo\Api\CreateRedirect(
				$apiMain,
				$moduleName,
				$wikibaseRepo->getEntityIdParser(),
				$apiHelperFactory->getErrorReporter( $apiMain ),
				$wikibaseRepo->newRedirectCreationInteractor( $apiMain->getUser(), $apiMain->getContext() )
			);
		}
	];
	$wgAPIListModules['wbsearch'] = Wikibase\Repo\Api\QuerySearchEntities::class;
	$wgAPIListModules['wbsubscribers'] = [
		'class' => Wikibase\Repo\Api\ListSubscribers::class,
		'factory' => function( ApiQuery $apiQuery, $moduleName ) {
			$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
			$mediaWikiServices = MediaWiki\MediaWikiServices::getInstance();
			$apiHelper = $wikibaseRepo->getApiHelperFactory( $apiQuery->getContext() );
			return new Wikibase\Repo\Api\ListSubscribers(
				$apiQuery,
				$moduleName,
				$apiHelper->getErrorReporter( $apiQuery ),
				$wikibaseRepo->getEntityIdParser(),
				$mediaWikiServices->getSiteLookup()
			);
		}
	];

	// Special page registration
	$wgSpecialPages['NewItem'] = function () {
		$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();

		$settings = $wikibaseRepo->getSettings();
		$copyrightView = new Wikibase\Repo\Specials\SpecialPageCopyrightView(
			new Wikibase\CopyrightMessageBuilder(),
			$settings->getSetting( 'dataRightsUrl' ),
			$settings->getSetting( 'dataRightsText' )
		);

		return new Wikibase\Repo\Specials\SpecialNewItem(
			$wikibaseRepo->getSiteLookup(),
			$copyrightView
		);
	};
	$wgSpecialPages['NewProperty'] = function () {
		$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();

		$settings = $wikibaseRepo->getSettings();
		$copyrightView = new Wikibase\Repo\Specials\SpecialPageCopyrightView(
			new Wikibase\CopyrightMessageBuilder(),
			$settings->getSetting( 'dataRightsUrl' ),
			$settings->getSetting( 'dataRightsText' )
		);

		return new Wikibase\Repo\Specials\SpecialNewProperty(
			$copyrightView
		);
	};
	$wgSpecialPages['ItemByTitle'] = function () {
		$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();

		$siteLinkTargetProvider = new Wikibase\Repo\SiteLinkTargetProvider(
			$wikibaseRepo->getSiteLookup(),
			$wikibaseRepo->getSettings()->getSetting( 'specialSiteLinkGroups' )
		);

		return new Wikibase\Repo\Specials\SpecialItemByTitle(
			$wikibaseRepo->getEntityTitleLookup(),
			new Wikibase\Lib\LanguageNameLookup(),
			$wikibaseRepo->getSiteLookup(),
			$wikibaseRepo->getStore()->newSiteLinkStore(),
			$siteLinkTargetProvider,
			$wikibaseRepo->getSettings()->getSetting( 'siteLinkGroups' )
		);
	};
	$wgSpecialPages['GoToLinkedPage'] = function() {
		$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
		return new Wikibase\Repo\Specials\SpecialGoToLinkedPage(
			$wikibaseRepo->getSiteLookup(),
			$wikibaseRepo->getStore()->newSiteLinkStore(),
			$wikibaseRepo->getStore()->getEntityRedirectLookup(),
			$wikibaseRepo->getEntityIdParser(),
			$wikibaseRepo->getStore()->getEntityLookup()
		);
	};
	$wgSpecialPages['ItemDisambiguation'] = function() {
		global $wgLang;
		$languageCode = $wgLang->getCode();
		$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
		$languageNameLookup = new Wikibase\Lib\LanguageNameLookup( $languageCode );
		$itemDisambiguation = new Wikibase\ItemDisambiguation(
			$wikibaseRepo->getEntityTitleLookup(),
			$languageNameLookup,
			$languageCode
		);
		return new Wikibase\Repo\Specials\SpecialItemDisambiguation(
			new Wikibase\Lib\MediaWikiContentLanguages(),
			$languageNameLookup,
			$itemDisambiguation,
			$wikibaseRepo->newTermSearchInteractor( $languageCode )
		);
	};
	$wgSpecialPages['ItemsWithoutSitelinks']
		= Wikibase\Repo\Specials\SpecialItemsWithoutSitelinks::class;
	$wgSpecialPages['SetLabel'] = Wikibase\Repo\Specials\SpecialSetLabel::class;
	$wgSpecialPages['SetDescription'] = Wikibase\Repo\Specials\SpecialSetDescription::class;
	$wgSpecialPages['SetAliases'] = Wikibase\Repo\Specials\SpecialSetAliases::class;
	$wgSpecialPages['SetLabelDescriptionAliases']
		= Wikibase\Repo\Specials\SpecialSetLabelDescriptionAliases::class;
	$wgSpecialPages['SetSiteLink'] = function() {
		$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
		$siteLookup = $wikibaseRepo->getSiteLookup();
		$settings = $wikibaseRepo->getSettings();

		$siteLinkChangeOpFactory = $wikibaseRepo->getChangeOpFactoryProvider()->getSiteLinkChangeOpFactory();
		$siteLinkTargetProvider = new Wikibase\Repo\SiteLinkTargetProvider(
			$siteLookup,
			$settings->getSetting( 'specialSiteLinkGroups' )
		);

		$labelDescriptionLookupFactory = $wikibaseRepo->getLanguageFallbackLabelDescriptionLookupFactory();
		return new Wikibase\Repo\Specials\SpecialSetSiteLink(
			$siteLookup,
			$siteLinkTargetProvider,
			$settings->getSetting( 'siteLinkGroups' ),
			$settings->getSetting( 'badgeItems' ),
			$labelDescriptionLookupFactory,
			$siteLinkChangeOpFactory
		);
	};
	$wgSpecialPages['EntitiesWithoutLabel'] = array(
		Wikibase\Repo\Specials\SpecialEntitiesWithoutPageFactory::class,
		'newSpecialEntitiesWithoutLabel'
	);
	$wgSpecialPages['EntitiesWithoutDescription'] = array(
		Wikibase\Repo\Specials\SpecialEntitiesWithoutPageFactory::class,
		'newSpecialEntitiesWithoutDescription'
	);
	$wgSpecialPages['ListDatatypes'] = Wikibase\Repo\Specials\SpecialListDatatypes::class;
	$wgSpecialPages['ListProperties'] = function () {
		global $wgContLang;
		$wikibaseRepo = Wikibase\Repo\WikibaseRepo::getDefaultInstance();
		$prefetchingTermLookup = $wikibaseRepo->getPrefetchingTermLookup();
		$languageFallbackChainFactory = $wikibaseRepo->getLanguageFallbackChainFactory();
		$fallbackMode = Wikibase\LanguageFallbackChainFactory::FALLBACK_ALL;
		$labelDescriptionLookup = new Wikibase\Lib\Store\LanguageFallbackLabelDescriptionLookup(
			$prefetchingTermLookup,
			$languageFallbackChainFactory->newFromLanguage( $wgContLang, $fallbackMode )
		);
		$entityIdFormatter = $wikibaseRepo->getEntityIdHtmlLinkFormatterFactory()
			->getEntityIdFormatter( $labelDescriptionLookup );
		return new Wikibase\Repo\Specials\SpecialListProperties(
			$wikibaseRepo->getDataTypeFactory(),
			$wikibaseRepo->getStore()->getPropertyInfoLookup(),
			$labelDescriptionLookup,
			$entityIdFormatter,
			$wikibaseRepo->getEntityTitleLookup(),
			$prefetchingTermLookup
		);
	};
	$wgSpecialPages['DispatchStats'] = Wikibase\Repo\Specials\SpecialDispatchStats::class;
	$wgSpecialPages['EntityData'] = Wikibase\Repo\Specials\SpecialEntityData::class;
	$wgSpecialPages['EntityPage'] = function() {
		return new Wikibase\Repo\Specials\SpecialEntityPage(
			Wikibase\Repo\WikibaseRepo::getDefaultInstance()->getEntityIdParser(),
			Wikibase\Repo\WikibaseRepo::getDefaultInstance()->getEntityContentFactory()
		);
	};
	$wgSpecialPages['MyLanguageFallbackChain'] = function() {
		return new Wikibase\Repo\Specials\SpecialMyLanguageFallbackChain(
			\Wikibase\Repo\WikibaseRepo::getDefaultInstance()->getLanguageFallbackChainFactory()
		);
	};
	$wgSpecialPages['MergeItems'] = Wikibase\Repo\Specials\SpecialMergeItems::class;
	$wgSpecialPages['RedirectEntity'] = Wikibase\Repo\Specials\SpecialRedirectEntity::class;

	// Jobs
	$wgJobClasses['UpdateRepoOnMove'] = Wikibase\Repo\UpdateRepo\UpdateRepoOnMoveJob::class;
	$wgJobClasses['UpdateRepoOnDelete'] = Wikibase\Repo\UpdateRepo\UpdateRepoOnDeleteJob::class;

	// Hooks
	$wgHooks['BeforePageDisplay'][] = 'Wikibase\RepoHooks::onBeforePageDisplay';
	$wgHooks['LoadExtensionSchemaUpdates'][] = 'Wikibase\Repo\Store\Sql\DatabaseSchemaUpdater::onSchemaUpdate';
	$wgHooks['UnitTestsList'][] = 'Wikibase\RepoHooks::registerUnitTests';
	$wgHooks['ResourceLoaderTestModules'][] = 'Wikibase\RepoHooks::registerQUnitTests';

	$wgHooks['NamespaceIsMovable'][] = 'Wikibase\RepoHooks::onNamespaceIsMovable';
	$wgHooks['NewRevisionFromEditComplete'][] = 'Wikibase\RepoHooks::onNewRevisionFromEditComplete';
	$wgHooks['SkinTemplateNavigation'][] = 'Wikibase\RepoHooks::onPageTabs';
	$wgHooks['RecentChange_save'][] = 'Wikibase\RepoHooks::onRecentChangeSave';
	$wgHooks['ArticleDeleteComplete'][] = 'Wikibase\RepoHooks::onArticleDeleteComplete';
	$wgHooks['ArticleUndelete'][] = 'Wikibase\RepoHooks::onArticleUndelete';
	$wgHooks['GetPreferences'][] = 'Wikibase\RepoHooks::onGetPreferences';
	$wgHooks['LinkBegin'][] = 'Wikibase\Repo\Hooks\LinkBeginHookHandler::onLinkBegin';
	$wgHooks['ChangesListInitRows'][] = 'Wikibase\Repo\Hooks\LabelPrefetchHookHandlers::onChangesListInitRows';
	$wgHooks['OutputPageBodyAttributes'][] = 'Wikibase\RepoHooks::onOutputPageBodyAttributes';
	$wgHooks['FormatAutocomments'][] = 'Wikibase\RepoHooks::onFormat';
	$wgHooks['PageHistoryLineEnding'][] = 'Wikibase\RepoHooks::onPageHistoryLineEnding';
	$wgHooks['ApiCheckCanExecute'][] = 'Wikibase\RepoHooks::onApiCheckCanExecute';
	$wgHooks['SetupAfterCache'][] = 'Wikibase\RepoHooks::onSetupAfterCache';
	$wgHooks['ShowSearchHit'][] = 'Wikibase\RepoHooks::onShowSearchHit';
	$wgHooks['ShowSearchHitTitle'][] = 'Wikibase\RepoHooks::onShowSearchHitTitle';
	$wgHooks['TitleGetRestrictionTypes'][] = 'Wikibase\RepoHooks::onTitleGetRestrictionTypes';
	$wgHooks['TitleQuickPermissions'][] = 'Wikibase\RepoHooks::onTitleQuickPermissions';
	$wgHooks['AbuseFilter-contentToString'][] = 'Wikibase\RepoHooks::onAbuseFilterContentToString';
	$wgHooks['SpecialPage_reorderPages'][] = 'Wikibase\RepoHooks::onSpecialPageReorderPages';
	$wgHooks['OutputPageParserOutput'][] = 'Wikibase\RepoHooks::onOutputPageParserOutput';
	$wgHooks['ContentModelCanBeUsedOn'][] = 'Wikibase\RepoHooks::onContentModelCanBeUsedOn';
	$wgHooks['OutputPageBeforeHTML'][] = 'Wikibase\Repo\Hooks\OutputPageBeforeHTMLHookHandler::onOutputPageBeforeHTML';
	$wgHooks['OutputPageBeforeHTML'][] = 'Wikibase\Repo\Hooks\OutputPageJsConfigHookHandler::onOutputPageBeforeHtmlRegisterConfig';
	$wgHooks['APIQuerySiteInfoGeneralInfo'][] = 'Wikibase\RepoHooks::onAPIQuerySiteInfoGeneralInfo';
	$wgHooks['APIQuerySiteInfoStatisticsInfo'][] = 'Wikibase\RepoHooks::onAPIQuerySiteInfoStatisticsInfo';
	$wgHooks['ImportHandleRevisionXMLTag'][] = 'Wikibase\RepoHooks::onImportHandleRevisionXMLTag';
	$wgHooks['BaseTemplateToolbox'][] = 'Wikibase\RepoHooks::onBaseTemplateToolbox';
	$wgHooks['SkinTemplateBuildNavUrlsNav_urlsAfterPermalink'][] = 'Wikibase\RepoHooks::onSkinTemplateBuildNavUrlsNavUrlsAfterPermalink';
	$wgHooks['SkinMinervaDefaultModules'][] = 'Wikibase\RepoHooks::onSkinMinervaDefaultModules';
	$wgHooks['ResourceLoaderRegisterModules'][] = 'Wikibase\RepoHooks::onResourceLoaderRegisterModules';
	$wgHooks['ContentHandlerForModelID'][] = 'Wikibase\RepoHooks::onContentHandlerForModelID';
	$wgHooks['BeforeDisplayNoArticleText'][] = 'Wikibase\ViewEntityAction::onBeforeDisplayNoArticleText';
	$wgHooks['InfoAction'][] = '\Wikibase\RepoHooks::onInfoAction';
	$wgHooks['GetContentModels'][] = '\Wikibase\RepoHooks::onGetContentModels';

	// update hooks
	$wgHooks['LoadExtensionSchemaUpdates'][] = '\Wikibase\Repo\Store\Sql\ChangesSubscriptionSchemaUpdater::onSchemaUpdate';

	// Resource Loader Modules:
	$wgResourceModules = array_merge(
		$wgResourceModules,
		include __DIR__ . '/resources/Resources.php'
	);

	$wgWBRepoSettings = array_merge(
		require __DIR__ . '/../lib/config/WikibaseLib.default.php',
		require __DIR__ . '/config/Wikibase.default.php'
	);

	// Field wieight profiles. These profiles specify relative weights
	// of label fields for different languages, e.g. exact language match
	// vs. fallback language match.
	$wgEntityPrefixSearchProfiles = require __DIR__ . '/config/EntityPrefixSearchProfiles.php';
	// Wikibase prefix search scoring profile for CirrusSearch.
	// This profile applies to the whole document.
	// These configurations define how the results are ordered. If we have a lot of them,
	// we may want to move it to a separate file.
	// The names should be distinct from other Cirrus rescoring profile, so
	// prefixing with 'wikibase' is recommended.
	$wgCirrusSearchRescoreProfiles['wikibase_prefix'] = [
		'i18n_msg' => 'wikibase-rescore-profile-prefix',
		'supported_namespaces' => 'all',
		'rescore' => [
			[
				'window' => 8192,
				'window_size_override' => 'EntitySearchRescoreWindowSize',
				'query_weight' => 1.0,
				'rescore_query_weight' => 1.0,
				'score_mode' => 'multiply',
				'type' => 'function_score',
				'function_chain' => 'entity_weight'
			],
		]
	];
	// ElasticSearch function for entity weight
	$wgCirrusSearchRescoreFunctionScoreChains['entity_weight'] = [
		'score_mode' => 'max',
		'functions' => [
			[
				'type' => 'custom_field',
				'params' => [ 'field' => 'label_count', 'missing' => 0 ]
			],
			[
				'type' => 'custom_field',
				'params' => [ 'field' => 'sitelink_count', 'missing' => 0 ]
			],
		],
	];
} );
