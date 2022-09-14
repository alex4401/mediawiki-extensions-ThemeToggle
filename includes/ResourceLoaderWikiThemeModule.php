<?php

namespace MediaWiki\Extension\Ark\ThemeToggle;

use ResourceLoaderContext;
use ResourceLoaderModule;
use ResourceLoaderWikiModule;

class ResourceLoaderWikiThemeModule extends ResourceLoaderWikiModule {
	public function __construct( array $options ) {
		$this->id = $options['id'];
	}

	private function getThemeName(): string {
		return $this->id;
	}

	protected function getPages( ResourceLoaderContext $context ) {
		$theme = $this->getThemeName();
		return [
			"MediaWiki:Theme-$theme.css" => [ 'type' => 'style' ]
		];
	}

	public function isPackaged(): bool {
		return false;
	}

	public function getType() {
		return ResourceLoaderModule::LOAD_STYLES;
	}

	public function getTargets() {
		return [ 'desktop', 'mobile' ];
	}

	public function getGroup() {
		return 'site';
	}
}