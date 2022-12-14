<?php
namespace MediaWiki\Extension\ThemeToggle;

use WikiMap;

class PreferenceHooks implements
    \MediaWiki\Preferences\Hook\GetPreferencesHook,
    \MediaWiki\User\Hook\UserGetDefaultOptionsHook
{

    public static function getPreferenceGroupName(): string {
        global $wgThemeTogglePreferenceGroup;
        return $wgThemeTogglePreferenceGroup ?? WikiMap::getCurrentWikiId();
    }

    public static function getThemePreferenceName(): string {
        return 'skinTheme-' . self::getPreferenceGroupName();
    }

    public function onUserGetDefaultOptions( &$defaultOptions ) {
        $defaultOptions[self::getThemePreferenceName()] = ThemeAndFeatureRegistry::get()->getDefaultThemeId();
    }

    public function onGetPreferences( $user, &$preferences ) {
        $defs = ThemeAndFeatureRegistry::get();
        $themeOptions = [];

        if ( $defs->isEligibleForAuto() ) {
            $themeOptions[wfMessage( 'theme-auto-preference-description' )->text()] = 'auto';
        }

        foreach ( $defs->getAll() as $themeId => $themeInfo ) {
            if ( $themeInfo->isUserAllowedToUse( $user ) ) {
                $themeOptions[wfMessage( $themeInfo->getMessageId() )->text()] = $themeId;
            }
        }

        $preferences[self::getThemePreferenceName()] = [
            'label-message' => 'themetoggle-user-preference-label',
            'type' => 'select',
            'options' => $themeOptions,
            'section' => 'rendering/skin/skin-prefs'
        ];
    }
}
