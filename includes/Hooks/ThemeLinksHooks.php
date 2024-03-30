<?php

namespace MediaWiki\Extension\ThemeToggle\Hooks;

use Html;
use Title;
use TitleValue;
use MediaWiki\Extension\ThemeToggle\ThemeAndFeatureRegistry;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\MediaWikiServices;

class ThemeLinksHooks implements \MediaWiki\Hook\OutputPageParserOutputHook {
	/** @var LinkRenderer */
	private LinkRenderer $linkRenderer;

	/** @var ThemeAndFeatureRegistry */
	private ThemeAndFeatureRegistry $registry;

	public function __construct(
		LinkRenderer $linkRenderer,
		ThemeAndFeatureRegistry $registry
	) {
		$this->linkRenderer = $linkRenderer;
		$this->registry = $registry;
	}

	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		$title = $out->getTitle();
		if ( $title->getNamespace() !== NS_MEDIAWIKI || $title->getText() !== ThemeAndFeatureRegistry::TITLE ) {
			return;
		}

		$themes = $this->registry->getAll();

		$hasBundledThemes = false;

		$html = Html::element( 'p', [], wfMessage( 'themetoggle-css-pages-list-intro' ) );
		$html .= Html::openElement( 'ul' );
		foreach ( $themes as $theme ) {
			if ( $theme->isBundled() ) {
				$hasBundledThemes = true;
			} else {
				$html .= Html::rawElement( 'li', [], $this->makeThemeListItem( $theme ) );
			}
		}
		$html .= Html::closeElement( 'ul' );

		if ( $hasBundledThemes ) {
			$html .= Html::element( 'p', [], wfMessage( 'themetoggle-bundled-list-intro' ) );
			$html .= Html::openElement( 'ul' );
			foreach ( $themes as $theme ) {
				if ( $theme->isBundled() ) {
					$html .= Html::rawElement( 'li', [], $this->makeThemeListItem( $theme ) );
				}
			}
			$html .= Html::closeElement( 'ul' );
		}

		$html .= Html::element( 'hr' );

		$parserOutput->setText( $html . $parserOutput->getText() );
	}

	private function makeThemeListItem( $theme ): string {
		$html = null;

		if ( $theme->isBundled() ) {
			$html = wfEscapeWikiText( $theme->getId() );
		} else {
			$html = $this->linkRenderer->makeLink( Title::newFromText( $theme->getCssPageName() ) );
		}
		
		$html .= ' (' . wfMessage( 'themetoggle-list-display-as' ) . ' ';
		$html .= $this->linkRenderer->makeLink(
			new TitleValue( NS_MEDIAWIKI, $theme->getMessageId() ),
			wfMessage( $theme->getMessageId() )
		);
		$html .= ')';
		return $html;
	}

}
