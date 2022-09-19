module.exports.REMOTE_PREF_NAME = 'skinTheme-' + ( mw.config.get( 'wgThemeTogglePrefGroup' ) || mw.config.get( 'wgWikiID' ) );
module.exports.LOCAL_PREF_NAME = 'skin-theme';
module.exports.CONFIG = require( './config.json' );


var _setAccountPreference = function ( value ) {
    mw.loader.using( 'mediawiki.api' ).then( function() {
        var api = new mw.Api();
        api.post( {
            action: 'options',
            format: 'json',
            optionname: module.exports.REMOTE_PREF_NAME,
            optionvalue: value,
            token: mw.user.tokens.get( 'csrfToken' )
        } );
    } );
}


module.exports.trySyncNewAccount = function () {
    if ( mw.config.get( 'wgUserName' ) !== null ) {
        var prefValue = localStorage.getItem( module.exports.LOCAL_PREF_NAME );
        if ( prefValue ) {
            localStorage.removeItem( module.exports.LOCAL_PREF_NAME );
            _setAccountPreference( prefValue );
        }
    }
};


module.exports.setUserPreference = function ( value ) {
    if ( mw.config.get( 'wgUserName' ) !== null ) {
        // Registered user: save the theme server-side
        _setAccountPreference( value );
    } else {
        // Anonymous user: save the theme in their browser's local storage
        localStorage.setItem( module.exports.LOCAL_PREF_NAME, value );
    }

    MwSkinTheme.set( value );
};


module.exports.whenCoreLoaded = function ( callback, context ) {
    if ( 'MwSkinTheme' in window ) {
        callback.apply( context );
    } else {
        setTimeout( module.exports.whenCoreLoaded.bind( null, callback, context ), 20 );
    }
};
