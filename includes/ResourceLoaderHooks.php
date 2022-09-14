<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use Config;
use ExtensionRegistry;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderFileModule;

class ResourceLoaderHooks implements
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook {
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

    private function registerThemeModule( ResourceLoader $resourceLoader, string $id ): void {
        $resourceLoader->register( 'ext.theme.' . $id, [
			'class' => ResourceLoaderWikiThemeModule::class,
			'id' => $id
		] );
    }
    
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
        /* This is a stub, ideally there'd be a definitions page unless there's some more clever way */
        global $wgThemeToggleSiteCssBundled,
            $wgThemeToggleSwitcherStyle;

        $resourceLoader->register( 'ext.themes.apply', [
            'class' => ResourceLoaderFileModule::class,
            'localBasePath' => 'extensions/ThemeToggle/modules/inline',
            'targets' => [ 'desktop', 'mobile' ],
            'scripts' => [ ModuleHelper::getCoreJsNameToServe() . '.js' ]
        ] );

        if ( ModuleHelper::getSwitcherModuleId() !== null ) {
            $resourceLoader->register( 'ext.themes.switcher', [
			    'class' => ResourceLoaderFileModule::class,
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

        foreach ( ThemeDefinitions::get()->getIds() as $theme ) {
            $messages[] = "theme-$theme";
            if ( !in_array( $theme, $wgThemeToggleSiteCssBundled ) ) {
                $this->registerThemeModule( $resourceLoader, $theme );
            }
        }

        $resourceLoader->register( 'ext.themes.siteMessages', [
			'class' => ResourceLoaderFileModule::class,
			'messages' => $messages
		] );
	}

    public function getSiteConfigModuleContents( ResourceLoaderContext $context, Config $config ): array {
        $defs = ThemeDefinitions::get();
        $ids = $defs->getIds();

        return [
            'themes' => $ids,
            'supportsAuto' => $defs->isEligibleForAuto(),
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