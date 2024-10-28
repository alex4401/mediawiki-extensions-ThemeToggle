<?php

namespace MediaWiki\Extension\ThemeToggle\Repository;

use MediaWiki\Title\Title;

interface ThemeDefinitionsSource {
    public function load(): ?array;
    public function doesTitleInvalidateCache( Title $title ): bool;
}
