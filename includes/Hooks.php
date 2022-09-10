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

        // Expose configuration variables
        $out->addJsConfigVars( [
            'wgThemeToggleDefault' => $wgThemeToggleDefault,
            'wgThemeToggleSiteCssBundled' => $wgThemeToggleSiteCssBundled
        ] );

        // Expose user's account-wide preference
        if ( !$isAnonymous ) {
            $prefValue = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $out->getUser(), 'skinTheme', null );
            if ( $prefValue !== null ) {
                $out->addJsConfigVars( [
                    'wgThemeToggleCurrent' => $prefValue
                ] );
            }
        }

        // Inject the theme applying script into <head> to reduce latency
		$nonce = $out->getCSP()->getNonce();
		$out->addHeadItem( 'ext.themes.inline', sprintf(
			'<script%s>(function(){var THEMELOAD=%s;%s})()</script>',
			$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
            json_encode( $wgLoadScript ),
            // modules/inline.js
			'var themeKey="skin-theme",prefersDark=window.matchMedia("(prefers-color-scheme: dark)"),linkNode=null;window.mwGetCurrentTheme=function(){return window.localStorage.getItem(themeKey)||RLCONF.wgThemeToggleCurrent||RLCONF.wgThemeToggleDefault},window.mwApplyThemePreference=function(){var e=mwGetCurrentTheme(),n=document.documentElement;function t(){try{null!==(e=mwGetCurrentTheme())&&(n.className=n.className.replace(/ theme-[^\s]+/gi,""),n.classList.add("theme-"+e)),RLCONF.wgThemeToggleSiteCssBundled.indexOf(e)<0?(null==linkNode&&(linkNode=document.createElement("link"),document.head.appendChild(linkNode)),linkNode.rel="stylesheet",linkNode.type="text/css",linkNode.href=THEMELOAD+"?lang="+n.lang+"&modules=ext.theme."+e+"&only=styles"):null!=linkNode&&(document.head.removeChild(linkNode),linkNode=null)}catch(e){}}function l(){e=prefersDark.matches?"dark":"light",window.localStorage.setItem(themeKey,e),t(),window.localStorage.setItem(themeKey,"auto")}"auto"===e?(l(),prefersDark.addEventListener("change",l)):(t(),prefersDark.removeEventListener("change",l))},window.mwApplyThemePreference()'
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
