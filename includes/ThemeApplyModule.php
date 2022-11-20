<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use MediaWiki\MediaWikiServices;
use ResourceLoaderContext;
use ResourceLoaderFileModule;

class ThemeApplyModule extends ResourceLoaderFileModule {
	protected $targets = [ 'desktop', 'mobile' ];

	public function getScript( ResourceLoaderContext $context ): string {
		$script = parent::getScript( $context );

		$user = $context->getUserObj();
        $defs = ThemeDefinitions::get();

        $currentTheme = $defs->getDefaultThemeId();
        // Retrieve user's preference
        if ( !$user->isAnon() ) {
            $currentTheme = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $user, PreferenceHooks::getThemePreferenceName(), $currentTheme );
        }

		// Perform replacements
		$pairs = [
            'VARS.Default' => $context->encodeJson( $currentTheme ),
            'VARS.SiteBundledCss' => $context->encodeJson( $defs->getBundledThemeIds() ),
            'VARS.ResourceLoaderEndpoint' => $context->encodeJson( $this->getThemeLoadEndpointUri( $context ) ),
		];
		$script = strtr( $script, $pairs );

		return $script;
	}

    private function getThemeLoadEndpointUri( ResourceLoaderContext $context ): string {
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
