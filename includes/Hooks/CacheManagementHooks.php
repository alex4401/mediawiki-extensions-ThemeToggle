<?php
namespace MediaWiki\Extension\ThemeToggle\Hooks;

use ManualLogEntry;
use MediaWiki\Extension\ThemeToggle\ThemeAndFeatureRegistry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use TitleValue;

class CacheManagementHooks implements
    \MediaWiki\Page\Hook\PageDeleteCompleteHook,
    \MediaWiki\Storage\Hook\PageSaveCompleteHook
{
    /** @var ThemeAndFeatureRegistry */
    private ThemeAndFeatureRegistry $registry;

    public function __construct( ThemeAndFeatureRegistry $registry ) {
        $this->registry = $registry;
    }

    public function onPageSaveComplete(
        $wikiPage,
        $userIdentity,
        $summary,
        $flags,
        $revisionRecord,
        $editResult
    ): void {
        $title = $wikiPage->getTitle();
        if ( $title->getNamespace() === NS_MEDIAWIKI && $title->getText() == ThemeAndFeatureRegistry::TITLE ) {
            $this->registry->purgeCache();
        }
    }

    public function onPageDeleteComplete(
        $page,
        Authority $deleter,
        string $reason,
        int $pageID,
        RevisionRecord $deletedRev,
        ManualLogEntry $logEntry,
        int $archivedRevisionCount
    ): void {
        $title = TitleValue::newFromPage( $page );
        if ( $title->getNamespace() === NS_MEDIAWIKI && $title->getText() == ThemeAndFeatureRegistry::TITLE ) {
            $this->registry->purgeCache();
        }
    }
}
