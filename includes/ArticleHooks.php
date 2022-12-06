<?php
namespace MediaWiki\Extension\ThemeToggle;

use ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use TitleValue;

class ArticleHooks implements
    \MediaWiki\Page\Hook\PageDeleteCompleteHook,
    \MediaWiki\Storage\Hook\PageSaveCompleteHook
{

    public function onPageSaveComplete( $wikiPage, $userIdentity, $summary, $flags, $revisionRecord, $editResult ): void {
        ThemeAndFeatureRegistry::get()->handlePageUpdate( $wikiPage->getTitle() );
    }

    public function onPageDeleteComplete( $page, Authority $deleter, string $reason, int $pageID, RevisionRecord $deletedRev,
        ManualLogEntry $logEntry, int $archivedRevisionCount ): void {
        ThemeAndFeatureRegistry::get()->handlePageUpdate( TitleValue::newFromPage( $page ) );
    }
}
