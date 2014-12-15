<?php

/**
 * Maintenance script for importing interlanguage links in Wikidata.
 *
 * For using it with the included simple-elements.csv and fill the database with chemical elements, use it thusly:
 *
 * php importInterlang.php --verbose --ignore-errors simple simple-elements.csv
 *
 * @since 0.1
 *
 * @licence GNU GPL v2+
 * @author Nikola Smolenski <smolensk@eunet.rs>
 * @author Jens Ohlig < jens.ohlig@wikimedia.de >
 */

use Wikibase\DataModel\Entity\Item;
use Wikibase\Lib\Store\EntityStore;
use Wikibase\Repo\WikibaseRepo;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';

require_once $basePath . '/maintenance/Maintenance.php';

class importInterlang extends Maintenance {

	/**
	 * @var bool
	 */
	private $verbose = false;

	/**
	 * @var bool
	 */
	private $ignoreErrors = false;

	/**
	 * @var int
	 */
	private $skip = 0;

	/**
	 * @var int
	 */
	private $only = 0;

	/**
	 * @var User
	 */
	private $user;

	/**
	 * @var EntityStore
	 */
	private $store;

	public function __construct() {
		$this->mDescription = 'Import interlanguage links in Wikidata.\n\nThe links may be created by extractInterlang.sql';

		$this->addOption( 'skip', 'Skip number of entries in the import file' );
		$this->addOption( 'only', 'Only import the specific entry from the import file' );
		$this->addOption( 'verbose', 'Print activity' );
		$this->addOption( 'ignore-errors', 'Continue after errors' );
		$this->addArg( 'lang', 'The source wiki\'s language code (e.g. "en")', true );
		$this->addArg( 'filename', 'File with interlanguage links', true );

		parent::__construct();
	}

	public function execute() {
		global $wgUser;

		if ( !defined( 'WB_VERSION' ) ) {
			$this->output( "You need to have Wikibase enabled in order to use this maintenance script!\n\n" );
			exit;
		}

		$this->user = $wgUser;
		$this->store = WikibaseRepo::getDefaultInstance()->getEntityStore();

		$this->verbose = (bool)$this->getOption( 'verbose' );
		$this->ignoreErrors = (bool)$this->getOption( 'ignore-errors' );
		$this->skip = (int)$this->getOption( 'skip' );
		$this->only = (int)$this->getOption( 'only' );
		$languageCode = $this->getArg( 0 );
		$filename = $this->getArg( 1 );

		$file = fopen( $filename, 'r' );

		if ( !$file ) {
			$this->doPrint( 'ERROR: failed to open ' . $filename );
			return;
		}

		fgets( $file ); // We don't need the first line with column names.

		$current = null;
		$currentLinks = array();
		$count = 0;
		$ok = true;
		while ( $link = fgetcsv( $file, 0, "\t" ) ) {
			if ( $link[0] !== $current ) {
				if ( !empty( $currentLinks ) ) {
					$ok = $this->createItem( $currentLinks );

					if ( !$ok && !$this->ignoreErrors ) {
						break;
					}
				}

				$count++;
				if ( ( $this->skip !== 0 ) && ( $this->skip > $count ) ) {
					continue;
				}
				if ( ( $this->only !== 0 ) && ( $this->only !== $count ) ) {
					if ( $this->only < $count ) {
						break;
					}
					continue;
				}

				$current = $link[0];
				$this->maybePrint( 'Processing ' . $current );
				$currentLinks = array( $languageCode => $current );
			}

			$currentLinks[$link[1]] = $link[2];
		}

		if ( !$ok && !$this->ignoreErrors ) {
			$this->doPrint( 'Aborted!' );
			return;
		}

		if ( !empty( $currentLinks ) ) {
			$ok = $this->createItem( $currentLinks );
		}

		if ( $ok ) {
			$this->maybePrint( 'Done.' );
		}
	}

	/**
	 * @param string[] $titles Associative array of interlanguage links, mapping language codes to page titles.
	 *
	 * @return bool true if the item was created, false otherwise
	 */
	private function createItem( array $titles ) {
		$item = Item::newEmpty();
		$fingerprint = $item->getFingerprint();
		$siteLinks = $item->getSiteLinkList();

		foreach ( $titles as $languageCode => $title ) {
			$pageName = str_replace( '_', ' ', $title );
			$label = preg_replace( '/\s*\(.*\)$/u', '', $pageName );

			$fingerprint->setLabel( $languageCode, $label );
			$siteLinks->addNewSiteLink( $languageCode . 'wiki', $pageName );
		}

		try {
			$this->store->saveEntity( $item, 'imported', $this->user, EDIT_NEW );
			return true;
		} catch ( Exception $ex ) {
			$this->doPrint( 'ERROR: ' . str_replace( "\n", ' ', $ex->getMessage() ) );
		}

		return false;
	}

	/**
	 * Print a scalar, array or object if --verbose option is set.
	 *
	 * @see doPrint
	 */
	private function maybePrint( $a ) {
		if ( $this->verbose ) {
			$this->doPrint( $a );
		}
	}

	/**
	 * Output a scalar, array or object to the default channel
	 *
	 * @see Maintenance::output
	 */
	private function doPrint( $var ) {
		if ( is_null( $var ) ) {
			$var = 'null';
		} elseif ( is_bool( $var ) ) {
			$var = $var ? "true\n": "false\n";
		} elseif ( !is_scalar( $var ) ) {
			$var = print_r( $var, true );
		}

		$this->output( trim( strval( $var ) ) . "\n" );
	}

}

$maintClass = 'importInterlang';
require_once( RUN_MAINTENANCE_IF_MAIN );
