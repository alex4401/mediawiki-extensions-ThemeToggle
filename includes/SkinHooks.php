<?php
namespace MediaWiki\Extension\Ark\Theming;

use MediaWiki\Hook\BeforePageDisplayHook;
use OutputPage;
use ResourceLoaderContext;
use Skin;


class SkinHooks implements BeforePageDisplayHook {
	/**
	 * Injects the inline theme applying script to the document head
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
        global $wgThemingDefault, $wgThemingSiteCssBundled;
        
		$nonce = $out->getCSP()->getNonce();

		// modules/inline.js
		$script = sprintf(
			'<script%s>SITEDEFAULTTHEME=%s;SITEBUNDLEDTHEMES=%s;%s</script>',
			$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
            json_encode( $wgThemingDefault ),
            json_encode( $wgThemingSiteCssBundled ),
			'window.extApplyThemePreference=function(){var e="skin-theme",t="theme-",n=["dark","light"];function o(){return window.localStorage.getItem(e)}var c=o(),a=window.matchMedia("(prefers-color-scheme: dark)");function r(){try{c=o(),null!==c&&(n.forEach(function(e){document.documentElement.classList.remove(t+e)}),document.documentElement.classList.add(t+c))}catch(e){}}function i(){c=a.matches?"dark":"light",window.localStorage.setItem(e,c),r(),window.localStorage.setItem(e,"auto")}"auto"===c?(i(),a.addEventListener("change",i)):(r(),a.removeEventListener("change",i))},window.extApplyThemePreference();'
        );

		$out->addHeadItem( 'ext.theming.inline', $script );
	}
}
