<?php

namespace MediaWiki\Page\Hook;

use MediaWiki\Page\WikiPage;
use MediaWiki\Title\Title;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "WikiPageFactory" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface WikiPageFactoryHook {
	/**
	 * Use this hook to override WikiPage class used for a title.
	 *
	 * @since 1.35
	 *
	 * @param Title $title Title of the page
	 * @param WikiPage|null &$page Variable to set the created WikiPage to
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onWikiPageFactory( $title, &$page );
}
