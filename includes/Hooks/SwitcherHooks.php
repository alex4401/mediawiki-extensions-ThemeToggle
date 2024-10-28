<?php
namespace MediaWiki\Extension\ThemeToggle\Hooks;

use ExtensionRegistry;
use MediaWiki\Extension\ThemeToggle\ConfigNames;
use MediaWiki\Extension\ThemeToggle\ExtensionConfig;
use MediaWiki\Extension\ThemeToggle\ThemeAndFeatureRegistry;
use MediaWiki\Output\OutputPage;
use MediaWiki\ResourceLoader as RL;
use MediaWiki\ResourceLoader\ResourceLoader;
use Skin;

class SwitcherHooks implements
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

        // Inject the theme switcher as a ResourceLoader module
        if ( $this->getSwitcherModuleId() !== null ) {
            $out->addModules( [ 'ext.themes.switcher' ] );
        }
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
    }

    private function getSwitcherModuleDefinition( string $id ): array {
        switch ( $id ) {
            case 'ext.themes.dayNightSwitcher':
                return [
                    'packageFiles' => [ 'dayNightSwitcher/main.js' ],
                    'styles' => [ 'dayNightSwitcher/' . ( $this->isWikiGG() ? 'styles-wikigg.less' : 'styles-generic.less' ) ],
                    'messages' => [
                        'themetoggle-simple-switch',
                        'themetoggle-simple-switch-short',
                    ]
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
}
