window.extApplyThemePreference = function () {
    var themeKey = 'skin-theme';


    function getCurrentTheme() {
        return window.localStorage.getItem( themeKey ) || THEMESITEDEFAULT;
    }


    var targetTheme = getCurrentTheme(),
        prefersDark = window.matchMedia( '(prefers-color-scheme: dark)' ),
        htmlNode = document.documentElement,
        linkNode = null;


	function applyInternal() {
		try {
			targetTheme = getCurrentTheme();

			// Apply by changing class
			if ( targetTheme !== null ) {
				// Remove all theme classes
                htmlNode.className = htmlNode.className.replace( / theme-[^\s]+/ig, '' );
                // Add new theme class
				htmlNode.classList.add( 'theme-' + targetTheme );
			}

            if ( THEMESITEBUNDLED.indexOf( targetTheme ) < 0 ) {
                if ( linkNode == null ) {
                    linkNode = document.createElement( 'link' );
                    document.head.appendChild( linkNode );
                }
                linkNode.rel = 'stylesheet';
                linkNode.type = 'text/css';
                linkNode.href = THEMELOAD+'?lang='+htmlNode.lang+'&modules=ext.theming.'+targetTheme+'&only=styles';
            } else if ( linkNode != null ) {
                document.head.removeChild( linkNode );
            }
		} catch ( e ) { }
	}


    function detectInternal() {
		targetTheme = prefersDark.matches ? 'dark' : 'light';
		// Set preference to the detected theme temporarily
		window.localStorage.setItem( themeKey, targetTheme );
        // Apply it
		applyInternal();
		// Reset preference back to auto
		window.localStorage.setItem( themeKey, 'auto' );
    }


	// Detect preferred theme by prefers-color-scheme
	if ( targetTheme === 'auto' ) {
        detectInternal();
		// Attach listener for future changes
		prefersDark.addEventListener( 'change', detectInternal );
	} else {
		applyInternal();
        prefersDark.removeEventListener( 'change', detectInternal );
	}
};


window.extApplyThemePreference();