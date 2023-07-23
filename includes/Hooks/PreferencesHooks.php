<?php
namespace MediaWiki\Extension\ThemeToggle\Hooks;

use MediaWiki\Extension\ThemeToggle\ExtensionConfig;
use MediaWiki\Extension\ThemeToggle\ThemeAndFeatureRegistry;

class PreferencesHooks implements
    \MediaWiki\Preferences\Hook\GetPreferencesHook,
    \MediaWiki\User\Hook\UserGetDefaultOptionsHook
{
    /** @var ExtensionConfig */
    private ExtensionConfig $config;

    /** @var ThemeAndFeatureRegistry */
    private ThemeAndFeatureRegistry $registry;

    public function __construct(
        ExtensionConfig $config,
        ThemeAndFeatureRegistry $registry
    ) {
        $this->config = $config;
        $this->registry = $registry;
    }

    public function onUserGetDefaultOptions( &$defaultOptions ) {
        $defaultOptions[$this->config->getThemePreferenceName()] = $this->registry->getDefaultThemeId();
    }

    public function onGetPreferences( $user, &$preferences ) {
        $themeOptions = [];

        if ( $this->registry->isEligibleForAuto() ) {
            $themeOptions[wfMessage( 'theme-auto-preference-description' )->text()] = 'auto';
        }

        foreach ( $this->registry->getAll() as $themeId => $themeInfo ) {
            if ( $themeInfo->isUserAllowedToUse( $user ) ) {
                $themeOptions[wfMessage( $themeInfo->getMessageId() )->text()] = $themeId;
            }
        }

        $preferences[$this->config->getThemePreferenceName()] = [
            'label-message' => 'themetoggle-user-preference-label',
            'type' => 'select',
            'options' => $themeOptions,
            'section' => 'rendering/skin/skin-prefs'
        ];
    }
}
