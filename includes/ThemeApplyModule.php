<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FileModule;

class ThemeApplyModule extends FileModule {
    protected $targets = [ 'desktop', 'mobile' ];

    public function getScript( Context $context ): string {
        $script = parent::getScript( $context );

        global $wgThemeToggleDefault,
            $wgThemeToggleSiteCssBundled;

        $user = $context->getUserObj();
        $currentTheme = $wgThemeToggleDefault;
        // Retrieve user's preference
        if ( !$user->isAnon() ) {
            $currentTheme = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $user, PreferenceHooks::getThemePreferenceName(), $wgThemeToggleDefault );
        }

        // Perform replacements
        $pairs = [
            'VARS.Default' => $context->encodeJson( $currentTheme ),
            'VARS.SiteBundledCss' => $context->encodeJson( $wgThemeToggleSiteCssBundled ),
            'VARS.ResourceLoaderEndpoint' => $context->encodeJson( $this->getThemeLoadEndpointUri( $context ) ),
        ];
        $script = strtr( $script, $pairs );

        return $script;
    }

    private function getThemeLoadEndpointUri( Context $context ): string {
        $out = ExtensionConfig::getLoadScript() . '?lang=' . $context->getLanguage();
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
