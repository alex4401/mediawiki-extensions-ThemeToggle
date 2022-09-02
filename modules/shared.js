module.exports.setUserPreference = function ( value ) {
    if ( mw.config.get( 'wgUserName' ) !== null ) {
        // Registered user: save the theme server-side
        mw.loader.using( 'mediawiki.api' ).then( function() {
            var api = new mw.Api();
            api.post( {
                action: 'options',
                format: 'json',
                optionname: 'skinTheme',
                optionvalue: value,
                token: mw.user.tokens.get( 'csrfToken' )
            } );
        } );

    } else {
        // Anonymous user: save the theme in their browser's local storage
        localStorage.setItem( 'skin-theme', Config.themes[nextIndex] );
    }

    mwApplyThemePreference();
};