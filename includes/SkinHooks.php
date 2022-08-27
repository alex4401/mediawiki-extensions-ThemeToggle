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
        global $wgLoadScript, $wgThemingDefault, $wgThemingSiteCssBundled;

		$nonce = $out->getCSP()->getNonce();

		// modules/inline.js
		$script = sprintf(
			'<script%s>(function(){var THEMELOAD=%s,THEMESITEDEFAULT=%s,THEMESITEBUNDLED=%s;%s})()</script>',
			$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
            json_encode( $wgLoadScript ),
            json_encode( $wgThemingDefault ),
            json_encode( $wgThemingSiteCssBundled ),
			'window.extApplyThemePreference=function(){var e="skin-theme";function t(){return window.localStorage.getItem(e)||THEMESITEDEFAULT}var n=t(),l=window.matchMedia("(prefers-color-scheme: dark)"),a=document.documentElement,o=null;function c(){try{null!==(n=t())&&(a.className=a.className.replace(/ theme-[^\s]+/gi,""),a.classList.add("theme-"+n)),THEMESITEBUNDLED.indexOf(n)<0?(null==o&&(o=document.createElement("link"),document.head.appendChild(o)),o.rel="stylesheet",o.type="text/css",o.href=THEMELOAD+"?lang="+a.lang+"&modules=ext.theming."+n+"&only=styles"):null!=o&&document.head.removeChild(o)}catch(e){}}function d(){n=l.matches?"dark":"light",window.localStorage.setItem(e,n),c(),window.localStorage.setItem(e,"auto")}"auto"===n?(d(),l.addEventListener("change",d)):(c(),l.removeEventListener("change",d))},window.extApplyThemePreference()'
        );

		$out->addHeadItem( 'ext.theming.inline', $script );
	}
}
