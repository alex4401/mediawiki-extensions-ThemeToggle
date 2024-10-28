<?php
namespace MediaWiki\Extension\ThemeToggle\ResourceLoader;

use MediaWiki\Config\Config;
use MediaWiki\Extension\ThemeToggle\ExtensionConfig;
use MediaWiki\Extension\ThemeToggle\ThemeAndFeatureRegistry;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\FileModule;

class SharedJsModule extends FileModule {
    /**
     * Get message keys used by this module.
     *
     * @return string[] List of message keys
     */
    public function getMessages() {
        $messages = [];
        $registry = MediaWikiServices::getInstance()->getService( ThemeAndFeatureRegistry::SERVICE_NAME );

        if ( $registry->isEligibleForAuto() ) {
            $messages[] = 'theme-auto';
        }

        foreach ( $registry->getAll() as $themeId => $themeInfo ) {
            $messages[] = $themeInfo->getMessageId();
        }

        return array_merge( $this->messages, $messages );
    }

    public function enableModuleContentVersion(): bool {
        // Enabling this means that ResourceLoader::getVersionHash will simply call getScript()
        // and hash it to determine the version (as used by E-Tag HTTP response header).
        return true;
    }

    public static function makeConfigArray( Context $context, Config $config ): array {
        /** @var ExtensionConfig */
        $config = MediaWikiServices::getInstance()->getService( ExtensionConfig::SERVICE_NAME );
        /** @var ThemeAndFeatureRegistry */
        $registry = MediaWikiServices::getInstance()->getService( ThemeAndFeatureRegistry::SERVICE_NAME );

        return [
            'themes' => array_map(
                static function ( $key, $info ) {
                    if ( $info->getEntitledUserGroups() ) {
                        return [
                            'id' => $key,
                            'userGroups' => $info->getEntitledUserGroups(),
                        ];
                    }
                    return $key;
                },
                array_keys( $registry->getAll() ),
                array_values( $registry->getAll() )
            ),
            'supportsAuto' => $registry->isEligibleForAuto(),
            'preferenceGroup' => $config->getPreferenceSuffix(),
            'defaultTheme' => $registry->getDefaultThemeId()
        ];
    }
}
