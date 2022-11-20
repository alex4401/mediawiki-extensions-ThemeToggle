( function () {
    var themeKey = 'skin-theme',
        prefersDark = window.matchMedia( '(prefers-color-scheme: dark)' ),
        linkNode = null,
        currentTheme = null,
        currentThemeActual = null,
        rc = THEMELOAD;


    window.MwSkinTheme = {
        getCurrent: function () {
            return currentTheme;
        },

        set: function ( target ) {
            var htmlNode = document.documentElement;

            currentTheme = target;


        	function applyInternal( target ) {
                currentThemeActual = target;

        		try {
        			// Apply by changing class
        			if ( currentThemeActual !== null ) {
        				// Remove all theme classes
                        htmlNode.className = htmlNode.className.replace( / theme-[^\s]+/ig, '' );
                        // Add new theme class
        				htmlNode.classList.add( 'theme-' + currentThemeActual );
        			}

                    if ( RLCONF.wgThemeToggleSiteCssBundled.indexOf( currentThemeActual ) < 0 ) {
                        if ( linkNode == null ) {
                            linkNode = document.createElement( 'link' );
                            document.head.appendChild( linkNode );
                        }
                        linkNode.rel = 'stylesheet';
                        linkNode.type = 'text/css';
                        linkNode.href = rc+'&modules=ext.theme.'+currentThemeActual+'&only=styles';
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


            function detectInternal() {
        		applyInternal( prefersDark.matches ? 'dark' : 'light' );
            }


        	if ( currentTheme === 'auto' ) {
                // Detect preferred theme by prefers-color-scheme
                detectInternal();
                htmlNode.classList.add( 'theme-auto' );
        		// Attach listener for future changes
        		prefersDark.addEventListener( 'change', detectInternal );
        	} else {
                // Apply the theme choice and stop tracking prefers-color-scheme changes
        		applyInternal( currentTheme );
                prefersDark.removeEventListener( 'change', detectInternal );
        	}
        }
    };


    MwSkinTheme.set( localStorage.getItem( themeKey ) || RLCONF.wgThemeToggleDefault );
} )();