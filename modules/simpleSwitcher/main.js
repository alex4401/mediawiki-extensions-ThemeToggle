/*
 * Simple state-cycling theme toggle
 *
 * This is really only ideal for two states. System/auto is ignored by default.
 *
 * Originally written for the official ARK Wiki https://ark.wiki.gg, later with contributions added back by:
 * - https://undermine.wiki.gg
 * - https://temtem.wiki.gg
*/

var Shared = require( 'ext.themes.baseSwitcher' );
var $wrapper, $toggle;


function cycleTheme() {
    var nextIndex = Shared.CONFIG.themes.indexOf( MwSkinTheme.getCurrent() ) + 1;
    if ( nextIndex >= Shared.CONFIG.themes.length ) {
        nextIndex = 0;
    }

    Shared.setUserPreference( Shared.CONFIG.themes[nextIndex] );
}


function initialise() {
    Shared.trySyncNewAccount();

	$toggle = $( '<span>' )
		.attr( 'title', mw.msg( 'themetoggle-simple-switch' ) )
		.on( 'mousedown', cycleTheme );
    $wrapper = $( '<li id="p-themes" class="mw-list-item">' )
        .append( $toggle )
        .prependTo( $( '#p-personal > .vector-menu-content > ul' ) );
}


$( initialise );