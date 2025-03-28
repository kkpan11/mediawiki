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

namespace MediaWiki\Block;

use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;

/**
 * @since 1.36
 */
interface BlockUserFactory {
	/**
	 * Create BlockUser
	 *
	 * @param BlockTarget|string|UserIdentity $target Target of the block
	 * @param Authority $performer Performer of the block
	 * @param string $expiry Expiry of the block (timestamp or 'infinity')
	 * @param string $reason Reason of the block
	 * @param array $blockOptions
	 * @param array $blockRestrictions
	 * @param array|null $tags Tags that should be assigned to the log entry
	 *
	 * @return BlockUser
	 */
	public function newBlockUser(
		$target,
		Authority $performer,
		string $expiry,
		string $reason = '',
		array $blockOptions = [],
		array $blockRestrictions = [],
		$tags = []
	): BlockUser;

	/**
	 * Create a BlockUser which updates a specified block
	 *
	 * @since 1.44
	 *
	 * @param DatabaseBlock $block The block to update
	 * @param Authority $performer Performer of the block
	 * @param string $expiry Expiry of the block (timestamp or 'infinity')
	 * @param string $reason Reason of the block
	 * @param array $blockOptions
	 * @param array $blockRestrictions
	 * @param array|null $tags Tags that should be assigned to the log entry
	 * @return BlockUser
	 */
	public function newUpdateBlock(
		DatabaseBlock $block,
		Authority $performer,
		string $expiry,
		string $reason = '',
		array $blockOptions = [],
		array $blockRestrictions = [],
		$tags = []
	): BlockUser;
}
