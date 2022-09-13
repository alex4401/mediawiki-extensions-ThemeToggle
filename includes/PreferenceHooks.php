<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use WikiMap;

class PreferenceHooks implements
    \MediaWiki\Preferences\Hook\GetPreferencesHook,
    \MediaWiki\User\Hook\UserGetDefaultOptionsHook {

    public static function getPreferenceGroupName(): string {
        global $wgThemeTogglePreferenceGroup;
        return $wgThemeTogglePreferenceGroup ?? WikiMap::getCurrentWikiId();
    }

    public static function getThemePreferenceName(): string {
        return 'skinTheme-' . self::getPreferenceGroupName();
    }

	public function onUserGetDefaultOptions( &$defaultOptions ) {
        global $wgThemeToggleDefault;
		$defaultOptions[self::getThemePreferenceName()] = $wgThemeToggleDefault;
	}

	public function onGetPreferences( $user, &$preferences ) {
        $defs = ThemeDefinitions::get();
        $themeOptions = [];

        if ( $defs->isEligibleForAuto() ) {
            $themeOptions[wfMessage( 'theme-auto-preference-description' )->text()] = 'auto';
        }

        foreach ( $defs->getIds() as $theme ) {
            $themeOptions[wfMessage( "theme-$theme" )->text()] = $theme;
        }

        $preferences[self::getThemePreferenceName()] = [
            'label-message' => 'themetoggle-user-preference-label',
            'type' => 'select',
            'options' => $themeOptions,
            'section' => 'rendering/skin/skin-prefs'
        ];
	}
}