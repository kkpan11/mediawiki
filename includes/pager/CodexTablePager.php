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

namespace MediaWiki\Pager;

use InvalidArgumentException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Parser\ParserOutput;

/**
 * Codex Table display of sortable data with pagination.
 *
 * @stable to extend
 * @ingroup Pager
 */
abstract class CodexTablePager extends TablePager {
	protected string $mCaption;

	/**
	 * @stable to call
	 *
	 * @param string $caption Text for visually-hidden caption element
	 * @param ?IContextSource $context
	 * @param ?LinkRenderer $linkRenderer
	 */
	public function __construct(
		string $caption,
		?IContextSource $context = null,
		?LinkRenderer $linkRenderer = null
	) {
		if ( trim( $caption ) === '' ) {
			throw new InvalidArgumentException( 'Table caption is required.' );
		}
		// TODO T366847: add sort text to caption when data is sortable.
		$this->mCaption = $caption;

		parent::__construct( $context, $linkRenderer );
	}

	/**
	 * Get the entire Codex table markup, including the wrapper element, pagers, table wrapper
	 * (which enables horizontal scroll), and the table element.
	 *
	 * @since 1.44
	 */
	public function getFullOutput(): ParserOutput {
		// Pagers.
		$navigation = $this->getNavigationBar();
		// `<table>` element and its contents.
		$body = parent::getBody();

		$pout = new ParserOutput();
		$pout->setRawText(
			Html::openElement( 'div', [ 'class' => 'cdx-table' ] ) . "\n" .
			// In the future, a visible caption + header content could go here.
			$navigation . "\n" .
			Html::openElement( 'div', [ 'class' => 'cdx-table__table-wrapper' ] ) . "\n" .
			$body . "\n" .
			Html::closeElement( 'div' ) . "\n" .
			// In the future, footer content could go here.
			$navigation . "\n" .
			Html::closeElement( 'div' )
		);
		$pout->addModuleStyles( $this->getModuleStyles() );
		return $pout;
	}

	/**
	 * Generate the `<thead>` element.
	 *
	 * This creates a thead with a single tr and includes sort buttons if applicable. To customize
	 * the thead layout, override this method.
	 *
	 * @stable to override
	 */
	protected function getThead(): string {
		$theadContent = '';
		$fields = $this->getFieldNames();

		// Make table header
		// TODO T366847: Add sort buttons.
		foreach ( $fields as $field => $name ) {
			if ( $name === '' ) {
				// th with no label (not advised).
				$theadContent .= Html::rawElement( 'th', $this->getCellAttrs( $field, $name ), "\u{00A0}" ) . "\n";
			} else {
				$theadContent .= Html::element( 'th', $this->getCellAttrs( $field, $name ), $name ) . "\n";
			}
		}
		return Html::rawElement( 'thead', [], Html::rawElement( 'tr', [], "\n" . $theadContent . "\n" ) );
	}

	/**
	 * Get the opening table tag through the opening tbody tag.
	 *
	 * This method should generally not be overridden: use getThead() to create a custom `<thead>`
	 * and getTableClass to set additional classes on the `<table>` element.
	 *
	 * @stable to override
	 */
	protected function getStartBody(): string {
		$ret = Html::openElement( 'table', [
			'class' => $this->getTableClass() ]
		);
		$ret .= Html::rawElement( 'caption', [], $this->mCaption );
		$ret .= $this->getThead();
		$ret .= Html::openElement( 'tbody' ) . "\n";

		return $ret;
	}

	/**
	 * Override to add a `<tfoot>` element.
	 *
	 * @stable to override
	 */
	protected function getTfoot(): string {
		return '';
	}

	/**
	 * Get the closing tbody tag through the closing table tag.
	 *
	 * @stable to override
	 */
	protected function getEndBody(): string {
		return "</tbody>" . $this->getTfoot() . "</table>\n";
	}

	/**
	 * Get markup for the "no results" UI. This is placed inside the tbody tag.
	 */
	protected function getEmptyBody(): string {
		$colspan = count( $this->getFieldNames() );
		$msgEmpty = $this->msg( 'table_pager_empty' )->text();
		return Html::rawElement( 'tr', [ 'class' => 'cdx-table__table__empty-state' ],
			Html::element(
				'td',
				[ 'class' => 'cdx-table__table__empty-state-content', 'colspan' => $colspan ],
				$msgEmpty )
			);
	}

	/**
	 * Add alignment per column.
	 *
	 * @param string $field The column
	 * @return string start (default), center, end, or number (always to the right)
	 */
	protected function getCellAlignment( string $field ): string {
		return 'start';
	}

	/**
	 * Add extra attributes to be applied to the given cell.
	 *
	 * @stable to override
	 *
	 * @param string $field The column
	 * @param string $value The cell contents
	 * @return array Array of attr => value
	 */
	protected function getCellAttrs( $field, $value ): array {
		return [
			'class' => [
				'cdx-table-pager__col--' . $field,
				'cdx-table__table__cell--align-' . $this->getCellAlignment( $field )
			]
		];
	}

	/**
	 * Class for the `<table>` element.
	 *
	 * @stable to override
	 */
	protected function getTableClass(): string {
		return 'cdx-table__table';
	}

	// TODO T366847: override getSortHeaderClass

	// TODO T366849:
	// - Override getNavigationBar
	// - Override getNavClass
	// - Figure out what to do with getLimitSelect, getLimitSelectList, getHiddenFields,
	//   getLimitForm, getLimitDropdown

	/**
	 * @inheritDoc
	 */
	public function getModuleStyles(): array {
		return [ 'mediawiki.pager.codex.styles' ];
	}
}

/** @deprecated class alias since 1.41 */
class_alias( CodexTablePager::class, 'CodexTablePager' );
