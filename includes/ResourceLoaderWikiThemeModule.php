<?php

namespace MediaWiki\Extension\Ark\ThemeToggle;

use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Module;
use MediaWiki\ResourceLoader\WikiModule;

class ResourceLoaderWikiThemeModule extends WikiModule {
    private string $id;

    public function __construct( array $options ) {
        $this->id = $options['id'];
    }

    private function getThemeName(): string {
        return $this->id;
    }

    protected function getPages( Context $context ) {
        $theme = $this->getThemeName();
        return [
            "MediaWiki:Theme-$theme.css" => [ 'type' => 'style' ]
        ];
    }

    public function isPackaged(): bool {
        return false;
    }

    public function getType() {
        return Module::LOAD_STYLES;
    }

    public function getTargets() {
        return [ 'desktop', 'mobile' ];
    }

    public function getGroup() {
        return 'site';
    }
}
