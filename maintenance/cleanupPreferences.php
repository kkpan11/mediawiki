<?php
/**
 * Clean up user preferences from the database.
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
 * @author TyA <tya.wiki@gmail.com>
 * @author Chad <chad@wikimedia.org>
 * @see https://phabricator.wikimedia.org/T32976
 * @ingroup Maintenance
 */

// @codeCoverageIgnoreStart
require_once __DIR__ . '/Maintenance.php';
// @codeCoverageIgnoreEnd

use MediaWiki\MainConfigNames;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\User\Options\UserOptionsLookup;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;

/**
 * Maintenance script that removes unused preferences from the database.
 *
 * @ingroup Maintenance
 */
class CleanupPreferences extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Clean up hidden preferences or removed preferences' );
		$this->setBatchSize( 50 );
		$this->addOption( 'dry-run', 'Print debug info instead of actually deleting' );
		$this->addOption( 'hidden', 'Drop hidden preferences ($wgHiddenPrefs)' );
		$this->addOption( 'unknown',
			'Drop unknown preferences (not in $wgDefaultUserOptions or prefixed with "userjs-")' );
	}

	/**
	 * We will do this in three passes
	 *   1) The easiest is to drop the hidden preferences from the database. We
	 *      don't actually want them
	 *   2) Drop preference keys that we don't know about. They could've been
	 *      removed from core, provided by a now-disabled extension, or the result
	 *      of a bug. We don't want them.
	 */
	public function execute() {
		$dbr = $this->getReplicaDB();
		$hidden = $this->hasOption( 'hidden' );
		$unknown = $this->hasOption( 'unknown' );

		if ( !$hidden && !$unknown ) {
			$this->output( "Did not select one of --hidden, --unknown, exiting\n" );
			return;
		}

		// Remove hidden prefs. Iterate over them to avoid the IN on a large table
		if ( $hidden ) {
			$hiddenPrefs = $this->getConfig()->get( MainConfigNames::HiddenPrefs );
			if ( !$hiddenPrefs ) {
				$this->output( "No hidden preferences, skipping\n" );
			}
			foreach ( $hiddenPrefs as $hiddenPref ) {
				$this->deleteByWhere(
					$dbr,
					'Dropping hidden preferences',
					[ 'up_property' => $hiddenPref ]
				);
			}
		}

		// Remove unknown preferences. Special-case 'userjs-' as we can't control those names.
		if ( $unknown ) {
			$defaultUserOptions = $this->getServiceContainer()->getUserOptionsLookup()->getDefaultOptions( null );
			$where = [
				$dbr->expr( 'up_property', IExpression::NOT_LIKE,
					new LikeValue( 'userjs-', $dbr->anyString() ) ),
				$dbr->expr( 'up_property', IExpression::NOT_LIKE,
					new LikeValue( UserOptionsLookup::LOCAL_EXCEPTION_SUFFIX, $dbr->anyString() ) ),
				$dbr->expr( 'up_property', '!=', array_keys( $defaultUserOptions ) ),
			];
			// Allow extensions to add to the where clause to prevent deletion of their own prefs.
			$this->getHookRunner()->onDeleteUnknownPreferences( $where, $dbr );
			$this->deleteByWhere( $dbr, 'Dropping unknown preferences', $where );
		}
	}

	private function deleteByWhere( IReadableDatabase $dbr, string $startMessage, array $where ) {
		$this->output( $startMessage . "...\n" );
		$dryRun = $this->hasOption( 'dry-run' );

		$iterator = new BatchRowIterator(
			$dbr,
			$dbr->newSelectQueryBuilder()
				->from( 'user_properties' )
				->select( $dryRun ?
					[ 'up_user', 'up_property', 'up_value' ] :
					[ 'up_user', 'up_property' ] )
				->where( $where )
				->caller( __METHOD__ ),
			[ 'up_user', 'up_property' ],
			$this->getBatchSize()
		);

		$dbw = $this->getPrimaryDB();
		$total = 0;
		foreach ( $iterator as $batch ) {
			$numRows = count( $batch );
			$total += $numRows;
			// Progress or something
			$this->output( "..doing $numRows entries\n" );

			// Delete our batch, then wait
			$deleteWhere = [];
			foreach ( $batch as $row ) {
				if ( $dryRun ) {
					$this->output(
						"    DRY RUN, would drop: " .
						"[up_user] => '{$row->up_user}' " .
						"[up_property] => '{$row->up_property}' " .
						"[up_value] => '{$row->up_value}'\n"
					);
					continue;
				}
				$deleteWhere[$row->up_user][$row->up_property] = true;
			}
			if ( $deleteWhere && !$dryRun ) {
				$dbw->newDeleteQueryBuilder()
					->deleteFrom( 'user_properties' )
					->where( $dbw->makeWhereFrom2d( $deleteWhere, 'up_user', 'up_property' ) )
					->caller( __METHOD__ )->execute();

				$this->waitForReplication();
			}
		}
		$this->output( "DONE! (handled $total entries)\n" );
	}
}

// @codeCoverageIgnoreStart
$maintClass = CleanupPreferences::class; // Tells it to run the class
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
