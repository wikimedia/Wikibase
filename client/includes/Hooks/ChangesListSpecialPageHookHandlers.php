<?php

namespace Wikibase\Client\Hooks;

use ChangesListBooleanFilter;
use ChangesListSpecialPage;
use ExtensionRegistry;
use IContextSource;
use MediaWiki\MediaWikiServices;
use User;
use WebRequest;
use Wikibase\Client\RecentChanges\RecentChangeFactory;
use Wikibase\Client\WikibaseClient;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\IDatabase;
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
	 * @var self
	 */
	private static $instance = null;

	/**
	 * @param WebRequest $request
	 * @param User $user
	 * @param LoadBalancer $loadBalancer
	 * @param string $pageName
	 */
	public function __construct(
		WebRequest $request,
		User $user,
		LoadBalancer $loadBalancer,
		$pageName
	) {
		$this->request = $request;
		$this->user = $user;
		$this->loadBalancer = $loadBalancer;
		$this->pageName = $pageName;
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

		return new self(
			$context->getRequest(),
			$context->getUser(),
			MediaWikiServices::getInstance()->getDBLoadBalancer(),
			$specialPageName
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

		$hookHandler->addFilter( $specialPage );

		return true;
	}

	public function addFilter( ChangesListSpecialPage $specialPage ) {
		$filterName = $this->getFilterName();
		$changeTypeGroup = $specialPage->getFilterGroup( 'changeType' );

		$specialPage->getOutput()->addModules( 'wikibase.client.jqueryMsg' );
		$specialPage->getOutput()->addModuleStyles( 'wikibase.client.changeslist.css' );

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
				return RecentChangeFactory::isWikibaseChange( $rc );
			}
		] );

		$significanceGroup = $specialPage->getFilterGroup( 'significance' );
		$hideMajorFilter = $significanceGroup->getFilter( 'hidemajor' );
		$hideMajorFilter->conflictsWith(
			$wikidataFilter,
			'wikibase-rcfilters-hide-wikibase-conflicts-major-global',
			'wikibase-rcfilters-major-conflicts-hide-wikibase',
			'wikibase-rcfilters-hide-wikibase-conflicts-major'
		);

		$extensionRegistry = ExtensionRegistry::getInstance();
		if ( $extensionRegistry->isLoaded( 'ORES' ) ) {
			$damagingGroup = $specialPage->getFilterGroup( 'damaging' );
			if ( $damagingGroup ) {
				$wikidataFilter->conflictsWith(
					$damagingGroup,
					'wikibase-rcfilters-hide-wikibase-conflicts-ores-global',
					'wikibase-rcfilters-hide-wikibase-conflicts-ores',
					'wikibase-rcfilters-damaging-conflicts-hide-wikibase'
				);
			}

			$goodfaithGroup = $specialPage->getFilterGroup( 'goodfaith' );
			if ( $goodfaithGroup ) {
				$wikidataFilter->conflictsWith(
					$goodfaithGroup,
					'wikibase-rcfilters-hide-wikibase-conflicts-ores-global',
					'wikibase-rcfilters-hide-wikibase-conflicts-ores',
					'wikibase-rcfilters-goodfaith-conflicts-hide-wikibase'
				);
			}
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
	private function hasShowWikibaseEditsPrefEnabled() {
		return (bool)$this->user->getOption( $this->getOptionName() );
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
