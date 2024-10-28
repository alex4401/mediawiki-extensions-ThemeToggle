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
    private const SWITCHER_DROPDOWN = 'Dropdown';
    private const SWITCHER_DAYNIGHT = 'DayNight';

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

    private function getSwitcherStyle(): ?string {
        switch ( $this->config->get( ConfigNames::SwitcherStyle ) ) {
            case 'dayNight':
            case 'simple':
                return self::SWITCHER_DAYNIGHT;
            case 'dropdown':
                return self::SWITCHER_DROPDOWN;

            case 'auto':
                return count( $this->registry->getIds() ) === 2 ? self::SWITCHER_DAYNIGHT : self::SWITCHER_DROPDOWN;
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
        if ( $this->getSwitcherStyle() !== null ) {
            $out->addModules( [ 'ext.themes.switcher' ] );
        }
    }

    public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
        $style = $this->getSwitcherStyle();
        if ( $style !== null ) {
            $resourceLoader->register( 'ext.themes.switcher', [
                'class' => RL\FileModule::class,
                'localBasePath' => 'extensions/ThemeToggle/modules',
                'remoteExtPath' => 'ThemeToggle/modules',
                'dependencies' => [ 'ext.themes.jsapi' ],
                'targets' => [ 'desktop', 'mobile' ]
            ] + $this->getModuleDefinitionForStyle( $style ) );
        }
    }

    private function getModuleDefinitionForStyle( string $style ): array {
        switch ( $style ) {
            case self::SWITCHER_DAYNIGHT:
                return [
                    'packageFiles' => [ 'dayNightSwitcher/main.js' ],
                    'styles' => [ 'dayNightSwitcher/' . ( $this->isWikiGG() ? 'styles-wikigg.less' : 'styles-generic.less' ) ],
                    'messages' => [
                        'themetoggle-simple-switch',
                        'themetoggle-simple-switch-short',
                    ]
                ];
            case self::SWITCHER_DROPDOWN:
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
