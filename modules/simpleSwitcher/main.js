/*
 * Simple state-cycling theme toggle
 *
 * Originally written for the official ARK Wiki https://ark.wiki.gg, later with contributions added back by:
 * - https://undermine.wiki.gg
 * - https://temtem.wiki.gg
*/

var Shared = require( '../shared.js' );
var Config = {
    themes: [ 'dark', 'light' ]
};//require( './config.json' );
var $wrapper, $toggle;


function cycleTheme() {
    var nextIndex = Config.themes.indexOf( mwGetCurrentTheme() ) + 1;
    if ( nextIndex >= Config.themes.length ) {
        nextIndex = 0;
    }

    Shared.setUserPreference( Config.themes[nextIndex] );
}


function initialise() {
    Shared.trySyncNewAccount();

	$toggle = $('<span>')
		.attr( 'title', mw.msg( 'themetoggle-simple-switch' ) )
		.on( 'mousedown', cycleTheme );
    $wrapper = $( '<li id="p-themes" class="mw-list-item">' )
        .append( $toggle )
        .prependTo( $( '#p-personal > .vector-menu-content > ul' ) );
}


$( initialise );