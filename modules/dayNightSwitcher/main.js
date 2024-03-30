/*
 * Simple state-cycling theme toggle
 *
 * This is really only ideal for two states. System/auto is ignored by default.
 *
 * Originally written for the official ARK Wiki https://ark.wiki.gg, later with contributions added back by:
 * - https://undermine.wiki.gg
 * - https://temtem.wiki.gg
*/

var Shared = require( 'ext.themes.jsapi' ),
    themes = Shared.getAvailableThemes();
var $toggle, $container, $mobileLabel;


function updateTitle() {
    // eslint-disable-next-line mediawiki/msg-doc
    var themeName = mw.msg( 'theme-' + MwSkinTheme.getCurrent() );

    var msg = mw.msg( 'themetoggle-simple-switch', themeName );
    $toggle.setAttribute( 'title', msg );

    $mobileLabel.innerText = mw.msg( 'themetoggle-simple-switch-short', themeName );
}


function cycleTheme() {
    var nextIndex = themes.indexOf( MwSkinTheme.getCurrent() ) + 1;
    if ( nextIndex >= themes.length ) {
        nextIndex = 0;
    }

    Shared.setUserPreference( themes[ nextIndex ] );
    updateTitle();
}


function initialise() {
    Shared.prepare();

    $container = document.createElement( 'li' );
    $container.id = 'pt-themes';
    $container.className = 'mw-list-item';

    $toggle = document.createElement( 'span' );
    $toggle.className = 'ext-themetoggle-simple-icon';
    $toggle.addEventListener( 'mousedown', function ( event ) {
        if ( event.which === 1 || event.button === 0 ) {
            cycleTheme();
        }
    } );
    $container.appendChild( $toggle );

    $mobileLabel = document.createElement( 'span' );
    $mobileLabel.className = 'ext-themetoggle-simple-label';
    $container.appendChild( $mobileLabel );

    Shared.getSwitcherPortlet().prepend( $container );

    updateTitle();
}


Shared.runSwitcherInitialiser( initialise );
