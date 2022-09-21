/*
 * Dropdown-based theme toggle
*/

var Shared = require( 'ext.themes.baseSwitcher' );
var $wrapper, $label, $list;


function addTheme( themeId ) {
    $( '<li class="mw-list-item" id="p-themes-item-'+themeId+'">' )
        .append( $( '<a href="#">' + mw.msg( 'theme-' + themeId ) + '</a>' ) )
        .on( 'click', function ( event ) {
            event.preventDefault();
            Shared.setUserPreference( themeId );
            $label.text( mw.msg( 'theme-' + themeId ) );
        } )
        .appendTo( $list );
}


function initialise() {
    Shared.trySyncNewAccount();

	$label = $( '<span>' )
		.text( mw.msg( 'theme-' + MwSkinTheme.getCurrent() ) );
    $list = $( '<ul class="vector-menu-content-list menu">' );
    $wrapper = $( '<li id="p-themes" class="mw-list-item vector-menu vector-menu-dropdown vector-menu-dropdown-noicon">' )
        .append( $( '<input type="checkbox" class="vector-menu-checkbox">' )
            .attr( 'title', mw.msg( 'themetoggle-dropdown-switch' ) ) )
        .append( $( '<h3 class="vector-menu-heading"><span class="vector-menu-checkbox-expanded">expanded</span>' +
            '<span class="vector-menu-checkbox-collapsed">collapsed</span></h3></h3>' )
            .prepend( $label ) )
        .append( $( '<div class="vector-menu-content">' )
            .append( $list ) )
        .prependTo( '#p-personal > .vector-menu-content > ul' );
    
    if ( Shared.CONFIG.supportsAuto ) {
        addTheme( 'auto' );
    }

    Shared.CONFIG.themes.forEach( addTheme );
}


Shared.whenCoreLoaded( function () {
    $( initialise );
} );