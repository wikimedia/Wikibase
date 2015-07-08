<?php

namespace Wikibase;

use ApiBase;
use ApiEditPage;
use BaseTemplate;
use Content;
use ContentHandler;
use DatabaseUpdater;
use ExtensionRegistry;
use HistoryPager;
use Html;
use Linker;
use LogEntryBase;
use MWException;
use OutputPage;
use ParserOutput;
use RecentChange;
use ResourceLoader;
use Revision;
use SearchResult;
use Skin;
use SkinTemplate;
use SpecialSearch;
use Title;
use User;
use Wikibase\Repo\Content\EntityHandler;
use Wikibase\Repo\Hooks\OutputPageEntityIdReader;
use Wikibase\Repo\Hooks\OutputPageJsConfigHookHandler;
use Wikibase\Repo\WikibaseRepo;
use WikiPage;

/**
 * File defining the hook handlers for the Wikibase extension.
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 * @author Nikola Smolenski
 * @author Daniel Werner
 * @author Michał Łazowik
 * @author Jens Ohlig
 */
final class RepoHooks {

	/**
	 * Handler for the BeforePageDisplay hook, simply injects wikibase.ui.entitysearch module
	 * replacing the native search box with the entity selector widget.
	 *
	 * @since 0.4
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 *
	 * @return bool
	 */
	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		$out->addModules( 'wikibase.ui.entitysearch' );
		return true;
	}

	/**
	 * Handler for the SetupAfterCache hook, completing setup of
	 * content and namespace setup.
	 *
	 * @since 0.1
	 *
	 * @note: $wgExtraNamespaces and $wgNamespaceAliases have already been processed at this point
	 *        and should no longer be touched.
	 *
	 * @throws MWException
	 * @return bool
	 */
	public static function onSetupAfterCache() {
		global $wgNamespaceContentModels;

		$namespaces = WikibaseRepo::getDefaultInstance()->
			getSettings()->getSetting( 'entityNamespaces' );

		if ( empty( $namespaces ) ) {
			throw new MWException( 'Wikibase: Incomplete configuration: '
				. '$wgWBRepoSettings["entityNamespaces"] has to be set to an '
				. 'array mapping content model IDs to namespace IDs. '
				. 'See ExampleSettings.php for details and examples.');
		}

		foreach ( $namespaces as $contentModel => $namespace ) {
			if ( !isset( $wgNamespaceContentModels[$namespace] ) ) {
				$wgNamespaceContentModels[$namespace] = $contentModel;
			}
		}

		return true;
	}

	/**
	 * Schema update to set up the needed database tables.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/LoadExtensionSchemaUpdates
	 *
	 * @since 0.1
	 *
	 * @param DatabaseUpdater $updater
	 *
	 * @return bool
	 */
	public static function onSchemaUpdate( DatabaseUpdater $updater ) {
		$type = $updater->getDB()->getType();

		if ( $type === 'mysql' || $type === 'sqlite' /* || $type === 'postgres' */ ) {
			$extension = $type === 'postgres' ? '.pg.sql' : '.sql';

			$updater->addExtensionTable(
				'wb_changes',
				__DIR__ . '/sql/changes' . $extension
			);

			if ( $type === 'mysql' && !$updater->updateRowExists( 'ChangeChangeObjectId.sql' ) ) {
				$updater->addExtensionUpdate( array(
					'applyPatch',
					__DIR__ . '/sql/ChangeChangeObjectId.sql',
					true
				) );

				$updater->insertUpdateRow( 'ChangeChangeObjectId.sql' );
			}

			$updater->addExtensionTable(
				'wb_changes_dispatch',
				__DIR__ . '/sql/changes_dispatch' . $extension
			);
		}
		else {
			wfWarn( "Database type '$type' is not supported by the Wikibase repository." );
		}

		/** @var SqlStore $store */
		$store = WikibaseRepo::getDefaultInstance()->getStore();
		$store->doSchemaUpdate( $updater );

		return true;
	}

	/**
	 * Hook to add PHPUnit test cases.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UnitTestsList
	 *
	 * @since 0.1
	 *
	 * @param string[] &$paths
	 *
	 * @return bool
	 */
	public static function registerUnitTests( array &$paths ) {
		$paths[] = __DIR__ . '/tests/phpunit/';

		return true;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderTestModules
	 *
	 * @since 0.2 (in repo as RepoHooks::onResourceLoaderTestModules in 0.1)
	 *
	 * @param array &$testModules
	 * @param ResourceLoader &$resourceLoader
	 *
	 * @return boolean
	 */
	public static function registerQUnitTests( array &$testModules, ResourceLoader &$resourceLoader ) {
		$testModules['qunit'] = array_merge(
			$testModules['qunit'],
			include( __DIR__ . '/tests/qunit/resources.php' )
		);

		return true;
	}

	/**
	 * Handler for the NamespaceIsMovable hook.
	 *
	 * Implemented to prevent moving pages that are in an entity namespace.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/NamespaceIsMovable
	 *
	 * @since 0.1
	 *
	 * @param int $ns Namespace ID
	 * @param bool $movable
	 *
	 * @return bool
	 */
	public static function onNamespaceIsMovable( $ns, &$movable ) {
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		if ( $entityNamespaceLookup->isEntityNamespace( $ns ) ) {
			$movable = false;
		}

		return true;
	}

	/**
	 * Called when a revision was inserted due to an edit.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/NewRevisionFromEditComplete
	 *
	 * @since 0.1
	 *
	 * @param WikiPage $article A WikiPage object as of MediaWiki 1.19, an Article one before.
	 * @param Revision $revision
	 * @param int $baseID
	 * @param User $user
	 *
	 * @return bool
	 */
	public static function onNewRevisionFromEditComplete( $article, Revision $revision, $baseID, User $user ) {
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		if ( $entityContentFactory->isEntityContentModel( $article->getContent()->getModel() ) ) {
			self::notifyEntityStoreWatcherOnUpdate( $revision );

			$notifier = WikibaseRepo::getDefaultInstance()->getChangeNotifier();

			if ( $revision->getParentId() === null ) {
				$notifier->notifyOnPageCreated( $revision );
			} else {
				$parent = Revision::newFromId( $revision->getParentId() );
				$notifier->notifyOnPageModified( $revision, $parent );
			}
		}

		return true;
	}

	/**
	 * @param Revision $revision
	 */
	private static function notifyEntityStoreWatcherOnUpdate( Revision $revision ) {
		/** @var EntityContent $content */
		$content = $revision->getContent();
		$watcher = WikibaseRepo::getDefaultInstance()->getEntityStoreWatcher();

		// Notify storage/lookup services that the entity was updated. Needed to track page-level changes.
		// May be redundant in some cases. Take care not to cause infinite regress.
		if ( $content->isRedirect() ) {
			$watcher->redirectUpdated(
				$content->getEntityRedirect(),
				$revision->getId()
			);
		} else {
			$watcher->entityUpdated( new EntityRevision(
				$content->getEntity(),
				$revision->getId(),
				$revision->getTimestamp()
			) );
		}
	}

	/**
	 * Occurs after the delete article request has been processed.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ArticleDeleteComplete
	 *
	 * @since 0.1
	 *
	 * @param WikiPage $wikiPage
	 * @param User $user
	 * @param string $reason
	 * @param int $id
	 * @param Content $content
	 * @param LogEntryBase $logEntry
	 *
	 * @throws MWException
	 *
	 * @return bool
	 */
	public static function onArticleDeleteComplete( WikiPage $wikiPage, User $user, $reason, $id,
		Content $content = null, LogEntryBase $logEntry = null
	) {
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		// Bail out if we are not looking at an entity
		if ( !$content || !$entityContentFactory->isEntityContentModel( $content->getModel() ) ) {
			return true;
		}

		/** @var EntityContent $content */

		// Notify storage/lookup services that the entity was deleted. Needed to track page-level deletion.
		// May be redundant in some cases. Take care not to cause infinite regress.
		WikibaseRepo::getDefaultInstance()->getEntityStoreWatcher()->entityDeleted( $content->getEntityId() );

		$notifier = WikibaseRepo::getDefaultInstance()->getChangeNotifier();
		$notifier->notifyOnPageDeleted( $content, $user, $logEntry->getTimestamp() );

		return true;
	}

	/**
	 * Handle changes for undeletions
	 *
	 * @since 0.2
	 *
	 * @param Title $title
	 * @param bool $created
	 * @param string $comment
	 *
	 * @return bool
	 */
	public static function onArticleUndelete( Title $title, $created, $comment ) {
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		// Bail out if we are not looking at an entity
		if ( !$entityContentFactory->isEntityContentModel( $title->getContentModel() ) ) {
			return true;
		}

		$revisionId = $title->getLatestRevID();
		$revision = Revision::newFromId( $revisionId );
		$content = $revision ? $revision->getContent() : null;

		if ( !( $content instanceof EntityContent ) ) {
			return true;
		}

		//XXX: EntityContent::save() also does this. Why are we doing this twice?
		WikibaseRepo::getDefaultInstance()->getStore()->newEntityPerPage()->addEntityPage(
			$content->getEntityId(),
			$title->getArticleID()
		);

		$notifier = WikibaseRepo::getDefaultInstance()->getChangeNotifier();
		$notifier->notifyOnPageUndeleted( $revision );

		return true;
	}

	/**
	 * Nasty hack to inject information from RC into the change notification saved earlier
	 * by the onNewRevisionFromEditComplete hook handler.
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/RecentChange_save
	 *
	 * @todo: find a better way to do this!
	 *
	 * @param $recentChange RecentChange
	 * @return bool
	 */
	public static function onRecentChangeSave( RecentChange $recentChange ) {
		$logType = $recentChange->getAttribute( 'rc_log_type' );
		$logAction = $recentChange->getAttribute( 'rc_log_action' );
		$revId = $recentChange->getAttribute( 'rc_this_oldid' );

		if ( $revId <= 0 ) {
			// If we don't have a revision ID, we have no chance to find the right change to update.
			// NOTE: As of February 2015, RC entries for undeletion have rc_this_oldid = 0.
			return true;
		}

		if ( $logType === null || ( $logType === 'delete' && $logAction === 'restore' ) ) {
			$changesTable = ChangesTable::singleton();

			$slave = $changesTable->getReadDb();
			$changesTable->setReadDb( DB_MASTER );

			/** @var EntityChange $change */
			$change = $changesTable->selectRow(
				null,
				array( 'revision_id' => $revId )
			);

			$changesTable->setReadDb( $slave );

			if ( $change ) {
				$change->setMetadataFromRC( $recentChange );
				$change->save();
			}
		}

		return true;
	}

	/**
	 * Allows to add user preferences.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/GetPreferences
	 *
	 * NOTE: Might make sense to put the inner functionality into a well structured Preferences file once this
	 *       becomes more.
	 *
	 * @since 0.1
	 *
	 * @param User $user
	 * @param array &$preferences
	 *
	 * @return bool
	 */
	public static function onGetPreferences( User $user, array &$preferences ) {
		$preferences['wb-acknowledgedcopyrightversion'] = array(
			'type' => 'api'
		);

		if ( class_exists( 'Babel' ) ) {
			$preferences['wikibase-entitytermsview-showEntitytermslistview'] = array(
				'type' => 'toggle',
				'label-message' => 'wikibase-setting-entitytermsview-showEntitytermslistview',
				'help-message' => 'wikibase-setting-entitytermsview-showEntitytermslistview-help',
				'section' => 'rendering/advancedrendering',
			);
		} elseif ( $user->getBoolOption( 'wikibase-entitytermsview-showEntitytermslistview' ) ) {
			// Clear setting after uninstalling Babel extension.
			unset( $user->mOptions['wikibase-entitytermsview-showEntitytermslistview'] );
			$user->saveSettings();
		}

		return true;
	}

	/**
	 * Called after fetching the core default user options.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/UserGetDefaultOptions
	 *
	 * @param array &$defaultOptions
	 *
	 * @return bool
	 */
	public static function onUserGetDefaultOptions( array &$defaultOptions ) {
		// pre-select default language in the list of fallback languages
		$defaultLang = $defaultOptions['language'];
		$defaultOptions[ 'wb-languages-' . $defaultLang ] = 1;

		return true;
	}

	/**
	 * Modify line endings on history page.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageHistoryLineEnding
	 *
	 * @since 0.1
	 *
	 * @param HistoryPager $history
	 * @param object &$row
	 * @param string &$s
	 * @param array &$classes
	 *
	 * @return bool
	 */
	public static function onPageHistoryLineEnding( HistoryPager $history, &$row, &$s, array &$classes  ) {
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		$article = $history->getArticle();
		$rev = new Revision( $row );

		if ( $entityContentFactory->isEntityContentModel( $history->getTitle()->getContentModel() )
			&& $article->getPage()->getLatest() !== $rev->getID()
			&& $rev->getTitle()->quickUserCan( 'edit', $history->getUser() )
		) {
			$link = Linker::linkKnown(
				$rev->getTitle(),
				$history->msg( 'wikibase-restoreold' )->escaped(),
				array(),
				array(
					'action'	=> 'edit',
					'restore'	=> $rev->getId()
				)
			);

			$s .= " " . $history->msg( 'parentheses' )->rawParams( $link )->escaped();
		}

		return true;
	}

	/**
	 * Alter the structured navigation links in SkinTemplates.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/SkinTemplateNavigation
	 *
	 * @since 0.1
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array $links
	 *
	 * @return bool
	 */
	public static function onPageTabs( SkinTemplate &$skinTemplate, array &$links ) {
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		$title = $skinTemplate->getTitle();
		$request = $skinTemplate->getRequest();

		if ( $entityContentFactory->isEntityContentModel( $title->getContentModel() ) ) {
			unset( $links['views']['edit'] );
			unset( $links['views']['viewsource'] );

			if ( $title->quickUserCan( 'edit', $skinTemplate->getUser() ) ) {
				$old = !$skinTemplate->isRevisionCurrent()
					&& !$request->getCheck( 'diff' );

				$restore = $request->getCheck( 'restore' );

				if ( $old || $restore ) {
					// insert restore tab into views array, at the second position

					$revid = $restore ? $request->getText( 'restore' ) : $skinTemplate->getRevisionId();

					$head = array_slice( $links['views'], 0, 1 );
					$tail = array_slice( $links['views'], 1 );
					$neck['restore'] = array(
						'class' => $restore ? 'selected' : false,
						'text' => $skinTemplate->getLanguage()->ucfirst(
							wfMessage( 'wikibase-restoreold' )->text()
						),
						'href' => $title->getLocalURL( array(
							'action' => 'edit',
							'restore' => $revid
						) ),
					);

					$links['views'] = array_merge( $head, $neck, $tail );
				}
			}
		}

		return true;
	}

	/**
	 * Reorder the groups for the special pages
	 *
	 * @since 0.4
	 *
	 * @param array &$groups
	 * @param bool &$moveOther
	 *
	 * @return bool
	 */
	public static function onSpecialPage_reorderPages( &$groups, &$moveOther ) {
		$groups = array_merge( array( 'wikibaserepo' => null ), $groups );
		return true;
	}

	/**
	 * Deletes all the data stored on the repository.
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/WikibaseDeleteData
	 *
	 * @since 0.1
	 *
	 * @param callable $reportMessage // takes a string param and echos it
	 *
	 * @return bool
	 */
	public static function onWikibaseDeleteData( $reportMessage ) {
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		$reportMessage( 'Deleting data from changes table...' );

		$dbw = wfGetDB( DB_MASTER );
		$dbw->delete( 'wb_changes', '*', __METHOD__ );
		$dbw->delete( 'wb_changes_dispatch', '*', __METHOD__ );

		$reportMessage( "done!\n" );

		$reportMessage( 'Deleting revisions from Data NS...' );

		$namespaceList = $dbw->makeList( $entityNamespaceLookup->getEntityNamespaces(), LIST_COMMA );

		$dbw->deleteJoin(
			'revision', 'page',
			'rev_page', 'page_id',
			array( 'page_namespace IN ( ' . $namespaceList . ')' )
		);

		$reportMessage( "done!\n" );

		$reportMessage( 'Deleting pages from Data NS...' );

		$dbw->delete(
			'page',
			array( 'page_namespace IN ( ' . $namespaceList . ')' )
		);

		$reportMessage( "done!\n" );

		$store = WikibaseRepo::getDefaultInstance()->getStore();

		$reportMessage( 'Deleting data from the ' . get_class( $store ) . ' store...' );

		$store->clear();

		$reportMessage( "done!\n" );

		return true;
	}

	/**
	 * Used to append a css class to the body, so the page can be identified as Wikibase item page.
	 * @see http://www.mediawiki.org/wiki/Manual:Hooks/OutputPageBodyAttributes
	 *
	 * @since 0.1
	 *
	 * @param OutputPage $out
	 * @param Skin $sk
	 * @param array $bodyAttrs
	 *
	 * @return bool
	 */
	public static function onOutputPageBodyAttributes( OutputPage $out, Skin $sk, array &$bodyAttrs ) {
		$outputPageEntityIdReader = new OutputPageEntityIdReader(
			WikibaseRepo::getDefaultInstance()->getEntityContentFactory(),
			WikibaseRepo::getDefaultInstance()->getEntityIdParser()
		);

		$entityId = $outputPageEntityIdReader->getEntityIdFromOutputPage( $out );

		if ( $entityId === null ) {
			return true;
		}

		// TODO: preg_replace kind of ridiculous here, should probably change the ENTITY_TYPE constants instead
		$entityType = preg_replace( '/^wikibase-/i', '', $entityId->getEntityType() );

		// add class to body so it's clear this is a wb item:
		$bodyAttrs['class'] .= ' wb-entitypage wb-' . $entityType . 'page';
		// add another class with the ID of the item:
		$bodyAttrs['class'] .= ' wb-' . $entityType . 'page-' . $entityId->getSerialization();

		if ( $sk->getRequest()->getCheck( 'diff' ) ) {
			$bodyAttrs['class'] .= ' wb-diffpage';
		}

		if ( $out->getRevisionId() !== $out->getTitle()->getLatestRevID() ) {
			$bodyAttrs['class'] .= ' wb-oldrevpage';
		}

		return true;
	}

	/**
	 * Handler for the ApiCheckCanExecute hook in ApiMain.
	 *
	 * This implementation causes the execution of ApiEditPage (action=edit) to fail
	 * for all namespaces reserved for Wikibase entities. This prevents direct text-level editing
	 * of structured data, and it also prevents other types of content being created in these
	 * namespaces.
	 *
	 * @param ApiBase $module The API module being called
	 * @param User    $user   The user calling the API
	 * @param array|string|null   $message Output-parameter holding for the message the call should fail with.
	 *                            This can be a message key or an array as expected by ApiBase::dieUsageMsg().
	 *
	 * @return bool true to continue execution, false to abort and with $message as an error message.
	 */
	public static function onApiCheckCanExecute( ApiBase $module, User $user, &$message ) {
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		if ( $module instanceof ApiEditPage ) {
			$params = $module->extractRequestParams();
			$pageObj = $module->getTitleOrPageId( $params );
			$namespace = $pageObj->getTitle()->getNamespace();

			foreach ( $entityContentFactory->getEntityContentModels() as $contentModel ) {
				/** @var EntityHandler $handler */
				$handler = ContentHandler::getForModelID( $contentModel );

				if ( $handler->getEntityNamespace() == $namespace ) {
					// trying to use ApiEditPage on an entity namespace
					$params = $module->extractRequestParams();

					// allow undo
					if ( $params['undo'] > 0 ) {
						return true;
					}

					// fail
					$message = array(
						'wikibase-no-direct-editing',
						$pageObj->getTitle()->getNsText()
					);

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Format the output when the search result contains entities
	 *
	 * @since 0.3
	 *
	 * @param SpecialSearch $searchPage
	 * @param SearchResult $result
	 * @param array $terms
	 * @param string &$link
	 * @param string &$redirect
	 * @param string &$section
	 * @param string &$extract
	 * @param string &$score
	 * @param string &$size
	 * @param string &$date
	 * @param string &$related
	 * @param string &$html
	 *
	 * @return bool
	 */
	public static function onShowSearchHit( SpecialSearch $searchPage, SearchResult $result, $terms,
		&$link, &$redirect, &$section, &$extract, &$score, &$size, &$date, &$related, &$html
	) {
		$entityContentFactory = WikibaseRepo::getDefaultInstance()->getEntityContentFactory();

		$title = $result->getTitle();
		$contentModel = $title->getContentModel();

		if ( $entityContentFactory->isEntityContentModel( $contentModel ) ) {
			/** @var EntityContent $content */
			$page = WikiPage::factory( $title );
			$content = $page->getContent();

			if ( $content && !$content->isRedirect() ) {
				$entity = $content->getEntity();
				$language = $searchPage->getLanguage();
				$description = $entity->getDescription( $language->getCode() ); // TODO: language fallback!

				if ( $description !== false && $description !== '' ) {
					$attr = array( 'class' => 'wb-itemlink-description' );
					$link .= wfMessage( 'colon-separator' )->text();
					$link .= Html::element( 'span', $attr, $description );
				}
			}

			$extract = ''; // TODO: set this to something useful.
		}

		return true;
	}

	/**
	 * Remove span tag (added by Cirrus) placed around title search hit for entity titles
	 * to highlight matches in bold.
	 *
	 * @todo highlight the Q## part of the entity link formatting and highlight label matches
	 *
	 * @param string &$link_t
	 * @param string &$titleSnippet
	 * @param SearchResult $result
	 *
	 * @return bool
	 */
	public static function onShowSearchHitTitle( &$link_t, &$titleSnippet, SearchResult $result ) {
		$title = $result->getTitle();
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		if ( $entityNamespaceLookup->isEntityNamespace( $title->getNamespace() ) ) {
			$titleSnippet = $title->getPrefixedText();
		}

		return true;
	}

	/**
	 * Handler for the TitleGetRestrictionTypes hook.
	 *
	 * Implemented to prevent people from protecting pages from being
	 * created or moved in an entity namespace (which is pointless).
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/TitleGetRestrictionTypes
	 *
	 * @since 0.3
	 *
	 * @param Title $title
	 * @param array $types The types of protection available
	 *
	 * @return bool
	 */
	public static function onTitleGetRestrictionTypes( Title $title, array &$types ) {
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		if ( $entityNamespaceLookup->isEntityNamespace( $title->getNamespace() ) ) {
			// Remove create and move protection for Wikibase namespaces
			$types = array_diff( $types, array( 'create', 'move' ) );
		}

		return true;
	}

	/**
	 * Hook handler for AbuseFilter's AbuseFilter-contentToString hook, implemented
	 * to provide a custom text representation of Entities for filtering.
	 *
	 * @param Content $content The content object
	 * @param string  &$text The resulting text
	 *
	 * @return bool
	 */
	public static function onAbuseFilterContentToString( Content $content, &$text ) {
		if ( $content instanceof EntityContent ) {
			$text = $content->getTextForFilters();

			return false;
		}

		return true;
	}

	/**
	 * Pretty formatting of autocomments.
	 *
	 * Note that this function does _not_ use $title and $local but
	 * could use them if links should be created that points to something.
	 * Typically this could be links that moves to and highlight some
	 * section within the item itself.
	 *
	 * @param string[] $data
	 * @param string $comment reference to the finalized autocomment
	 * @param string|bool $pre content before the autocomment
	 * @param string $auto the autocomment unformatted
	 * @param string|bool $post content after the autocomment
	 * @param Title|null $title use for further information
	 * @param bool $local shall links be generated locally or globally
	 *
	 * @return bool
	 */
	public static function onFormat( $data, &$comment, $pre, $auto, $post, $title, $local ) {
		global $wgLang, $wgTitle;

		list( $contentModel, $prefix ) = $data;

		// If it is possible to avoid loading the whole page then the code will be lighter on the server.
		if ( !( $title instanceof Title ) ) {
			$title = $wgTitle;
		}

		if ( !( $title instanceof Title ) || $title->getContentModel() !== $contentModel ) {
			return true;
		}

		if ( preg_match( '/^([a-z\-]+)\s*(:\s*(.*?)\s*)?$/', $auto, $matches ) ) {
			// turn the args to the message into an array
			$args = isset( $matches[3] ) ? explode( '|', $matches[3] ) : array();

			// look up the message
			$msg = wfMessage( $prefix . '-summary-' . $matches[1] );
			if ( !$msg->isDisabled() ) {
				// parse the autocomment
				$auto = $msg->params( $args )->parse();

				// add pre and post fragments
				if ( $pre === true ) {
					// written summary $presep autocomment (summary /* section */)
					$pre = wfMessage( 'autocomment-prefix' )->escaped();
				} elseif ( $pre !== '' && $pre !== false ) {
					// written summary $presep autocomment (summary /* section */)
					$pre .= wfMessage( 'autocomment-prefix' )->escaped();
				} elseif ( $pre === false ) {
					$pre = '';
				}
				if ( $post !== '' && $post !== false ) {
					// autocomment $postsep written summary (/* section */ summary)
					$auto .= wfMessage( 'colon-separator' )->escaped();
					if ( $post === true ) {
						$post = '';
					}
				} elseif ( $post === false ) {
					$post = '';
				}

				$auto = '<span class="autocomment">' . $auto . '</span>';
				$comment = $pre . $wgLang->getDirMark() . '<span dir="auto">' . $auto . '</span>' . $post;
			}
		}

		return true;
	}

	/**
	 * Called when pushing meta-info from the ParserOutput into OutputPage.
	 * Used to transfer 'wikibase-view-chunks' and entity data from ParserOutput to OutputPage.
	 *
	 * @param OutputPage $out
	 * @param ParserOutput $parserOutput
	 *
	 * @return bool
	 */
	public static function onOutputPageParserOutput( OutputPage $out, ParserOutput $parserOutput ) {
		// Set in EntityParserOutputGenerator.
		$placeholders = $parserOutput->getExtensionData( 'wikibase-view-chunks' );
		if ( $placeholders !== null ) {
			$out->setProperty( 'wikibase-view-chunks', $placeholders );
		}

		// Used in ViewEntityAction and EditEntityAction to override the page HTML title
		// with the label, if available, or else the id. Passed via parser output
		// and output page to save overhead of fetching content and accessing an entity
		// on page view.
		$titleText = $parserOutput->getExtensionData( 'wikibase-titletext' );
		if ( $titleText !== null ) {
			$out->setProperty( 'wikibase-titletext', $titleText );
		}

		// Array with <link rel="alternate"> tags for the page HEAD.
		$alternateLinks = $parserOutput->getExtensionData( 'wikibase-alternate-links' );
		if ( $alternateLinks !== null ) {
			foreach ( $alternateLinks as $link ) {
				$out->addLink( $link );
			}
		}

		return true;
	}

	/**
	 * Puts user-specific and other vars that we don't want stuck
	 * in parser cache (e.g. copyright message)
	 *
	 * @param OutputPage $out
	 * @param string &$html
	 *
	 * @return bool
	 */
	public static function onOutputPageBeforeHtmlRegisterConfig( OutputPage $out, &$html ) {
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		if ( !$entityNamespaceLookup->isEntityNamespace( $out->getTitle()->getNamespace() ) ) {
			return true;
		}

		$settings = WikibaseRepo::getDefaultInstance()->getSettings();
		$hookHandler = new OutputPageJsConfigHookHandler( $settings );

		$isExperimental = defined( 'WB_EXPERIMENTAL_FEATURES' ) && WB_EXPERIMENTAL_FEATURES;

		$hookHandler->handle( $out, $isExperimental );

		return true;
	}

	/**
	 * Handler for the ContentModelCanBeUsedOn hook, used to prevent pages of inappropriate type
	 * to be placed in an entity namespace.
	 *
	 * @param string $contentModel
	 * @param Title $title
	 * @param bool $ok
	 *
	 * @return bool
	 */
	public static function onContentModelCanBeUsedOn( $contentModel, Title $title, &$ok ) {
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		$expectedModel = array_search(
			$title->getNamespace(),
			$entityNamespaceLookup->getEntityNamespaces()
		);

		// If the namespace is an entity namespace, the content model
		// must be the model assigned to that namespace.
		if ( $expectedModel !== false && $expectedModel !== $contentModel ) {
			$ok = false;
			return false;
		}

		return true;
	}

	/**
	 * Handler for the ContentHandlerForModelID hook, implemented to create EntityHandler
	 * instances that have knowledge of the necessary services.
	 *
	 * @param string $modelId
	 * @param ContentHandler|null $handler
	 *
	 * @return bool
	 */
	public static function onContentHandlerForModelID( $modelId, &$handler ) {
		// FIXME: a mechanism for registering additional entity types needs to be put in place.
		switch ( $modelId ) {
			case CONTENT_MODEL_WIKIBASE_ITEM:
				$handler = WikibaseRepo::getDefaultInstance()->newItemHandler();
				return false;

			case CONTENT_MODEL_WIKIBASE_PROPERTY:
				$handler = WikibaseRepo::getDefaultInstance()->newPropertyHandler();
				return false;

			default:
				return true;
		}
	}

	/**
	 * Helper for onAPIQuerySiteInfoStatisticsInfo
	 * @param object $row
	 * @return array
	 */
	private static function formatDispatchRow( $row ) {
		$data = array(
			'pending' => $row->chd_pending,
			'lag' => $row->chd_lag,
		);
		if ( isset( $row->chd_site ) ) {
			$data['site'] = $row->chd_site;
		}
		if ( isset( $row->chd_seen ) ) {
			$data['position'] = $row->chd_seen;
		}
		if ( isset( $row->chd_touched ) ) {
			$data['touched'] = wfTimestamp( TS_ISO_8601, $row->chd_touched );
		}

		return $data;
	}

	/**
	 * Adds DispatchStats info to the API
	 * @param array $data
	 * @return bool
	 */
	public static function onAPIQuerySiteInfoStatisticsInfo( array &$data ) {
		$stats = new DispatchStats();
		$stats->load();
		if ( $stats->hasStats() ) {
			$data['dispatch'] = array(
				'oldest' => array(
					'id' => $stats->getMinChangeId(),
					'timestamp' => $stats->getMinChangeTimestamp(),
				),
				'newest' => array(
					'id' => $stats->getMaxChangeId(),
					'timestamp' => $stats->getMaxChangeTimestamp(),
				),
				'freshest' => self::formatDispatchRow( $stats->getFreshest() ),
				'median' => self::formatDispatchRow( $stats->getMedian() ),
				'stalest' => self::formatDispatchRow( $stats->getStalest() ),
				'average' => self::formatDispatchRow( $stats->getAverage() ),
			);
		}

		return true;
	}

	/**
	 * Called by Import.php. Implemented to prevent the import of entities.
	 *
	 * @param object $importer unclear, see Bug 64657
	 * @param array $pageInfo
	 * @param array $revisionInfo
	 *
	 * @throws MWException
	 * @return bool
	 */
	public static function onImportHandleRevisionXMLTag( $importer, $pageInfo, $revisionInfo ) {
		if ( isset( $revisionInfo['model'] ) ) {
			$contentModels = WikibaseRepo::getDefaultInstance()->getContentModelMappings();
			$allowImport = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'allowEntityImport' );

			if ( !$allowImport && in_array( $revisionInfo['model'], $contentModels ) ) {
				// Skip entities.
				// XXX: This is rather rough.
				throw new MWException( 'To avoid ID conflicts, the import of Wikibase entities is not supported. You can enable imports using the `allowEntityImport` setting.' );
			}
		}

		return true;
	}

	/**
	 * Called in SkinTemplate::buildNavUrls(), allows us to set up navigation URLs to later be used
	 * in the toolbox.
	 *
	 * @param SkinTemplate $skinTemplate
	 * @param array $navigationUrls
	 *
	 * @return bool
	 */
	public static function onSkinTemplateBuildNavUrlsNav_urlsAfterPermalink(
		SkinTemplate $skinTemplate,
		array &$navigationUrls
	) {
		$title = $skinTemplate->getTitle();
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		if ( !$entityNamespaceLookup->isEntityNamespace( $title->getNamespace() ) ) {
			return true;
		}

		$baseUri = WikibaseRepo::getDefaultInstance()->getSettings()->getSetting( 'conceptBaseUri' );
		$navigationUrls['wb-concept-uri'] = array(
			'text' => $skinTemplate->msg( 'wikibase-concept-uri' ),
			'href' => $baseUri . $title->getDBKey(),
			'title' => $skinTemplate->msg( 'wikibase-concept-uri-tooltip' )
		);

		return true;
	}

	/**
	 * Called in BaseTemplate::getToolbox(), allows us to add navigation URLs to the toolbox.
	 *
	 * @param BaseTemplate $baseTemplate
	 * @param array $toolbox
	 *
	 * @return bool
	 */
	public static function onBaseTemplateToolbox( BaseTemplate $baseTemplate, array &$toolbox ) {
		if ( !isset( $baseTemplate->data['nav_urls']['wb-concept-uri'] ) ) {
			return true;
		}

		$toolbox['wb-concept-uri'] = $baseTemplate->data['nav_urls']['wb-concept-uri'];
		$toolbox['wb-concept-uri']['id'] = 't-wb-concept-uri';

		return true;
	}

	/**
	 * Disable mobile editor for entity pages in Extension:MobileFrontend.
	 * @see https://www.mediawiki.org/wiki/Extension:MobileFrontend
	 *
	 * @param Skin $skin
	 * @param array &$modules associative array of resource loader modules
	 *
	 * @return bool
	 */
	public static function onSkinMinervaDefaultModules( Skin $skin, array &$modules ) {
		$title = $skin->getTitle();
		$entityNamespaceLookup = WikibaseRepo::getDefaultInstance()->getEntityNamespaceLookup();

		// remove the editor module so that it does not get loaded on entity pages
		if ( $entityNamespaceLookup->isEntityNamespace( $title->getNamespace() ) ) {
			unset( $modules['editor'] );
		}

		return true;
	}

	/**
	 * Register ResourceLoader modules with dynamic dependencies.
	 *
	 * @param ResourceLoader $resourceLoader
	 */
	public static function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ) {
		preg_match( '+' . preg_quote( DIRECTORY_SEPARATOR ) . '(?:vendor|extensions)'
			. preg_quote( DIRECTORY_SEPARATOR ) . '.*+', __DIR__, $remoteExtPath );
		$hasULS = ExtensionRegistry::getInstance()->isLoaded( 'UniversalLanguageSelector' );

		$moduleTemplate = array(
			'localBasePath' => __DIR__ . '/resources',
			'remoteExtPath' => '..' . $remoteExtPath[0],
			'position' => 'top' // reducing the time between DOM construction and JS initialisation
		);

		$dependencies = array(
			'util.ContentLanguages',
			'util.inherit',
			'wikibase',
		);

		if ( $hasULS ) {
			$dependencies[] = 'ext.uls.languagenames';
		}

		$resourceLoader->register(
			'wikibase.WikibaseContentLanguages',
			$moduleTemplate + array(
				'scripts' => array(
					'wikibase.WikibaseContentLanguages.js',
				),
				'dependencies' => $dependencies
			)
		);

		$dependencies = array(
			'wikibase.special',
			'jquery.ui.suggester'
		);

		if ( $hasULS ) {
			$dependencies[] = 'ext.uls.mediawiki';
		}

		$resourceLoader->register(
			'wikibase.special.itemDisambiguation',
			$moduleTemplate + array(
				'scripts' => array(
					'wikibase.special/wikibase.special.itemDisambiguation.js'
				),
				'dependencies' => $dependencies
			)
		);

		$dependencies = array(
			'wikibase.special',
			'jquery.ui.suggester'
		);

		if ( $hasULS ) {
			$dependencies[] = 'ext.uls.mediawiki';
		}

		$resourceLoader->register(
			'wikibase.special.entitiesWithout',
			$moduleTemplate + array(
				'scripts' => array(
					'wikibase.special/wikibase.special.entitiesWithout.js'
				),
				'dependencies' => $dependencies
			)
		);

		return true;
	}
}
