<?php
namespace MediaWiki\Extension\ThemeToggle;

class ExtensionConfig {
    public static function getLoadScript(): string {
        global $wgThemeToggleLoadScriptOverride,
            $wgLoadScript;
        if ( $wgThemeToggleLoadScriptOverride !== null ) {
            return $wgThemeToggleLoadScriptOverride;
        }
        return $wgLoadScript;
    }

    public static function getPreferenceGroupName(): ?string {
        global $wgThemeTogglePreferenceGroup;
        return $wgThemeTogglePreferenceGroup;
    }

    public static function isDeadCodeEliminationExperimentEnabled(): bool {
        global $wgThemeToggleDeadCodeEliminationExperiment;
        return $wgThemeToggleDeadCodeEliminationExperiment;
    }
}
