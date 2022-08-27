window.extApplyThemePreference = function () {
    var themeKey = 'skin-theme',
        classPrefix = 'theme-',
        modulePrefix = 'ext.theming.';
    var themes = [ 'dark', 'light' ];


    function getCurrentTheme() {
        return window.localStorage.getItem( themeKey );
    }


    var targetTheme = getCurrentTheme();
    var prefersDark = window.matchMedia( '(prefers-color-scheme: dark)' );


	function applyInternal() {
		try {
			targetTheme = getCurrentTheme();

			// Apply by changing class
			if ( targetTheme !== null ) {
				// Remove all theme classes
                themes.forEach( function ( item ) {
                    document.documentElement.classList.remove( classPrefix + item );
                } );
                // Add new theme class
				document.documentElement.classList.add( classPrefix + targetTheme );
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

( function () {
	window.extApplyThemePreference();
} )();
