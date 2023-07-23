<?php
namespace MediaWiki\Extension\ThemeToggle;

use Config;
use ExtensionRegistry;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\ResourceLoader;
use OutputPage;

class ThemeLoadingHooks implements
    \MediaWiki\Hook\BeforePageDisplayHook,
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook
{
    private static ?bool $isWikiGG = null;

    /**
     * Injects the inline theme applying script to the document head
     */
    public function onBeforePageDisplay( $out, $skin ): void {
        global $wgThemeToggleEnableForAnonymousUsers;

        $isAnonymous = $out->getUser()->isAnon();
        if ( !$wgThemeToggleEnableForAnonymousUsers && $isAnonymous ) {
            return;
        }

        $defs = ThemeAndFeatureRegistry::get();
        $currentTheme = $defs->getForUser( $out->getUser() );

        // Expose configuration variables
        if ( !$isAnonymous ) {
            $out->addJsConfigVars( [
                'wgCurrentTheme' => $currentTheme
            ] );
        }

        // Preload the CSS class
        if ( $currentTheme !== 'auto' ) {
            $out->addHtmlClasses( [ "theme-$currentTheme" ] );
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

    public static function getSwitcherModuleDefinition( string $id ): array {
        switch ( $id ) {
            case 'ext.themes.dayNightSwitcher':
                return [
                    'packageFiles' => [ 'dayNightSwitcher/main.js' ],
                    'styles' => [ 'dayNightSwitcher/styles.less' ],
                    'messages' => [ 'themetoggle-simple-switch' ]
                ];
            case 'ext.themes.dropdownSwitcher':
                return [
                    'packageFiles' => [ 'dropdownSwitcher/main.js' ],
                    'styles' => [ 'dropdownSwitcher/' . ( self::isWikiGG() ? 'styles-wikigg.less' : 'styles-generic.less' ) ],
                    'messages' => [ 'themetoggle-dropdown-switch' ]
                ];
        }
    }

    public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
        /* This is a stub, ideally there'd be a definitions page unless there's some more clever way */
        global $wgThemeToggleSiteCssBundled;

        $resourceLoader->register( 'ext.themes.apply', [
            'class' => ThemeApplyModule::class,
            'localBasePath' => 'extensions/ThemeToggle/modules/inline',
            'scripts' => [ ModuleHelper::getCoreJsNameToServe() . '.js' ]
        ] );

        if ( ModuleHelper::getSwitcherModuleId() !== null ) {
            $resourceLoader->register( 'ext.themes.switcher', [
                'class' => FileModule::class,
                'localBasePath' => 'extensions/ThemeToggle/modules',
                'remoteExtPath' => 'extensions/ThemeToggle/modules',
                'dependencies' => [ 'ext.themes.jsapi' ],
                'targets' => [ 'desktop', 'mobile' ]
            ] + self::getSwitcherModuleDefinition( ModuleHelper::getSwitcherModuleId() ) );
        }

        $messages = [];

        if ( ThemeAndFeatureRegistry::get()->isEligibleForAuto() ) {
            $messages[] = 'theme-auto';
        }

        foreach ( ThemeAndFeatureRegistry::get()->getAll() as $themeId => $themeInfo ) {
            $messages[] = $themeInfo->getMessageId();
            if ( !in_array( $themeId, $wgThemeToggleSiteCssBundled ) ) {
                $resourceLoader->register( 'ext.theme.' . $themeId, [
                    'class' => ResourceLoaderWikiThemeModule::class,
                    'id' => $themeId
                ] );
            }
        }

        $resourceLoader->register( 'ext.themes.siteMessages', [
            'class' => FileModule::class,
            'messages' => $messages
        ] );
    }

    public static function getSiteConfigModuleContents( RL\Context $context, Config $config ): array {
        $defs = ThemeAndFeatureRegistry::get();
        return [
            'themes' => array_keys( array_filter( $defs->getAll(), fn( $themeInfo, $themeId )
                => ( count( $themeInfo->getRequiredUserRights() ) <= 0 ), ARRAY_FILTER_USE_BOTH ) ),
            'supportsAuto' => $defs->isEligibleForAuto(),
            'preferenceGroup' => ExtensionConfig::getPreferenceGroupName(),
            'defaultTheme' => $defs->getDefaultThemeId()
        ];
    }

    public static function isWikiGG(): bool {
        if ( self::$isWikiGG === null ) {
            // HACK: Kind of a hack, but these extensions are always present on wiki.gg wikis as they're internal
            $registry = ExtensionRegistry::getInstance();
            self::$isWikiGG = $registry->isLoaded( 'WikiBase' ) && $registry->isLoaded( 'WikiHooks' );
        }
        return self::$isWikiGG;
    }
}
