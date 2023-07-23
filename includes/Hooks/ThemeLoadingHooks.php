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
     * Injects the inline theme applying script to the document head
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

        // Inject the theme applying script into <head> to reduce latency
        $rlEndpoint = $this->getThemeLoadEndpointUri( $out );
        $this->injectScriptTag( $out, 'ext.themes.apply', '', "async src=\"$rlEndpoint&modules=ext.themes.apply&only=scripts"
            . '&raw=1"' );

        // Inject the theme switcher as a ResourceLoader module
        if ( $this->getSwitcherModuleId() !== null ) {
            $out->addModules( [ 'ext.themes.switcher' ] );
        }
    }

    public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
        if ( $this->getSwitcherModuleId() !== null ) {
            $resourceLoader->register( 'ext.themes.switcher', [
                'class' => FileModule::class,
                'localBasePath' => 'extensions/ThemeToggle/modules',
                'remoteExtPath' => 'extensions/ThemeToggle/modules',
                'dependencies' => [ 'ext.themes.jsapi' ],
                'targets' => [ 'desktop', 'mobile' ]
            ] + $this->getSwitcherModuleDefinition( $this->getSwitcherModuleId() ) );
        }

        $messages = [];
        if ( $this->registry->isEligibleForAuto() ) {
            $messages[] = 'theme-auto';
        }

        foreach ( $this->registry->getAll() as $themeId => $themeInfo ) {
            $messages[] = $themeInfo->getMessageId();
            if ( !$themeInfo->isBundled() ) {
                $resourceLoader->register( 'ext.theme.' . $themeId, [
                    'class' => WikiThemeModule::class,
                    'id' => $themeId
                ] );
            }
        }

        $resourceLoader->register( 'ext.themes.siteMessages', [
            'class' => FileModule::class,
            'messages' => $messages
        ] );
    }

    private function injectScriptTag( OutputPage $outputPage, string $id, string $script, $attributes = false ) {
        $nonce = $outputPage->getCSP()->getNonce();
        $outputPage->addHeadItem( $id, sprintf(
            '<script%s%s>%s</script>',
            $nonce !== false ? " nonce=\"$nonce\"" : '',
            $attributes !== false ? " $attributes" : '',
            $script
        ) );
    }

    private function getThemeLoadEndpointUri( OutputPage $outputPage ): string {
        $out = $this->config->getLoadScript() . '?lang=' . $outputPage->getLanguage()->getCode();
        if ( ResourceLoader::inDebugMode() ) {
            $out .= '&debug=1';
        }
        return $out;
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
                    'messages' => [ 'themetoggle-dropdown-switch' ]
                ];
        }
    }

    public static function getSiteConfigModuleContents( RL\Context $context, Config $config ): array {
        /** @var ExtensionConfig */
        $config = MediaWikiServices::getInstance()->getService( ExtensionConfig::SERVICE_NAME );
        /** @var ThemeAndFeatureRegistry */
        $registry = MediaWikiServices::getInstance()->getService( ThemeAndFeatureRegistry::SERVICE_NAME );

        return [
            'themes' => array_keys( array_filter( $registry->getAll(), fn( $themeInfo, $themeId )
                => ( count( $themeInfo->getRequiredUserRights() ) <= 0 ), ARRAY_FILTER_USE_BOTH ) ),
            'supportsAuto' => $registry->isEligibleForAuto(),
            'preferenceGroup' => $config->getPreferenceSuffix(),
            'defaultTheme' => $registry->getDefaultThemeId()
        ];
    }
}
