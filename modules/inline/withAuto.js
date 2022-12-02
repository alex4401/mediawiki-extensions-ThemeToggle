/* eslint-disable mediawiki/class-doc */


( function () {
    var themeKey = 'skin-theme',
        prefersDark = window.matchMedia( '(prefers-color-scheme: dark)' ),
        htmlNode = document.documentElement,
        linkNode = null,
        currentTheme = null;


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


    function _detect() {
        _setThemeImpl( prefersDark.matches ? 'dark' : 'light' );
    }


    window.MwSkinTheme = {
        getCurrent: function () {
            return currentTheme;
        },


        set: function ( target ) {
            currentTheme = target;

            if ( currentTheme === 'auto' ) {
                // Detect preferred theme by prefers-color-scheme
                _detect();
                htmlNode.classList.add( 'theme-auto' );
                // Attach listener for future changes
                prefersDark.addEventListener( 'change', _detect );
            } else {
                // Apply the theme choice and stop tracking prefers-color-scheme changes
                _setThemeImpl( currentTheme );
                prefersDark.removeEventListener( 'change', _detect );
            }
        }
    };


    MwSkinTheme.set( localStorage.getItem( themeKey ) || RLCONF.wgCurrentTheme || VARS.Default );
}() );
