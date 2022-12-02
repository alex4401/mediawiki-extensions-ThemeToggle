/* eslint-disable mediawiki/class-doc */


( function () {
    var themeKey = 'skin-theme',
        htmlNode = document.documentElement,
        linkNode = null,
        currentTheme = null;
    /* @if ( VARS.WithPCSSupport ) */
    var prefersDark = window.matchMedia( '(prefers-color-scheme: dark)' );
    /* @endif */


    function _setThemeImpl( actualTarget ) {
        try {
            // Apply by changing class
            if ( actualTarget !== null ) {
                // Remove all theme classes
                htmlNode.className = htmlNode.className.replace( / theme-[^\s]+/ig, '' );
                // Add new theme class
                htmlNode.classList.add( 'theme-' + actualTarget );
            }

            if ( VARS.SiteBundledCss.indexOf( actualTarget ) < 0 ) {
                if ( linkNode === null ) {
                    linkNode = document.createElement( 'link' );
                    document.head.appendChild( linkNode );
                }
                linkNode.rel = 'stylesheet';
                linkNode.type = 'text/css';
                linkNode.href = VARS.ResourceLoaderEndpoint + '&modules=ext.theme.' + actualTarget + '&only=styles';
            } else if ( linkNode !== null ) {
                document.head.removeChild( linkNode );
                linkNode = null;
            }
        } catch ( ex ) {
            setTimeout( function () {
                throw ex;
            }, 0 );
        }
    }


    /* @if ( VARS.WithPCSSupport ) */
    function _setFromPCS() {
        _setThemeImpl( prefersDark.matches ? 'dark' : 'light' );
    }
    /* @endif */


    window.MwSkinTheme = {
        getCurrent: function () {
            return currentTheme;
        },


        set: function ( target ) {
            currentTheme = target;

            /* @if ( VARS.WithPCSSupport ) */
            if ( currentTheme === 'auto' ) {
                // Detect preferred theme by prefers-color-scheme
                _setFromPCS();
                htmlNode.classList.add( 'theme-auto' );
                // Attach listener for future changes
                prefersDark.addEventListener( 'change', _setFromPCS );
            } else {
                // Apply the theme choice and stop tracking prefers-color-scheme changes
                _setThemeImpl( currentTheme );
                prefersDark.removeEventListener( 'change', _setFromPCS );
            }
            /* @endif */

            /* @if ( !VARS.WithPCSSupport ) */
            _setThemeImpl( target );
            /* @endif */
        }
    };


    MwSkinTheme.set( localStorage.getItem( themeKey ) || RLCONF.wgCurrentTheme || VARS.Default );
}() );
