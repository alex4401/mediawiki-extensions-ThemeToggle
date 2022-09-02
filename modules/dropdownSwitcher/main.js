/*
 * Dropdown-based theme toggle (basically simple switcher but adapted to more themes)
*/

var Shared = require( 'ext.themes.baseSwitcher' );
var $wrapper, $toggle;


function cycleTheme() {
    var nextIndex = Shared.CONFIG.themes.indexOf( mwGetCurrentTheme() ) + 1;
    if ( nextIndex >= Shared.CONFIG.themes.length ) {
        nextIndex = 0;
    }

    Shared.setUserPreference( Shared.CONFIG.themes[nextIndex] );
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