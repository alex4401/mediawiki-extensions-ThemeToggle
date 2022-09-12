<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use Config;
use ManualLogEntry;
use ResourceLoader;
use ResourceLoaderContext;
use ResourceLoaderFileModule;
use WikiMap;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\ProperPageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class Hooks implements
    \MediaWiki\Page\Hook\PageDeleteCompleteHook,
    \MediaWiki\Storage\Hook\PageSaveCompleteHook,
    \MediaWiki\Hook\BeforePageDisplayHook,
    \MediaWiki\ResourceLoader\Hook\ResourceLoaderRegisterModulesHook,
    \MediaWiki\Preferences\Hook\GetPreferencesHook,
    \MediaWiki\User\Hook\UserGetDefaultOptionsHook {

    public function onPageSaveComplete( $wikiPage, $userIdentity, $summary, $flags, $revisionRecord, $editResult ): void {
        ThemeDefinitions::get()->handlePageUpdate( $wikiPage->getTitle() );
    }
    
    public function onPageDeleteComplete( $page, Authority $deleter, string $reason, int $pageID, RevisionRecord $deletedRev,
        ManualLogEntry $logEntry, int $archivedRevisionCount ): void {
        ThemeDefinitions::get()->handlePageUpdate( TitleValue::newFromPage( $page ) );
    }

    private function getSwitcherModuleId(): ?string {
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

    private function getSwitcherModuleDefinition( string $id ): array {
        switch ( $id ) {
            case 'ext.themes.simpleSwitcher':
                return [
                    'packageFiles' => [ 'simpleSwitcher/main.js' ],
                    'styles' => [ 'simpleSwitcher/styles.less' ],
                    'messages' => [ 'themetoggle-simple-switch' ]
                ];
            case 'ext.themes.dropdownSwitcher':
                return [
                    'packageFiles' => [ 'dropdownSwitcher/main.js' ],
                    'styles' => [ 'dropdownSwitcher/styles.less' ],
                    'messages' => [ 'themetoggle-dropdown-switch' ]
                ];
        }
    }

    /**
     * Returns the script to be added into the document head.
     * 
     * As themes can be managed via MediaWiki:Theme-definitions, do NOT use dark or light to decide if the auto-supporting
     * payload is best. This should be manually controlled because of cache constraints.
     */
    private function getCoreJsToInject(): string {
        global $wgThemeToggleDisableAutoDetection;
        if ( $wgThemeToggleDisableAutoDetection ) {
            return InlineJsConstants::NO_AUTO;
        }
        return InlineJsConstants::WITH_AUTO;
    }

    public function getSiteConfigModuleContents( ResourceLoaderContext $context, Config $config ): array {
        $defs = ThemeDefinitions::get();
        $ids = $defs->getIds();

        return [
            'themes' => $ids,
            'supportsAuto' => $defs->isEligibleForAuto(),
        ];
    }

	/**
	 * Injects the inline theme applying script to the document head
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
        global $wgLoadScript,
            $wgScriptPath,
            $wgThemeToggleDefault,
            $wgThemeToggleSiteCssBundled,
            $wgThemeToggleEnableForAnonymousUsers,
            $wgThemeToggleSwitcherStyle;

        $isAnonymous = $out->getUser()->isAnon();
        if ( !$wgThemeToggleEnableForAnonymousUsers && $isAnonymous ) {
            return;
        }

        $currentTheme = $wgThemeToggleDefault;
        // Retrieve user's preference
        if ( !$isAnonymous ) {
            $currentTheme = MediaWikiServices::getInstance()->getUserOptionsLookup()
                ->getOption( $out->getUser(), 'skinTheme', $wgThemeToggleDefault );
        }

        // Expose configuration variables
        $out->addJsConfigVars( [
            'wgThemeToggleDefault' => $currentTheme,
            'wgThemeToggleSiteCssBundled' => $wgThemeToggleSiteCssBundled
        ] );

        // Inject the theme applying script into <head> to reduce latency
		$nonce = $out->getCSP()->getNonce();
        // HACK: SANDBOX EXPERIMENT
        if ( array_key_exists( 'asynctt', $_GET ) ) {
    		$out->addHeadItem( 'ext.themes.inline', sprintf(
	    		'<script%s>THEMELOAD=%s</script>',
		    	$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
                json_encode( $wgLoadScript )
            ) );
    		$out->addHeadItem( 'ext.themes.async', sprintf(
	    		'<script%s async src="%s/extensions/ThemeToggle/modules/inline/withAuto.js"></script>',
		    	$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
                $wgScriptPath
            ) );
        } else {
    		$out->addHeadItem( 'ext.themes.inline', sprintf(
	    		'<script%s>(function(){var THEMELOAD=%s;%s})()</script>',
		    	$nonce !== false ? sprintf( ' nonce="%s"', $nonce ) : '',
                json_encode( $wgLoadScript ),
                // modules/inline.js
			    self::getCoreJsToInject()
            ) );
        }

        // Inject the theme switcher as a ResourceLoader module
        if ( $this->getSwitcherModuleId() !== null ) {
            $out->addModules( [ 'ext.themes.switcher' ] );
        }
	}

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
            $themeOptions[ wfMessage( "theme-$theme" )->text() ] = $theme;
        }

        $preferences[self::getThemePreferenceName()] = [
            'label-message' => 'themetoggle-user-preference-label',
            'type' => 'select',
            'options' => $themeOptions,
            'section' => 'rendering/skin/skin-prefs'
        ];
	}

    private function registerThemeModule( ResourceLoader $resourceLoader, string $id ): void {
        $resourceLoader->register( 'ext.theme.' . $id, [
			'class' => WikiThemeResourceLoaderModule::class,
			'id' => $id
		] );
    }
    
	public function onResourceLoaderRegisterModules( ResourceLoader $resourceLoader ): void {
        /* This is a stub, ideally there'd be a definitions page unless there's some more clever way */
        global $wgThemeToggleSiteCssBundled,
            $wgThemeToggleSwitcherStyle;
        
        $messages = [];

        foreach ( ThemeDefinitions::get()->getIds() as $theme ) {
            $messages[] = "theme-$theme";
            if ( !in_array( $theme, $wgThemeToggleSiteCssBundled ) ) {
                $this->registerThemeModule( $resourceLoader, $theme );
            }
        }

        if ( $this->getSwitcherModuleId() !== null ) {
            $resourceLoader->register( 'ext.themes.switcher', [
			    'class' => ResourceLoaderFileModule::class,
		        'localBasePath' => 'extensions/ThemeToggle/modules',
		        'remoteExtPath' => 'extensions/ThemeToggle/modules',
                'dependencies' => [ 'ext.themes.baseSwitcher' ],
                'targets' => [ 'desktop', 'mobile' ]
		    ] + $this->getSwitcherModuleDefinition( $this->getSwitcherModuleId() ) );
        }

        $resourceLoader->register( 'ext.themes.siteMessages', [
			'class' => ResourceLoaderFileModule::class,
			'messages' => $messages
		] );
	}
}