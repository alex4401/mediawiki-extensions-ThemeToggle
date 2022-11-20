( function () {
    var themeKey = 'skin-theme',
        linkNode = null,
        currentTheme = null,
        rc = THEMELOAD;


    window.MwSkinTheme = {
        getCurrent: function () {
            return currentTheme;
        },

        set: function ( target ) {
            var htmlNode = document.documentElement;
        
            currentTheme = target;


            function applyInternal() {
                try {
                    // Apply by changing class
                    if ( currentTheme !== null ) {
                        // Remove all theme classes
                        htmlNode.className = htmlNode.className.replace( / theme-[^\s]+/ig, '' );
                        // Add new theme class
                        htmlNode.classList.add( 'theme-' + currentTheme );
                    }
                
                    if ( RLCONF.wgThemeToggleSiteCssBundled.indexOf( currentTheme ) < 0 ) {
                        if ( linkNode == null ) {
                            linkNode = document.createElement( 'link' );
                            document.head.appendChild( linkNode );
                        }
                        linkNode.rel = 'stylesheet';
                        linkNode.type = 'text/css';
                        linkNode.href = rc+'&modules=ext.theme.'+currentTheme+'&only=styles';
                    } else if ( linkNode != null ) {
                        document.head.removeChild( linkNode );
                        linkNode = null;
                    }
                } catch ( ex ) {
                    setTimeout( function () {
                        throw ex;
                    }, 0 );
                }
            }
        
        
            applyInternal();
        }
    };


    MwSkinTheme.set( localStorage.getItem( themeKey ) || RLCONF.wgThemeToggleDefault );
} )();