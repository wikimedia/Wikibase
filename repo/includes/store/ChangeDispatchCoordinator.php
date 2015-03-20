<?php

namespace Wikibase\Store;

/**
 * Service interface for a inter-process locking service intended to coordinate
 * message dispatching between multiple processes.
 *
 * The purpose of a ChangeDispatchCoordinator is to determine which client wiki to dispatch
 * to next in a fair manner, and to prevent multiple processes to try and dispatch to the
 * same wiki at once.
 *
 * @since 0.5
 *
 * @licence GNU GPL v2+
 * @author Daniel Kinzler
 */
interface ChangeDispatchCoordinator {

	/**
	 * Selects a client wiki and locks it. If no suitable client wiki can be found,
	 * this method returns null.
	 *
	 * Note: this implementation will try a wiki from the list returned by getCandidateClients()
	 * at random. If all have been tried and failed, it returns null.
	 *
	 * @return array An associative array containing the state of the selected client wiki
	 *               (or null, if no target could be locked). Fields are:
	 *
	 * * chd_site:     the client wiki's global site ID
	 * * chd_db:       the client wiki's logical database name
	 * * chd_seen:     the last change ID processed for that client wiki
	 * * chd_touched:  timestamp giving the last time that client wiki was updated
	 * * chd_lock:     the name of a global lock currently active for that client wiki
	 *
	 * @throws \MWException if no available client wiki could be found.
	 *
	 * @see releaseWiki()
	 */
	public function selectClient();

	/**
	 * Initializes the dispatch table by injecting dummy records for all target wikis
	 * that are in the configuration but not yet in the dispatch table.
	 */
	public function initState();

	/**
	 * Attempt to lock the given target wiki. If it can't be locked because
	 * another dispatch process is working on it, this method returns false.
	 *
	 * @param string $siteID The ID of the client wiki to lock.
	 *
	 * @throws \MWException if there are no client wikis to chose from.
	 * @throws \Exception
	 * @return array An associative array containing the state of the selected client wiki
	 *               (see selectClient()) or false if the client wiki could not be locked.
	 *
	 * @see selectClient()
	 */
	public function lockClient( $siteID );

	/**
	 * Updates the given client wiki's entry in the dispatch table and
	 * releases the global lock on that wiki.
	 *
	 * @param int $seen   :  the ID of the last change processed in the pass.
	 * @param array $state  : associative array representing the client wiki's state before the
	 *                      update pass, as returned by selectWiki().
	 *
	 * @throws \Exception
	 * @see selectWiki()
	 */
	public function releaseClient( $seen, array $state );

}
