<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use ManualLogEntry;
use ResourceLoader;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class Hooks implements
    \MediaWiki\Page\Hook\PageDeleteCompleteHook,
    \MediaWiki\Storage\Hook\PageSaveCompleteHook,
    \MediaWiki\Hook\BeforePageDisplayHook,
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook,
    \MediaWiki\Preferences\Hook\GetPreferencesHook,
    \MediaWiki\User\Hook\UserGetDefaultOptionsHook {
    public function onPageSaveComplete( $wikiPage, $userIdentity, $summary, $flags, $revisionRecord, $editResult ): void {
        ThemeDefinitions::get()->handlePageUpdate( $wikiPage->getTitle() );
    }
    
    public function onPageDeleteComplete(  $page, Authority $deleter, string $reason, int $pageID,
        RevisionRecord $deletedRev, ManualLogEntry $logEntry, int $archivedRevisionCount ): void {
        ThemeDefinitions::get()->handlePageUpdate( TitleValue::newFromPage( $page ) );
    }

	/**
	 * Injects the inline theme applying script to the document head
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
        global $wgLoadScript,
            $wgThemeToggleDefault,
            $wgThemeToggleSiteCssBundled,
            $wgThemeToggleEnableForAnonymousUsers,
            $wgThemeToggleSwitcherStyle;

        $isAnonymous = $out->getUser()->isAnon();
        if ( !$wgThemeToggleEnableForAnonymousUsers && $isAnonymous ) {
            return;
        }

        $currentTheme = $wgThemeToggleDefault;
        // Retrieve user's preference
        if ( !$isAnonymous ) {
            $currentTheme = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $out->getUser(), 'skinTheme', $wgThemeToggleDefault );
        }

        // Expose configuration variables
        $out->addJsConfigVars( [
            'wgThemeToggleDefault' => $currentTheme,
            'wgThemeToggleSiteCssBundled' => $wgThemeToggleSiteCssBundled
        ] );

        // Inject the theme applying script into <head> to reduce latency
		$nonce = $out->getCSP()->getNonce();
		$out->addHeadItem( 'ext.themes.inline', sprintf(
			'<script%s>(function(){var THEMELOAD=%s;%s})()</script>',
			$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
            json_encode( $wgLoadScript ),
            // modules/inline.js
			'var themeKey="skin-theme",prefersDark=window.matchMedia("(prefers-color-scheme: dark)"),linkNode=null,currentTheme=null,currentThemeActual=null;window.mwGetCurrentTheme=function(){return currentTheme},window.mwChangeDisplayedTheme=function(e){function n(e){var n=document.documentElement;currentThemeActual=e;try{null!==currentThemeActual&&(n.className=n.className.replace(/ theme-[^\s]+/gi,""),n.classList.add("theme-"+currentThemeActual)),RLCONF.wgThemeToggleSiteCssBundled.indexOf(currentThemeActual)<0?(null==linkNode&&(linkNode=document.createElement("link"),document.head.appendChild(linkNode)),linkNode.rel="stylesheet",linkNode.type="text/css",linkNode.href=THEMELOAD+"?lang="+n.lang+"&modules=ext.theme."+currentThemeActual+"&only=styles"):null!=linkNode&&(document.head.removeChild(linkNode),linkNode=null)}catch(e){}}function t(){n(prefersDark.matches?"dark":"light")}"auto"===(currentTheme=e)?(t(),prefersDark.addEventListener("change",t)):(n(currentTheme),prefersDark.removeEventListener("change",t))},mwChangeDisplayedTheme(localStorage.getItem(themeKey)||RLCONF.wgThemeToggleDefault)'
        ) );

        // Inject the theme switcher as a ResourceLoader module
        switch ( $wgThemeToggleSwitcherStyle ) {
            case 'simple':
                $out->addModules( [
                    'ext.themes.simpleSwitcher'
                ] );
                break;
        }
	}

	public function onUserGetDefaultOptions( &$defaultOptions ) {
        global $wgThemeToggleDefault;
		$defaultOptions['skinTheme'] = $wgThemeToggleDefault;
	}

	public function onGetPreferences( $user, &$preferences ) {
        $themeOptions = [
            wfMessage( 'theme-auto-preference-description' )->text() => 'auto'
        ];

        foreach ( ThemeDefinitions::get()->getIds() as $theme ) {
            $themeOptions[ wfMessage( "theme-$theme" )->text() ] = $theme;
        }

        $preferences['skinTheme'] = [
            'label-message' => 'themetoggle-user-preference-label',
            'type' => 'select',
            'options' => $themeOptions,
            'section' => 'rendering/skin/skin-prefs'
        ];
	}

    private function registerThemeModule( ResourceLoader $resourceLoader, string $id ): void {
        $resourceLoader->register( 'ext.theme.' . $id, [
			'class' => WikiThemeResourceLoaderModule::class,
			'id' => $id
		] );
    }
    
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
        /* This is a stub, ideally there'd be a definitions page unless there's some more clever way */
        global $wgThemeToggleSiteCssBundled;
        
        foreach ( ThemeDefinitions::get()->getIds() as $theme ) {
            if ( !in_array( $theme, $wgThemeToggleSiteCssBundled ) ) {
                $this->registerThemeModule( $resourceLoader, $theme );
            }
        }
	}
}
