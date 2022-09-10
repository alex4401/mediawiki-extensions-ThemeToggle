var themeKey = 'skin-theme',
    linkNode = null,
    currentTheme = null;


window.MwSkinTheme = {
    getCurrentTheme: function () {
        return currentTheme;
    },

    set: function ( target ) {
        var htmlNode = document.documentElement;
    
        currentTheme = target;
    
    
        function applyInternal( target ) {            
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
                    linkNode.href = THEMELOAD+'?lang='+htmlNode.lang+'&modules=ext.theme.'+currentTheme+'&only=styles';
                } else if ( linkNode != null ) {
                    document.head.removeChild( linkNode );
                    linkNode = null;
                }
            } catch ( e ) { }
        }
    
    
        applyInternal( currentTheme );
    }
};


MwSkinTheme.set( localStorage.getItem( themeKey ) || RLCONF.wgThemeToggleDefault );