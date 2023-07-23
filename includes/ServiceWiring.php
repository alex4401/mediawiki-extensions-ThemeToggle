<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ThemeToggle\ExtensionConfig;
use MediaWiki\MediaWikiServices;

return [
    ExtensionConfig::SERVICE_NAME => static function (
        MediaWikiServices $services
    ): ExtensionConfig {
        return new ExtensionConfig(
            new ServiceOptions(
                ExtensionConfig::CONSTRUCTOR_OPTIONS,
                $services->getMainConfig()
            )
        );
    },
];
