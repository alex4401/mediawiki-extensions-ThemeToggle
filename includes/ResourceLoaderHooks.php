<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use Config;
use ExtensionRegistry;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FileModule;
use MediaWiki\ResourceLoader\ResourceLoader;

class ResourceLoaderHooks implements
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook
{
    private static ?bool $isWikiGG = null;

    public static function getSwitcherModuleDefinition( string $id ): array {
        switch ( $id ) {
            case 'ext.themes.simpleSwitcher':
                return [
                    'packageFiles' => [ 'simpleSwitcher/main.js' ],
                    'styles' => [ 'simpleSwitcher/styles.less' ],
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
                'dependencies' => [ 'ext.themes.baseSwitcher' ],
                'targets' => [ 'desktop', 'mobile' ]
            ] + self::getSwitcherModuleDefinition( ModuleHelper::getSwitcherModuleId() ) );
        }

        $messages = [];

        if ( ThemeDefinitions::get()->isEligibleForAuto() ) {
            $messages[] = 'theme-auto';
        }

        foreach ( ThemeDefinitions::get()->getAll() as $themeId => $themeInfo ) {
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

    public function getSiteConfigModuleContents( Context $context, Config $config ): array {
        $defs = ThemeDefinitions::get();
        return [
            'themes' => array_keys( array_filter( $defs->getAll(), fn( $themeInfo, $themeId )
                => ( count( $themeInfo->getRequiredUserRights() ) <= 0 ), ARRAY_FILTER_USE_BOTH ) ),
            'supportsAuto' => $defs->isEligibleForAuto(),
            'preferenceGroup' => ExtensionConfig::getPreferenceGroupName()
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
