<?php
namespace MediaWiki\Extension\ThemeToggle;

class ModuleHelper {
    public static function getSwitcherModuleId(): ?string {
        global $wgThemeToggleSwitcherStyle;
        switch ( $wgThemeToggleSwitcherStyle ) {
            case 'auto':
                return ( count( ThemeAndFeatureRegistry::get()->getIds() ) <= 2 ) ? 'ext.themes.dayNightSwitcher'
                    : 'ext.themes.dropdownSwitcher';
            case 'dayNight':
            case 'simple':
                return 'ext.themes.dayNightSwitcher';
            case 'dropdown':
                return 'ext.themes.dropdownSwitcher';
        }
        return null;
    }
}
