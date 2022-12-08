/*
 * Dropdown-based theme toggle
*/

var Shared = require( 'ext.themes.jsapi' );
var $container, $label, $list;


function addTheme( themeId ) {
    $( '<li class="mw-list-item" id="p-themes-item-' + themeId + '">' )
        // eslint-disable-next-line mediawiki/msg-doc
        .append( $( '<a href="#">' + mw.msg( 'theme-' + themeId ) + '</a>' ) )
        .on( 'click', function ( event ) {
            event.preventDefault();
            Shared.setUserPreference( themeId );
            // eslint-disable-next-line mediawiki/msg-doc
            $label.text( mw.msg( 'theme-' + themeId ) );
        } )
        .appendTo( $list );
}


function initialise() {
    Shared.prepare();

    $label = $( '<span class="vector-menu-heading-label">' )
        // eslint-disable-next-line mediawiki/msg-doc
        .text( mw.msg( 'theme-' + MwSkinTheme.getCurrent() ) );
    $list = $( '<ul class="vector-menu-content-list menu">' );
    $container = $( '<li id="p-themes" class="mw-list-item vector-menu vector-menu-dropdown vector-menu-dropdown-noicon">' )
        .append( $( '<input id="p-themes-checkbox" type="checkbox" class="vector-menu-checkbox" role="button" '
            + 'aria-haspopup="true" aria-labelledby="p-themes-label">' )
            .attr( 'title', mw.msg( 'themetoggle-dropdown-switch' ) ) )
        .append( $( '<label id="p-themes-label" for="p-themes-checkbox" class="vector-menu-heading">' )
            .prepend( $label ) )
        .append( $( '<div class="vector-menu-content">' )
            .append( $list ) )
        .prependTo( '#p-personal > .vector-menu-content > ul' );

    if ( Shared.CONFIG.supportsAuto ) {
        addTheme( 'auto' );
    }
    Shared.CONFIG.themes.forEach( addTheme );

    mw.hook( 'ext.themes.dropdownSwitcherReady' ).fire( $container );
}


Shared.whenCoreLoaded( function () {
    $( initialise );
} );
