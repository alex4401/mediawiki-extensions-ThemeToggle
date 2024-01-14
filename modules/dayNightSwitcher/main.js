/*
 * Simple state-cycling theme toggle
 *
 * This is really only ideal for two states. System/auto is ignored by default.
 *
 * Originally written for the official ARK Wiki https://ark.wiki.gg, later with contributions added back by:
 * - https://undermine.wiki.gg
 * - https://temtem.wiki.gg
*/

var Shared = require( 'ext.themes.jsapi' );
var $toggle, $container;


function updateTitle() {
    // eslint-disable-next-line mediawiki/msg-doc
    var msg = mw.msg( 'themetoggle-simple-switch', mw.msg( 'theme-' + MwSkinTheme.getCurrent() ) );
    $toggle.setAttribute( 'title', msg );
}


function cycleTheme() {
    var nextIndex = Shared.CONFIG.themes.indexOf( MwSkinTheme.getCurrent() ) + 1;
    if ( nextIndex >= Shared.CONFIG.themes.length ) {
        nextIndex = 0;
    }

    Shared.setUserPreference( Shared.CONFIG.themes[ nextIndex ] );
    updateTitle();
}


function initialise() {
    Shared.prepare();

    $container = document.createElement( 'li' );
    $container.id = 'p-themes';
    $container.className = 'mw-list-item';

    $toggle = document.createElement( 'span' );
    $toggle.addEventListener( 'mousedown', function ( event ) {
        if ( event.which === 1 || event.button === 0 ) {
            cycleTheme();
        }
    } );
    $container.appendChild( $toggle );

    document.querySelector( '#pt-userpage' ).prepend( $container );

    updateTitle();
}


Shared.runSwitcherInitialiser( initialise );
