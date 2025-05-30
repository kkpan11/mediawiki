<?php
/**
 * Remove old or broken uploads from temporary uploaded file storage,
 * clean up associated database records
 *
 * Copyright © 2011, Wikimedia Foundation
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Ian Baker <ibaker@wikimedia.org>
 * @ingroup Maintenance
 */

use MediaWiki\FileRepo\FileRepo;
use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;

// @codeCoverageIgnoreStart
require_once __DIR__ . '/Maintenance.php';
// @codeCoverageIgnoreEnd

/**
 * Maintenance script to remove old or broken uploads from temporary uploaded
 * file storage and clean up associated database records.
 *
 * @ingroup Maintenance
 */
class CleanupUploadStash extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Clean up abandoned files in temporary uploaded file stash' );
		$this->setBatchSize( 50 );
	}

	public function execute() {
		$repo = $this->getServiceContainer()->getRepoGroup()->getLocalRepo();
		$tempRepo = $repo->getTempRepo();

		$dbr = $repo->getReplicaDB();

		// how far back should this look for files to delete?
		$cutoff = time() - (int)$this->getConfig()->get( MainConfigNames::UploadStashMaxAge );

		$this->output( "Getting list of files to clean up...\n" );
		$res = $dbr->newSelectQueryBuilder()
			->select( 'us_key' )
			->from( 'uploadstash' )
			->where( $dbr->expr( 'us_timestamp', '<', $dbr->timestamp( $cutoff ) ) )
			->caller( __METHOD__ )
			->fetchResultSet();

		// Delete all registered stash files...
		if ( $res->numRows() == 0 ) {
			$this->output( "No stashed files to cleanup according to the DB.\n" );
		} else {
			// finish the read before starting writes.
			$keys = [];
			foreach ( $res as $row ) {
				$keys[] = $row->us_key;
			}

			$this->output( 'Removing ' . count( $keys ) . " file(s)...\n" );
			// this could be done some other, more direct/efficient way, but using
			// UploadStash's own methods means it's less likely to fall accidentally
			// out-of-date someday
			$stash = new UploadStash( $repo );

			$i = 0;
			foreach ( $keys as $key ) {
				$i++;
				try {
					$stash->getFile( $key, true );
					$stash->removeFileNoAuth( $key );
				} catch ( UploadStashException $ex ) {
					$type = get_class( $ex );
					$this->output( "Failed removing stashed upload with key: $key ($type)\n" );
				}
				if ( $i % 100 == 0 ) {
					$this->waitForReplication();
					$this->output( "$i\n" );
				}
			}
			$this->output( "$i done\n" );
		}

		// Delete all the corresponding thumbnails...
		$dir = $tempRepo->getZonePath( 'thumb' );
		$iterator = $tempRepo->getBackend()->getFileList( [ 'dir' => $dir, 'adviseStat' => 1 ] );
		if ( $iterator === null ) {
			$this->fatalError( "Could not get file listing." );
		}
		$this->output( "Deleting old thumbnails...\n" );
		$i = 0;
		$batch = [];
		foreach ( $iterator as $file ) {
			if ( wfTimestamp( TS_UNIX, $tempRepo->getFileTimestamp( "$dir/$file" ) ) < $cutoff ) {
				$batch[] = [ 'op' => 'delete', 'src' => "$dir/$file" ];
				if ( count( $batch ) >= $this->getBatchSize() ) {
					$this->doOperations( $tempRepo, $batch );
					$i += count( $batch );
					$batch = [];
					$this->output( "$i\n" );
				}
			}
		}
		if ( count( $batch ) ) {
			$this->doOperations( $tempRepo, $batch );
			$i += count( $batch );
		}
		$this->output( "$i done\n" );

		// Apparently lots of stash files are not registered in the DB...
		$dir = $tempRepo->getZonePath( 'public' );
		$iterator = $tempRepo->getBackend()->getFileList( [ 'dir' => $dir, 'adviseStat' => 1 ] );
		if ( $iterator === null ) {
			$this->fatalError( "Could not get file listing." );
		}
		$this->output( "Deleting orphaned temp files...\n" );
		if ( strpos( $dir, '/local-temp' ) === false ) {
			$this->output( "Temp repo might be misconfigured. It points to directory: '$dir' \n" );
		}

		$i = 0;
		$batch = [];
		foreach ( $iterator as $file ) {
			if ( wfTimestamp( TS_UNIX, $tempRepo->getFileTimestamp( "$dir/$file" ) ) < $cutoff ) {
				$batch[] = [ 'op' => 'delete', 'src' => "$dir/$file" ];
				if ( count( $batch ) >= $this->getBatchSize() ) {
					$this->doOperations( $tempRepo, $batch );
					$i += count( $batch );
					$batch = [];
					$this->output( "$i\n" );
				}
			}
		}
		if ( count( $batch ) ) {
			$this->doOperations( $tempRepo, $batch );
			$i += count( $batch );
		}
		$this->output( "$i done\n" );
	}

	protected function doOperations( FileRepo $tempRepo, array $ops ) {
		$status = $tempRepo->getBackend()->doQuickOperations( $ops );
		if ( !$status->isOK() ) {
			$this->error( $status );
		}
	}
}

// @codeCoverageIgnoreStart
$maintClass = CleanupUploadStash::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
