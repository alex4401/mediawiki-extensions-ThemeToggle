/**
 * @typedef {Object} SwitcherConfig
 * @prop {string?} preferenceGroup
 * @prop {boolean} supportsAuto
 * @prop {string[]} themes
 * @prop {string} defaultTheme
*/

/** @type {SwitcherConfig} */
module.exports.CONFIG = require( './config.json' );
/** @type {string} */
module.exports.LOCAL_PREF_NAME = 'skin-theme';
/** @type {string} */
module.exports.REMOTE_PREF_NAME = 'skinTheme-' + ( module.exports.CONFIG.preferenceGroup || mw.config.get( 'wgWikiID' ) );


function _setAccountPreference( value ) {
    mw.loader.using( 'mediawiki.api' ).then( function () {
        var api = new mw.Api();
        api.post( {
            action: 'options',
            format: 'json',
            optionname: module.exports.REMOTE_PREF_NAME,
            optionvalue: value,
            token: mw.user.tokens.get( 'csrfToken' )
        } );
    } );
};


/**
 * Checks whether local preference points to a valid theme, and if not, erases it and requests the default theme to be
 * set.
 */
module.exports.trySanitisePreference = function () {
    if ( mw.config.get( 'wgUserName' ) === null
        && this.CONFIG.themes.indexOf( localStorage.getItem( module.exports.LOCAL_PREF_NAME ) < 0 ) ) {
        localStorage.removeItem( module.exports.LOCAL_PREF_NAME );
        MwSkinTheme.set( module.exports.CONFIG.defaultTheme );
    }
};


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


module.exports.prepare = function () {
    module.exports.trySanitisePreference();
    module.exports.trySyncNewAccount();
};
