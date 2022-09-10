<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

class InlineJsConstants {
    // modules/inline/noAuto.js
    const NO_AUTO = 'var t=null,n=null;window.MwSkinTheme={getCurrent:function(){return n},set:function(e){var l=document.documentElement;n=e;try{null!==n&&(l.className=l.className.replace(/ theme-[^\s]+/gi,""),l.classList.add("theme-"+n)),RLCONF.wgThemeToggleSiteCssBundled.indexOf(n)<0?(null==t&&(t=document.createElement("link"),document.head.appendChild(t)),t.rel="stylesheet",t.type="text/css",t.href=THEMELOAD+"?lang="+l.lang+"&modules=ext.theme."+n+"&only=styles"):null!=t&&(document.head.removeChild(t),t=null)}catch(e){}}},MwSkinTheme.set(localStorage.getItem("skin-theme")||RLCONF.wgThemeToggleDefault);';
    // modules/inline/withAuto.js
    const WITH_AUTO = 'var a,s=window.matchMedia("(prefers-color-scheme: dark)"),c=null,d=null;window.MwSkinTheme={getCurrent:function(){return d},set:function(e){var t=document.documentElement;function n(e){a=e;try{null!==a&&(t.className=t.className.replace(/ theme-[^\s]+/gi,""),t.classList.add("theme-"+a)),RLCONF.wgThemeToggleSiteCssBundled.indexOf(a)<0?(null==c&&(c=document.createElement("link"),document.head.appendChild(c)),c.rel="stylesheet",c.type="text/css",c.href=THEMELOAD+"?lang="+t.lang+"&modules=ext.theme."+a+"&only=styles"):null!=c&&(document.head.removeChild(c),c=null)}catch(e){}}function l(){n(s.matches?"dark":"light")}"auto"===(d=e)?(l(),t.classList.add("theme-auto"),s.addEventListener("change",l)):(n(d),s.removeEventListener("change",l))}},MwSkinTheme.set(localStorage.getItem("skin-theme")||RLCONF.wgThemeToggleDefault);';
}