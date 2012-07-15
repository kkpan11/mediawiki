<?php
/**
 *
 *
 * Created on Sep 27, 2008
 *
 * Copyright © 2008 Roan Kattouw "<Firstname>.<Lastname>@gmail.com"
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
 */

/**
 * A query module to list duplicates of the given file(s)
 *
 * @ingroup API
 */
class ApiQueryDuplicateFiles extends ApiQueryGeneratorBase {

	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'df' );
	}

	public function execute() {
		$this->run();
	}

	public function getCacheMode( $params ) {
		return 'public';
	}

	public function executeGenerator( $resultPageSet ) {
		$this->run( $resultPageSet );
	}

	/**
	 * @param $resultPageSet ApiPageSet
	 * @return
	 */
	private function run( $resultPageSet = null ) {
		$params = $this->extractRequestParams();
		$namespaces = $this->getPageSet()->getAllTitlesByNamespace();
		if ( empty( $namespaces[NS_FILE] ) ) {
			return;
		}
		$images = $namespaces[NS_FILE];

		$this->addTables( 'image', 'i1' );
		$this->addTables( 'image', 'i2' );
		$this->addFields( array(
			'i1.img_name AS orig_name',
			'i2.img_name AS dup_name',
			'i2.img_user_text AS dup_user_text',
			'i2.img_timestamp AS dup_timestamp'
		) );

		$this->addWhere( array(
			'i1.img_name' => array_keys( $images ),
			'i1.img_sha1 = i2.img_sha1',
			'i1.img_name != i2.img_name',
		) );

		if ( isset( $params['continue'] ) ) {
			$cont = explode( '|', $params['continue'] );
			if ( count( $cont ) != 2 ) {
				$this->dieUsage( 'Invalid continue param. You should pass the ' .
					'original value returned by the previous query', '_badcontinue' );
			}
			$op = $params['dir'] == 'descending' ? '<' : '>';
			$db = $this->getDB();
			$orig = $db->addQuotes( $this->titleTokey( $cont[0] ) );
			$dup = $db->addQuotes( $this->titleToKey( $cont[1] ) );
			$this->addWhere(
				"i1.img_name $op $orig OR " .
				"(i1.img_name = $orig AND " .
				"i2.img_name $op= $dup)"
			);
		}

		$sort = ( $params['dir'] == 'descending' ? ' DESC' : '' );
		// Don't order by i1.img_name if it's constant in the WHERE clause
		if ( count( $this->getPageSet()->getGoodTitles() ) == 1 ) {
			$this->addOption( 'ORDER BY', 'i2.img_name' . $sort );
		} else {
			$this->addOption( 'ORDER BY', array(
					'i1.img_name' . $sort,
					'i2.img_name' . $sort
			));
		}
		$this->addOption( 'LIMIT', $params['limit'] + 1 );

		$res = $this->select( __METHOD__ );
		$count = 0;
		$titles = array();
		foreach ( $res as $row ) {
			if ( ++$count > $params['limit'] ) {
				// We've reached the one extra which shows that
				// there are additional pages to be had. Stop here...
				$this->setContinueEnumParameter( 'continue',
					$this->keyToTitle( $row->orig_name ) . '|' .
					$this->keyToTitle( $row->dup_name ) );
				break;
			}
			if ( !is_null( $resultPageSet ) ) {
				$titles[] = Title::makeTitle( NS_FILE, $row->dup_name );
			} else {
				$r = array(
					'name' => $row->dup_name,
					'user' => $row->dup_user_text,
					'timestamp' => wfTimestamp( TS_ISO_8601, $row->dup_timestamp )
				);
				$fit = $this->addPageSubItem( $images[$row->orig_name], $r );
				if ( !$fit ) {
					$this->setContinueEnumParameter( 'continue',
							$this->keyToTitle( $row->orig_name ) . '|' .
							$this->keyToTitle( $row->dup_name ) );
					break;
				}
			}
		}
		if ( !is_null( $resultPageSet ) ) {
			$resultPageSet->populateFromTitles( $titles );
		}
	}

	public function getAllowedParams() {
		return array(
			'limit' => array(
				ApiBase::PARAM_DFLT => 10,
				ApiBase::PARAM_TYPE => 'limit',
				ApiBase::PARAM_MIN => 1,
				ApiBase::PARAM_MAX => ApiBase::LIMIT_BIG1,
				ApiBase::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			),
			'continue' => null,
			'dir' => array(
				ApiBase::PARAM_DFLT => 'ascending',
				ApiBase::PARAM_TYPE => array(
					'ascending',
					'descending'
				)
			),
		);
	}

	public function getParamDescription() {
		return array(
			'limit' => 'How many files to return',
			'continue' => 'When more results are available, use this to continue',
			'dir' => 'The direction in which to list',
		);
	}

	public function getResultProperties() {
		return array(
			'' => array(
				'name' => 'string',
				'user' => 'string',
				'timestamp' => 'timestamp'
			)
		);
	}

	public function getDescription() {
		return 'List all files that are duplicates of the given file(s)';
	}

	public function getPossibleErrors() {
		return array_merge( parent::getPossibleErrors(), array(
			array( 'code' => '_badcontinue', 'info' => 'Invalid continue param. You should pass the original value returned by the previous query' ),
		) );
	}

	public function getExamples() {
		return array(
			'api.php?action=query&titles=File:Albert_Einstein_Head.jpg&prop=duplicatefiles',
			'api.php?action=query&generator=allimages&prop=duplicatefiles',
		);
	}

	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/API:Properties#duplicatefiles_.2F_df';
	}

	public function getVersion() {
		return __CLASS__ . ': $Id$';
	}
}
