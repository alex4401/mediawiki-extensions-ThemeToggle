<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\ResourceLoader;
use OutputPage;

class BodyHooks implements
    \MediaWiki\Hook\BeforePageDisplayHook {

    /**
     * Injects the inline theme applying script to the document head
     */
    public function onBeforePageDisplay( $out, $skin ): void {
        global $wgThemeToggleEnableForAnonymousUsers;

        $isAnonymous = $out->getUser()->isAnon();
        if ( !$wgThemeToggleEnableForAnonymousUsers && $isAnonymous ) {
            return;
        }

        $defs = ThemeDefinitions::get();
        $currentTheme = $defs->getDefaultThemeId();
        // Retrieve user's preference
        if ( !$isAnonymous ) {
            $currentTheme = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $out->getUser(), PreferenceHooks::getThemePreferenceName(), $currentTheme );
        }

        // Expose configuration variables
        $out->addJsConfigVars( [
            'wgThemeToggleDefault' => $currentTheme,
            // @deprecated v0.3.1:v0.4.0
            'wgThemeToggleSiteCssBundled' => $defs->getBundledThemeIds()
        ] );
        if ( !$isAnonymous && ExtensionConfig::getPreferenceGroupName() !== null ) {
            $out->addJsConfigVars( [
                'wgThemeTogglePrefGroup' => ExtensionConfig::getPreferenceGroupName()
            ] );
        }

        // Inject the theme applying script into <head> to reduce latency
        $rlEndpoint = self::getThemeLoadEndpointUri( $out );
        self::injectScriptTag( $out, 'ext.themes.apply', '', "async src=\"$rlEndpoint&modules=ext.themes.apply&only=scripts"
            . '&raw=1"' );

        // Inject the theme switcher as a ResourceLoader module
        if ( ModuleHelper::getSwitcherModuleId() !== null ) {
            $out->addModules( [ 'ext.themes.switcher' ] );
        }
    }

    private static function injectScriptTag( OutputPage $outputPage, string $id, string $script, $attributes = false ) {
        $nonce = $outputPage->getCSP()->getNonce();
        $outputPage->addHeadItem( $id, sprintf(
            '<script%s%s>%s</script>',
            $nonce !== false ? " nonce=\"$nonce\"" : '',
            $attributes !== false ? " $attributes" : '',
            $script
        ) );
    }

    private static function getThemeLoadEndpointUri( OutputPage $outputPage ): string {
        $out = ExtensionConfig::getLoadScript() . '?lang=' . $outputPage->getLanguage()->getCode();
        if ( ResourceLoader::inDebugMode() ) {
            $out .= '&debug=1';
        }
        return $out;
    }
}
