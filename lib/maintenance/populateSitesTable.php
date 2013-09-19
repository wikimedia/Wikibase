<?php

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../../..';
require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Maintenance script for populating the Sites table from another wiki that runs the
 * SiteMatrix extension.
 *
 * @since 0.1
 * @note: this should move out of Wikibase
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 * @author Katie Filbert < aude.wiki@gmail.com >
 */
class PopulateSitesTable extends Maintenance {

	public function __construct() {
		$this->mDescription = 'Populate the sites table from another wiki that runs the SiteMatrix extension';

		$this->addOption( 'strip-protocols', "Strip http/https from URLs to make them protocol relative." );
		$this->addOption( 'load-from', "Full URL to the API of the wiki to fetch the site info from. "
				. "Default is https://meta.wikimedia.org/w/api.php", false, true );
		$this->addOption( 'script-path', 'Script path to use for wikis in the site matrix. '
				. ' (e.g. "/w/$1")', false, true );
		$this->addOption( 'article-path', 'Article path for wikis in the site matrix. '
				. ' (e.g. "/wiki/$1")', false, true );
		$this->addOption( 'site-group', 'Site group that this wiki is a member of.  Used to populate '
				. ' local interwiki identifiers in the site identifiers table.  If not set and --wiki'
				. ' is set, the script will try to determine which site group the wiki is part of'
				. ' and populate interwiki ids for sites in that group.', false, true );
		$this->addOption( 'no-expand-group', 'Do not expand site group codes in site matrix. '
				. ' By default, "wiki" is expanded to "wikipedia".' );

		parent::__construct();
	}

	public function execute() {
		$stripProtocols = $this->getOption( 'strip-protocols', false );
		$url = $this->getOption( 'load-from', 'https://meta.wikimedia.org/w/api.php' );
		$scriptPath = $this->getOption( 'script-path', '/w/$1' );
		$articlePath = $this->getOption( 'article-path', '/wiki/$1' );
		$expandGroup = !$this->getOption( 'no-expand-group', false );
		$siteGroup = $this->getOption( 'site-group' );
		$wikiId = $this->getOption( 'wiki' );

		try {
			$json = $this->getSiteMatrixData( $url );

			$siteMatrixParser = new SiteMatrixParser( $scriptPath, $articlePath,
				$stripProtocols, $expandGroup );

			$sites = $siteMatrixParser->sitesFromJson( $json );

			$store = SiteSQLStore::newInstance();
			$sitesBuilder = new SitesBuilder( $store );
			$sitesBuilder->buildStore( $sites, $siteGroup, $wikiId );

		} catch ( MWException $e ) {
			$this->output( $e->getMessage() );
		}

		$this->output( "done.\n" );
	}

	/**
	 * @param string $url
	 *
	 * @throws MWException
	 *
	 * @return string
	 */
	protected function getSiteMatrixData( $url ) {
		$url .= '?action=sitematrix&format=json';

		//NOTE: the raiseException option needs change Iad3995a6 to be merged, otherwise it is ignored.
		$json = Http::get( $url, 'default', array( 'raiseException' => true ) );

		if ( !$json ) {
			throw new MWException( "Got no data from $url\n" );
		}

		return $json;
	}
}

$maintClass = 'PopulateSitesTable';
require_once ( RUN_MAINTENANCE_IF_MAIN );
