<?php
namespace MediaWiki\Extension\ThemeToggle\Hooks;

use Html;
use Title;
use MediaWiki\Extension\ThemeToggle\ThemeAndFeatureRegistry;
use MediaWiki\MediaWikiServices;

class ThemeLinksHooks implements
	\MediaWiki\Hook\OutputPageParserOutputHook
{
	/** @var ThemeAndFeatureRegistry */
	private ThemeAndFeatureRegistry $registry;

	public function __construct( ThemeAndFeatureRegistry $registry ) {
		$this->registry = $registry;
	}

	public function onOutputPageParserOutput( $out, $parserOutput ): void {
		$title = $out->getTitle();
		if ( $title->getNamespace() !== NS_MEDIAWIKI || $title->getText() !== ThemeAndFeatureRegistry::TITLE ) {
			return;
		}

		$themes = $this->registry->getAll();
		$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

		$hasBundledThemes = false;

		$html = Html::element('p', [], wfMessage('themetoggle-css-pages-list-intro') );
		$html .= Html::openElement('ul');
		foreach ( $themes as $theme ) {
			if ( $theme->isBundled() ) {
				$hasBundledThemes = true;
			} else {
				$html .= Html::rawElement( 'li', [],
					$linkRenderer->makeLink( Title::newFromText( $theme->getCssPageName() ) ) );
			}
		}
		$html .= Html::closeElement('ul');

		if ( $hasBundledThemes ) {
			$html .= Html::element( 'p', [], wfMessage( 'themetoggle-bundled-list-intro' ) );
			$html .= Html::openElement( 'ul' );
			foreach ( $themes as $theme ) {
				if ( $theme->isBundled() ) {
					$html .= Html::element( 'li', [], $theme->getId() );
				}
			}
			$html .= Html::closeElement( 'ul' );
		}

		$html .= Html::element( 'hr' );

		$parserOutput->setText( $html . $parserOutput->getText() );
	}
}
