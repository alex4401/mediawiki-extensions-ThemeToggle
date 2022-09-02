var themeKey = 'skin-theme',
    prefersDark = window.matchMedia( '(prefers-color-scheme: dark)' ),
    linkNode = null,
    currentTheme = null,
    currentThemeActual = null;


window.mwGetCurrentTheme = function () {
    return currentTheme;
};


window.mwChangeDisplayedTheme = function ( target ) {
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
                linkNode.href = THEMELOAD+'?lang='+htmlNode.lang+'&modules=ext.theme.'+currentThemeActual+'&only=styles';
            } else if ( linkNode != null ) {
                document.head.removeChild( linkNode );
                linkNode = null;
            }
		} catch ( e ) { }
	}


    function detectInternal() {
		applyInternal( prefersDark.matches ? 'dark' : 'light' );
    }


	// Detect preferred theme by prefers-color-scheme
	if ( currentTheme === 'auto' ) {
        detectInternal();
        htmlNode.classList.add( 'theme-auto' );
		// Attach listener for future changes
		prefersDark.addEventListener( 'change', detectInternal );
	} else {
        htmlNode.classList.remove( 'theme-auto' );
		applyInternal( currentTheme );
        prefersDark.removeEventListener( 'change', detectInternal );
	}
};


mwChangeDisplayedTheme( localStorage.getItem( themeKey ) || RLCONF.wgThemeToggleDefault );