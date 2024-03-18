<?php
namespace MediaWiki\Extension\ThemeToggle\Hooks;

use Config;
use ExtensionRegistry;
use MediaWiki\Extension\ThemeToggle\ConfigNames;
use MediaWiki\Extension\ThemeToggle\ExtensionConfig;
use MediaWiki\Extension\ThemeToggle\ResourceLoader\WikiThemeModule;
use MediaWiki\Extension\ThemeToggle\ThemeAndFeatureRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\ResourceLoader;
use OutputPage;
use Skin;

class ThemeLoadingHooks implements
    \MediaWiki\Hook\BeforePageDisplayHook,
    \MediaWiki\Hook\OutputPageAfterGetHeadLinksArrayHook,
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook
{
    /** @var ExtensionConfig */
    private ExtensionConfig $config;

    /** @var ThemeAndFeatureRegistry */
    private ThemeAndFeatureRegistry $registry;

    private ?bool $isWikiGG = null;

    public function __construct(
        ExtensionConfig $config,
        ThemeAndFeatureRegistry $registry
    ) {
        $this->config = $config;
        $this->registry = $registry;
    }

    private function isWikiGG(): bool {
        if ( $this->isWikiGG === null ) {
            // HACK: Kind of a hack, but these extensions are always present on wiki.gg wikis as they're internal
            $registry = ExtensionRegistry::getInstance();
            $this->isWikiGG = $registry->isLoaded( 'WikiBase' ) && $registry->isLoaded( 'WikiHooks' );
        }
        return $this->isWikiGG;
    }

    private function getSwitcherModuleId(): ?string {
        switch ( $this->config->get( ConfigNames::SwitcherStyle ) ) {
            case 'auto':
                return ( count( $this->registry->getIds() ) <= 2 ) ? 'ext.themes.dayNightSwitcher'
                    : 'ext.themes.dropdownSwitcher';
            case 'dayNight':
            case 'simple':
                return 'ext.themes.dayNightSwitcher';
            case 'dropdown':
                return 'ext.themes.dropdownSwitcher';
        }
        return null;
    }

    /**
     * Schedules switcher loading, adds body classes, injects logged-in users' theme choices.
     *
     * @param OutputPage $out
     * @param Skin $skin
     */
    public function onBeforePageDisplay( $out, $skin ): void {
        $isAnonymous = $out->getUser()->isAnon();
        if ( !$this->config->get( ConfigNames::EnableForAnonymousUsers ) && $isAnonymous ) {
            return;
        }

        $currentTheme = $this->registry->getForUser( $out->getUser() );

        // Expose configuration variables
        if ( !$isAnonymous ) {
            $out->addJsConfigVars( [
                'wgCurrentTheme' => $currentTheme
            ] );
        }

        // Preload the CSS class. For automatic detection, assume light - we can't make a good guess (obviously), but client
        // scripts will correct this.
        if ( $currentTheme !== 'auto' ) {
            $out->addHtmlClasses( [ "theme-$currentTheme" ] );
        } else {
            $out->addHtmlClasses( [ 'theme-auto', 'theme-light' ] );
        }
        // Preload the styles if default or current theme is not bundled with site CSS
        if ( $currentTheme !== 'auto' ) {
            $currentThemeInfo = $this->registry->get( $currentTheme );
            if ( $currentThemeInfo !== null && !$currentThemeInfo->isBundled() ) {
                $out->addLink( [
                    'id' => 'mw-themetoggle-styleref',
                    'rel' => 'stylesheet',
                    'href' => wfAppendQuery( $this->getThemeLoadEndpointUri( $out ), [
                        'only' => 'styles',
                        'modules' => "ext.theme.$currentTheme",
                    ] )
                ] );
            }
        }

        // Inject the theme switcher as a ResourceLoader module
        if ( $this->getSwitcherModuleId() !== null ) {
            $out->addModules( [ 'ext.themes.switcher' ] );
        }
    }

    /**
     * Injects the theme applying script into <head> before meta tags and other extensions' head items. This should
     * help the script get downloaded earlier (ideally it would be scheduled before core JS).
     *
     * @param array &$tags
     * @param OutputPage $out
     * @return void
     */
    public function onOutputPageAfterGetHeadLinksArray( &$tags, $out ) {
        $rlEndpoint = $this->getThemeLoadEndpointUri( $out );
        $skin = $out->getSkin()->getSkinName();
        $html = $this->makeScriptTag(
            $out,
            '',
            "async src=\"$rlEndpoint&modules=ext.themes.apply&only=scripts&skin=$skin&raw=1\""
        );
        array_unshift( $tags, $html );
    }

    public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
        if ( $this->getSwitcherModuleId() !== null ) {
            $resourceLoader->register( 'ext.themes.switcher', [
                'class' => RL\FileModule::class,
                'localBasePath' => 'extensions/ThemeToggle/modules',
                'remoteExtPath' => 'ThemeToggle/modules',
                'dependencies' => [ 'ext.themes.jsapi' ],
                'targets' => [ 'desktop', 'mobile' ]
            ] + $this->getSwitcherModuleDefinition( $this->getSwitcherModuleId() ) );
        }

        foreach ( $this->registry->getAll() as $themeId => $themeInfo ) {
            if ( !$themeInfo->isBundled() ) {
                $resourceLoader->register( 'ext.theme.' . $themeId, [
                    'class' => WikiThemeModule::class,
                    'id' => $themeId
                ] );
            }
        }
    }

    private function makeScriptTag( OutputPage $outputPage, string $script, $attributes = false ) {
        $nonce = $outputPage->getCSP()->getNonce();
        return sprintf(
            '<script%s%s>%s</script>',
            $nonce !== false ? " nonce=\"$nonce\"" : '',
            $attributes !== false ? " $attributes" : '',
            $script
        );
    }

    private function injectScriptTag( OutputPage $outputPage, string $id, string $script, $attributes = false ) {
        $outputPage->addHeadItem( $id, $this->makeScriptTag( $outputPage, $script, $attributes ) );
    }

    private function getThemeLoadEndpointUri( OutputPage $outputPage ): string {
        return wfAppendQuery( $this->config->getLoadScript(), [
            'lang' => $outputPage->getLanguage()->getCode(),
            'debug' => ResourceLoader::inDebugMode() ? '2' : false,
            'skin' => $outputPage->getSkin()->getSkinName(),
        ] );
    }

    private function getSwitcherModuleDefinition( string $id ): array {
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
                    'styles' => [ 'dropdownSwitcher/' . ( $this->isWikiGG() ? 'styles-wikigg.less' : 'styles-generic.less' ) ],
                    'messages' => [
                        'themetoggle-dropdown-switch',
                        'themetoggle-dropdown-section-themes',
                    ]
                ];
        }
    }

    public static function getSiteConfigModuleContents( RL\Context $context, Config $config ): array {
        /** @var ExtensionConfig */
        $config = MediaWikiServices::getInstance()->getService( ExtensionConfig::SERVICE_NAME );
        /** @var ThemeAndFeatureRegistry */
        $registry = MediaWikiServices::getInstance()->getService( ThemeAndFeatureRegistry::SERVICE_NAME );

        return [
            'themes' => array_map(
                static function ( $key, $info ) {
                    if ( $info->getEntitledUserGroups() ) {
                        return [
                            'id' => $key,
                            'userGroups' => $info->getEntitledUserGroups(),
                        ];
                    }
                    return $key;
                },
                array_keys( $registry->getAll() ),
                array_values( $registry->getAll() )
            ),
            'supportsAuto' => $registry->isEligibleForAuto(),
            'preferenceGroup' => $config->getPreferenceSuffix(),
            'defaultTheme' => $registry->getDefaultThemeId()
        ];
    }
}
