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
    public function onPageSaveComplete(
        $wikiPage,
        $userIdentity,
        $summary,
        $flags,
        $revisionRecord,
        $editResult
    ): void {
        ThemeAndFeatureRegistry::get()->handlePageUpdate( $wikiPage->getTitle() );
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
        ThemeAndFeatureRegistry::get()->handlePageUpdate( TitleValue::newFromPage( $page ) );
    }
}
