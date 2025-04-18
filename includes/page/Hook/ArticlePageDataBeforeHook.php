<?php

namespace MediaWiki\Page\Hook;

use MediaWiki\Page\WikiPage;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "ArticlePageDataBefore" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface ArticlePageDataBeforeHook {
	/**
	 * This hook is called before loading data of an article from the database.
	 *
	 * @since 1.35
	 *
	 * @param WikiPage $wikiPage WikiPage whose data will be loaded
	 * @param array &$fields Fields to load from the database
	 * @param array &$tables Tables to load from the database
	 * @param array &$joinConds Join conditions to load from the database
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onArticlePageDataBefore( $wikiPage, &$fields, &$tables,
		&$joinConds
	);
}
