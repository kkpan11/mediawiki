<?php

namespace MediaWiki\Hook;

use MediaWiki\Page\Article;

/**
 * This is a hook handler interface, see docs/Hooks.md.
 * Use the hook name "ArticleUpdateBeforeRedirect" to register handlers implementing this interface.
 *
 * @stable to implement
 * @ingroup Hooks
 */
interface ArticleUpdateBeforeRedirectHook {
	/**
	 * This hook is called after a page is updated (usually on save), before
	 * the user is redirected back to the page.
	 *
	 * @since 1.35
	 *
	 * @param Article $article
	 * @param string &$sectionanchor Section anchor link (e.g. "#overview" )
	 * @param string &$extraq Extra query parameters which can be added via hooked functions
	 * @return bool|void True or no return value to continue or false to abort
	 */
	public function onArticleUpdateBeforeRedirect( $article, &$sectionanchor,
		&$extraq
	);
}
