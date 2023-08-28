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
var $toggle;


function updateTitle() {
    // eslint-disable-next-line mediawiki/msg-doc
    $toggle.attr( 'title', mw.msg( 'themetoggle-simple-switch', mw.msg( 'theme-' + MwSkinTheme.getCurrent() ) ) );
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

    $toggle = $( '<span>' )
        .on( 'mousedown', function ( event ) {
            if ( event.which === 1 || event.button === 0 ) {
                cycleTheme();
            }
        } );
    $( '<li id="p-themes" class="mw-list-item">' )
        .append( $toggle )
        // eslint-disable-next-line no-jquery/no-global-selector
        .prependTo( $( '#p-personal > .vector-menu-content > ul' ) );

    updateTitle();
}


Shared.runSwitcherInitialiser( initialise );
