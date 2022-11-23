<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

class ModuleHelper {
    public static function getSwitcherModuleId(): ?string {
        global $wgThemeToggleSwitcherStyle;
        switch ( $wgThemeToggleSwitcherStyle ) {
            case 'auto':
                return ( count( ThemeDefinitions::get()->getIds() ) <= 2 ) ? 'ext.themes.simpleSwitcher'
                    : 'ext.themes.dropdownSwitcher';
            case 'simple':
                return 'ext.themes.simpleSwitcher';
            case 'dropdown':
                return 'ext.themes.dropdownSwitcher';
        }
        return null;
    }

    public static function getCoreJsNameToServe(): string {
        global $wgThemeToggleDisableAutoDetection;
        if ( $wgThemeToggleDisableAutoDetection || !ThemeDefinitions::get()->isEligibleForAuto() ) {
            return 'noAuto';
        }
        return 'withAuto';
    }
}
