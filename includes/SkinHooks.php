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
			'window.extApplyThemePreference=function(){var e="skin-theme";function t(){return window.localStorage.getItem(e)||SITEDEFAULTTHEME}var n=t(),l=window.matchMedia("(prefers-color-scheme: dark)"),a=document.documentElement,o=null;function c(){try{null!==(n=t())&&(a.className=a.className.replace(/ theme-[^\s]+/gi,""),a.classList.add("theme-"+n)),SITEBUNDLEDTHEMES.indexOf(n)<0?(null==o&&(o=document.createElement("link"),document.head.appendChild(o)),o.rel="stylesheet",o.type="text/css",o.href="/load.php?lang="+a.lang+"&modules=ext.theming."+n+"&only=styles"):null!=o&&document.head.removeChild(o)}catch(e){}}function d(){n=l.matches?"dark":"light",window.localStorage.setItem(e,n),c(),window.localStorage.setItem(e,"auto")}"auto"===n?(d(),l.addEventListener("change",d)):(c(),l.removeEventListener("change",d))},window.extApplyThemePreference();'
        );

		$out->addHeadItem( 'ext.theming.inline', $script );
	}
}
