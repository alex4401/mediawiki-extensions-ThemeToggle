<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;

class ArticleHooks implements
    \MediaWiki\Page\Hook\PageDeleteCompleteHook,
    \MediaWiki\Storage\Hook\PageSaveCompleteHook {

    public function onPageSaveComplete( $wikiPage, $userIdentity, $summary, $flags, $revisionRecord, $editResult ): void {
        ThemeDefinitions::get()->handlePageUpdate( $wikiPage->getTitle() );
    }
    
    public function onPageDeleteComplete( $page, Authority $deleter, string $reason, int $pageID, RevisionRecord $deletedRev,
        ManualLogEntry $logEntry, int $archivedRevisionCount ): void {
        ThemeDefinitions::get()->handlePageUpdate( TitleValue::newFromPage( $page ) );
    }
}