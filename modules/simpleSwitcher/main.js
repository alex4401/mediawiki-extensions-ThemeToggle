/*
 * Simple state-cycling theme toggle
 *
 * Originally written for the official ARK Wiki https://ark.wiki.gg, later with contributions added back by:
 * - https://undermine.wiki.gg
 * - https://temtem.wiki.gg
*/

var Config = {
    themes: [ 'dark', 'light' ]
};//require( './config.json' );
var $wrapper, $toggle;


function cycleTheme() {
    var nextIndex = Config.themes.indexOf( mwGetCurrentTheme() ) + 1;
    if ( nextIndex >= Config.themes.length ) {
        nextIndex = 0;
    }

    localStorage.setItem( 'skin-theme', Config.themes[nextIndex] );
    mwApplyThemePreference();
}


function initialise() {
	$toggle = $('<span>')
		.attr( 'title', mw.msg( 'themetoggle-simple-switch' ) )
		.on( 'mousedown', cycleTheme );
    $wrapper = $( '<li id="p-themes" class="mw-list-item">' )
        .append( $toggle )
        .prependTo( $( '#p-personal > .vector-menu-content > ul' ) );
}


$( initialise );