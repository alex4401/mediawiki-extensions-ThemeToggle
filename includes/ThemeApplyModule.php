<?php
namespace MediaWiki\Extension\ThemeToggle;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FileModule;

class ThemeApplyModule extends FileModule {
    protected $targets = [ 'desktop', 'mobile' ];

    public function getScript( Context $context ): string {
        $script = parent::getScript( $context );

        $user = $context->getUserObj();
        $defs = ThemeAndFeatureRegistry::get();

        $currentTheme = $defs->getDefaultThemeId();
        // Retrieve user's preference
        if ( !$user->isAnon() ) {
            $currentTheme = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $user, PreferencesHooks::getThemePreferenceName(), $currentTheme );
        }

        // Perform replacements
        global $wgThemeToggleDisableAutoDetection;
        $script = strtr( $script, [
            'VARS.Default' => $context->encodeJson( $currentTheme ),
            'VARS.SiteBundledCss' => $context->encodeJson( $defs->getBundledThemeIds() ),
            'VARS.ResourceLoaderEndpoint' => $context->encodeJson( $this->getThemeLoadEndpointUri( $context ) ),
            'VARS.WithPCSSupport' => !$wgThemeToggleDisableAutoDetection && $defs->isEligibleForAuto() ? 1 : 0,
            'VARS.WithFeatureSupport' => false
        ] );
        $script = strtr( $script, [
            // Normalise conditions
            '!1' => '0',
            '!0' => '1'
        ] );
        $script = preg_replace( '/\/\* @if \( 0 \) \*\/[\s\S]+?\/\* @endif \*\//m', '', $script );

        return $script;
    }

    private function getThemeLoadEndpointUri( Context $context ): string {
        $loadScript = ExtensionConfig::getLoadScript();
        $language = $context->getLanguage();
        $out = "$loadScript?lang=$language&only=styles";
        if ( $context->getDebug() ) {
            $out .= '&debug=1';
        }
        return $out;
    }

    public function supportsURLLoading(): bool {
        return false;
    }

    public function enableModuleContentVersion(): bool {
        // Enabling this means that ResourceLoader::getVersionHash will simply call getScript()
        // and hash it to determine the version (as used by E-Tag HTTP response header).
        return true;
    }
}
