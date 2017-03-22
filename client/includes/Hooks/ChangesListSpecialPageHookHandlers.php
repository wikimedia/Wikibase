<?php

namespace Wikibase\Client\Hooks;

use ChangesListBooleanFilter;
use ChangesListSpecialPage;
use ExtensionRegistry;
use FormOptions;
use IContextSource;
use IDatabase;
use MediaWiki\MediaWikiServices;
use RequestContext;
use User;
use WebRequest;
use Wikibase\Client\RecentChanges\RecentChangeFactory;
use Wikibase\Client\WikibaseClient;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\LoadBalancer;

/**
 * @license GPL-2.0+
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class ChangesListSpecialPageHookHandlers {

	/**
	 * @var WebRequest
	 */
	private $request;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @var LoadBalancer
	 */
	private $loadBalancer;

	/**
	 * @var string
	 */
	private $pageName;

	/**
	 * @var bool
	 */
	private $showExternalChanges;

	/**
	 * @var self
	 */
	private static $instance = null;

	/**
	 * @param WebRequest $request
	 * @param User $user
	 * @param LoadBalancer $loadBalancer
	 * @param string $pageName
	 * @param bool $showExternalChanges
	 */
	public function __construct(
		WebRequest $request,
		User $user,
		LoadBalancer $loadBalancer,
		$pageName,
		$showExternalChanges
	) {
		$this->request = $request;
		$this->user = $user;
		$this->loadBalancer = $loadBalancer;
		$this->pageName = $pageName;
		$this->showExternalChanges = $showExternalChanges;
	}

	/**
	 * @param IContextSource $context
	 * @param string $specialPageName
	 *
	 * @return self
	 */
	private static function newFromGlobalState(
		IContextSource $context,
		$specialPageName
	) {
		Assert::parameterType( 'string', $specialPageName, '$specialPageName' );

		$settings = WikibaseClient::getDefaultInstance()->getSettings();

		return new self(
			$context->getRequest(),
			$context->getUser(),
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			$specialPageName,
			$settings->getSetting( 'showExternalRecentChanges' )
		);
	}

	/**
	 * @param IContextSource $context
	 * @param string $specialPageName
	 *
	 * @return self
	 */
	private static function getInstance(
		IContextSource $context,
		$specialPageName
	) {
		if ( self::$instance === null ) {
			self::$instance = self::newFromGlobalState( $context, $specialPageName );
		}

		return self::$instance;
	}

	/**
	 * Modifies recent changes and watchlist options to show a toggle for Wikibase changes
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangesListSpecialPageStructuredFilters
	 *
	 * @param ChangesListSpecialPage $specialPage
	 *
	 * @return bool
	 */
	public static function onChangesListSpecialPageStructuredFilters(
		ChangesListSpecialPage $specialPage
	) {
		$hookHandler = self::getInstance(
			$specialPage->getContext(),
			$specialPage->getName()
		);

		$hookHandler->addFilterIfEnabled( $specialPage );

		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ChangesListSpecialPageQuery
	 *
	 * @param string $specialPageName
	 * @param array &$tables
	 * @param array &$fields
	 * @param array &$conds
	 * @param array &$query_options
	 * @param array &$join_conds
	 * @param FormOptions $opts
	 *
	 * @return bool
	 */
	public static function onChangesListSpecialPageQuery(
		$specialPageName,
		array &$tables,
		array &$fields,
		array &$conds,
		array &$query_options,
		array &$join_conds,
		FormOptions $opts
	) {
		$hookHandler = self::getInstance(
			RequestContext::getMain(),
			$specialPageName
		);

		$hookHandler->addWikibaseConditionsIfFilterUnavailable( $conds );

		return true;
	}

	// This is separate so hasWikibaseChangesEnabled can be mocked

	/**
	 * This is used to force-hide Wikibase changes if hasWikibaseChangesEnabled returns
	 * false.  The user will not even see the option in that case.
	 *
	 * @param array &$conds
	 */
	protected function addWikibaseConditionsIfFilterUnavailable( array &$conds ) {
		if ( !$this->hasWikibaseChangesEnabled() ) {
			// Force-hide if hasWikibaseChangesEnabled is false
			// The user-facing hideWikibase is handled by
			// ChangesListSpecialPageStructuredFilters and connected code.
			$this->addWikibaseConditions(
				$this->loadBalancer->getConnection( DB_REPLICA ),
				$conds
			);
		}
	}

	/**
	 * @param ChangesListSpecialPage $specialPage
	 */
	public function addFilterIfEnabled( ChangesListSpecialPage $specialPage ) {
		// The *user-facing* filter is only registered if external changes
		// are enabled, and the user does not have enhanced recent changes.
		//
		// If the user-facing filter is not registered, it's always *hidden*.
		// (See ChangesListSpecialPageQuery).
		if ( $this->hasWikibaseChangesEnabled() ) {
			$this->addFilter( $specialPage );
		}
	}

	/**
	 * @param ChangesListSpecialPage $specialPage
	 */
	protected function addFilter( ChangesListSpecialPage $specialPage ) {
		$filterName = $this->getFilterName();
		$changeTypeGroup = $specialPage->getFilterGroup( 'changeType' );

		$specialPage->getOutput()->addModules( 'wikibase.client.jqueryMsg' );

		$wikidataFilter = new ChangesListBooleanFilter( [
			'name' => $filterName,
			'group' => $changeTypeGroup,
			'priority' => -4,
			'label' => 'wikibase-rcfilters-hide-wikibase-label',
			'description' => 'wikibase-rcfilters-hide-wikibase-description',
			'showHide' => 'wikibase-rc-hide-wikidata',
			// If the preference is enabled, then don't hide Wikidata edits
			'default' => !$this->hasShowWikibaseEditsPrefEnabled(),
			'queryCallable' => function ( $specialClassName, $ctx, $dbr, &$tables, &$fields,
				&$conds, &$query_options, &$join_conds ) {
				$this->addWikibaseConditions( $dbr, $conds );
			},
			'cssClassSuffix' => 'src-mw-wikibase',
			'isRowApplicableCallable' => function ( $ctx, $rc ) {
				return $rc->getAttribute( 'rc_source' ) === RecentChangeFactory::SRC_WIKIBASE;
			}
		] );

		$extensionRegistry = ExtensionRegistry::getInstance();

		if ( $extensionRegistry->isLoaded( 'ORES' ) ) {
			$damagingGroup = $specialPage->getFilterGroup( 'damaging' );
			$wikidataFilter->conflictsWith(
				$damagingGroup,
				'wikibase-rcfilters-hide-wikibase-conflicts-ores-global',
				'wikibase-rcfilters-hide-wikibase-conflicts-ores',
				'wikibase-rcfilters-damaging-conflicts-hide-wikibase'
			);

			$goodfaithGroup = $specialPage->getFilterGroup( 'goodfaith' );
			$wikidataFilter->conflictsWith(
				$goodfaithGroup,
				'wikibase-rcfilters-hide-wikibase-conflicts-ores-global',
				'wikibase-rcfilters-hide-wikibase-conflicts-ores',
				'wikibase-rcfilters-goodfaith-conflicts-hide-wikibase'
			);
		}
	}

	/**
	 * @param IDatabase $dbr
	 * @param array &$conds
	 */
	public function addWikibaseConditions( IDatabase $dbr, array &$conds ) {
		$conds[] = 'rc_source != ' . $dbr->addQuotes( RecentChangeFactory::SRC_WIKIBASE );
	}

	/**
	 * @return bool
	 */
	protected function hasWikibaseChangesEnabled() {
		// do not include wikibase changes for activated enhanced watchlist
		// since we do not support that format yet (T46222)
		return $this->showExternalChanges && !$this->isEnhancedChangesEnabled();
	}

	/**
	 * @return bool
	 */
	private function hasShowWikibaseEditsPrefEnabled() {
		return (bool)$this->user->getOption( $this->getOptionName() );
	}

	/**
	 * @return bool
	 */
	private function isEnhancedChangesEnabled() {
		$enhancedChangesUserOption = $this->user->getOption( 'usenewrc' );

		return $this->request->getBool( 'enhanced', $enhancedChangesUserOption );
	}

	/**
	 * @return string
	 */
	private function getFilterName() {
		return 'hideWikibase';
	}

	/**
	 * @return string
	 */
	private function getOptionName() {
		if ( $this->pageName === 'Watchlist' ) {
			return 'wlshowwikibase';
		}

		return 'rcshowwikidata';
	}

}
