<?php
/**
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
 */

namespace MediaWiki\JobQueue;

use LogicException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\ReadOnlyMode;
use Wikimedia\Stats\StatsFactory;
use Wikimedia\UUID\GlobalIdGenerator;

/**
 * Factory for JobQueueGroup objects.
 *
 * @since 1.37
 * @ingroup JobQueue
 */
class JobQueueGroupFactory {
	/**
	 * @internal For use by ServiceWiring
	 */
	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::JobClasses,
		MainConfigNames::JobTypeConf,
		MainConfigNames::JobTypesExcludedFromDefaultQueue,
		MainConfigNames::LocalDatabases,
	];

	/** @var JobQueueGroup[] */
	private $instances;

	/** @var ServiceOptions */
	private $options;

	/** @var ReadOnlyMode */
	private $readOnlyMode;

	/** @var StatsFactory */
	private $statsFactory;

	/** @var WANObjectCache */
	private $wanCache;

	/** @var GlobalIdGenerator */
	private $globalIdGenerator;

	/**
	 * @param ServiceOptions $options
	 * @param ReadOnlyMode $readOnlyMode
	 * @param StatsFactory $statsFactory
	 * @param WANObjectCache $wanCache
	 * @param GlobalIdGenerator $globalIdGenerator
	 */
	public function __construct(
		ServiceOptions $options,
		ReadOnlyMode $readOnlyMode,
		StatsFactory $statsFactory,
		WANObjectCache $wanCache,
		GlobalIdGenerator $globalIdGenerator
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->instances = [];
		$this->options = $options;
		$this->readOnlyMode = $readOnlyMode;
		$this->statsFactory = $statsFactory;
		$this->wanCache = $wanCache;
		$this->globalIdGenerator = $globalIdGenerator;
	}

	/**
	 * @since 1.37
	 *
	 * @param false|string $domain Wiki domain ID. False uses the current wiki domain ID
	 * @return JobQueueGroup
	 */
	public function makeJobQueueGroup( $domain = false ): JobQueueGroup {
		if ( $domain === false ) {
			$domain = WikiMap::getCurrentWikiDbDomain()->getId();
		}

		// Make sure jobs are not getting pushed to bogus wikis. This can confuse
		// the job runner system into spawning endless RPC requests that fail (T171371).
		$isCurrentWiki = WikiMap::isCurrentWikiDbDomain( $domain );
		if ( !$isCurrentWiki ) {
			$wikiId = WikiMap::getWikiIdFromDbDomain( $domain );
			if ( !in_array( $wikiId, $this->options->get( MainConfigNames::LocalDatabases ) ) ) {
				// Do not enqueue job that cannot be run (T171371)
				throw new LogicException( "Domain '{$domain}' is not recognized." );
			}
		}

		if ( !isset( $this->instances[$domain] ) ) {
			$localJobClasses = $isCurrentWiki
				? $this->options->get( MainConfigNames::JobClasses )
				: null;

			$this->instances[$domain] = new JobQueueGroup(
				$domain,
				$this->readOnlyMode,
				$localJobClasses,
				$this->options->get( MainConfigNames::JobTypeConf ),
				$this->options->get( MainConfigNames::JobTypesExcludedFromDefaultQueue ),
				$this->statsFactory,
				$this->wanCache,
				$this->globalIdGenerator
			);
		}

		return $this->instances[$domain];
	}
}
