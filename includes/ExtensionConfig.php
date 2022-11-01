<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

class ExtensionConfig {
    public static function useAsyncJsDelivery(): bool {
        global $wgThemeToggleAsyncCoreJsDelivery;
        return array_key_exists( 'asynctt', $_GET ) ? ( $_GET['asynctt'] == 1 ) : $wgThemeToggleAsyncCoreJsDelivery;
    }

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
}
