<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use ResourceLoader;

class Hooks implements
    \MediaWiki\Hook\BeforePageDisplayHook,
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook,
    \MediaWiki\Preferences\Hook\GetPreferencesHook,
    \MediaWiki\User\Hook\UserGetDefaultOptionsHook {
	/**
	 * Injects the inline theme applying script to the document head
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
        global $wgLoadScript,
            $wgThemeToggleDefault,
            $wgThemeToggleSiteCssBundled,
            $wgThemeToggleEnableForAnonymousUsers,
            $wgThemeToggleSwitcherStyle;

        if ( !$wgThemeToggleEnableForAnonymousUsers && $out->getUser()->isAnon() ) {
            return;
        }

        // Inject the theme applying script into <head> to reduce latency
		$nonce = $out->getCSP()->getNonce();
		$out->addHeadItem( 'ext.themes.inline', sprintf(
			'<script%s>(function(){var THEMELOAD=%s,THEMESITEDEFAULT=%s,THEMESITEBUNDLED=%s;%s})()</script>',
			$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
            json_encode( $wgLoadScript ),
            json_encode( $wgThemeToggleDefault ),
            json_encode( $wgThemeToggleSiteCssBundled ),
            // modules/inline.js
			'var themeKey="skin-theme",prefersDark=window.matchMedia("(prefers-color-scheme: dark)"),linkNode=null;window.mwGetCurrentTheme=function(){return window.localStorage.getItem(themeKey)||THEMESITEDEFAULT},window.mwApplyThemePreference=function(){function a(){try{c=mwGetCurrentTheme(),null!==c&&(d.className=d.className.replace(/ theme-[^\s]+/ig,""),d.classList.add("theme-"+c)),0>THEMESITEBUNDLED.indexOf(c)?(null==linkNode&&(linkNode=document.createElement("link"),document.head.appendChild(linkNode)),linkNode.rel="stylesheet",linkNode.type="text/css",linkNode.href=THEMELOAD+"?lang="+d.lang+"&modules=ext.theme."+c+"&only=styles"):null!=linkNode&&(document.head.removeChild(linkNode),linkNode=null)}catch(a){}}function b(){c=prefersDark.matches?"dark":"light",window.localStorage.setItem(themeKey,c),a(),window.localStorage.setItem(themeKey,"auto")}var c=mwGetCurrentTheme(),d=document.documentElement;"auto"===c?(b(),prefersDark.addEventListener("change",b)):(a(),prefersDark.removeEventListener("change",b))},window.mwApplyThemePreference()'
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
		$defaultOptions['skinTheme'] = 'auto';
	}

	public function onGetPreferences( $user, &$preferences ) {
        $preferences['skinTheme'] = [
            'label-message' => 'themetoggle-user-preference-label',
            'type' => 'select',
            'options' => [
                'auto' => 'auto',
                'light1' => 'light2'
            ],
            'section' => 'rendering/skin/skin-prefs',
            'help-message' => 'prefs-help-variant',
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
        
        if ( !in_array( 'light', $wgThemeToggleSiteCssBundled ) ) {
            $this->registerThemeModule( $resourceLoader, 'light' );
        }
        
        if ( !in_array( 'dark', $wgThemeToggleSiteCssBundled ) ) {
            $this->registerThemeModule( $resourceLoader, 'dark' );
        }
	}
}
