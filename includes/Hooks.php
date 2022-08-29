<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use ResourceLoader;

class Hooks implements
    \MediaWiki\Hook\BeforePageDisplayHook,
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook {
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
			'var themeKey="skin-theme";window.mwGetCurrentTheme=function(){return window.localStorage.getItem(themeKey)||THEMESITEDEFAULT},window.mwApplyThemePreference=function(){var e=mwGetCurrentTheme(),t=window.matchMedia("(prefers-color-scheme: dark)"),n=document.documentElement,l=null;function m(){try{null!==(e=mwGetCurrentTheme())&&(n.className=n.className.replace(/ theme-[^\s]+/gi,""),n.classList.add("theme-"+e)),THEMESITEBUNDLED.indexOf(e)<0?(null==l&&(l=document.createElement("link"),document.head.appendChild(l)),l.rel="stylesheet",l.type="text/css",l.href=THEMELOAD+"?lang="+n.lang+"&modules=ext.theme."+e+"&only=styles"):null!=l&&(document.head.removeChild(l),l=null)}catch(e){}}function a(){e=t.matches?"dark":"light",window.localStorage.setItem(themeKey,e),m(),window.localStorage.setItem(themeKey,"auto")}"auto"===e?(a(),t.addEventListener("change",a)):(m(),t.removeEventListener("change",a))},window.mwApplyThemePreference()'
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
