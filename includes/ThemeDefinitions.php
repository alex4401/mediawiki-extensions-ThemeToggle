<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use InvalidArgumentException;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use ObjectCache;
use TextContent;
use Title;
use WANObjectCache;
use Wikimedia\Rdbms\Database;

class ThemeDefinitions {
	const CACHE_GENERATION = 1;
	const CACHE_TTL = 24*60*60;
	const TITLE = 'Theme-definitions';

	protected string $titlePrefix = 'MediaWiki:Theme-';

	private static $instance = null;

	public static function get() {
		if ( self::$instance === null ) {
			self::$instance = new ThemeDefinitions();
		}
		return self::$instance;
	}

	public function getIds(): array {
		return $this->load();
	}

	public function handlePageUpdate( LinkTarget $target ): void {
		if ( $target->getNamespace() === NS_MEDIAWIKI && $target->getText() == self::TITLE ) {
			$this->purgeDefinitionCache();
		}
	}

	private function purgeCache(): void {
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$srvCache = ObjectCache::getLocalServerInstance( 'hash' );
		$key = $this->makeDefinitionCacheKey( $wanCache );

		$wanCache->delete( $key );
		$srvCache->delete( $key );
	}

	private function makeDefinitionCacheKey( WANObjectCache $cache ): string {
		return $cache->makeKey(
			'theme-definitions',
			self::CACHE_GENERATION
		);
	}

	protected function load(): array {
		// From back to front:
		//
		// 2. wan cache (e.g. memcached)
		//    This improves end-user latency and reduces database load.
		//    It is purged when the data changes.
		//
		// 1. server cache (e.g. APCu).
		//    Very short blind TTL, mainly to avoid high memcached I/O.
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$srvCache = ObjectCache::getLocalServerInstance( 'hash' );
		$key = $this->makeDefinitionCacheKey( $wanCache );
		// Between 7 and 15 seconds to avoid memcached/lockTSE stampede (T203786)
		$srvCacheTtl = mt_rand( 7, 15 );
		return $srvCache->getWithSetCallback( $key, $srvCacheTtl, function () use ( $wanCache, $key ) {
				return $wanCache->getWithSetCallback( $key, self::CACHE_TTL, function ( $old, &$ttl, &$setOpts ) {
						// Reduce caching of known-stale data (T157210)
						$setOpts += Database::getCacheSetOptions( wfGetDB( DB_REPLICA ) );
						return $this->fetchStructuredList();
					}, [
						// Avoid database stampede
						'lockTSE' => 300,
					] );
		} );
	}

	public function fetchStructuredList() {
		$revision = MediaWikiServices::getInstance()
			->getRevisionLookup()
			->getRevisionByTitle( Title::makeTitle( NS_MEDIAWIKI, self::TITLE ) );
		$text = null;
		if ( !$revision
			|| !$revision->getContent( SlotRecord::MAIN )
			|| $revision->getContent( SlotRecord::MAIN )->isEmpty()
		) {
			$text = wfMessage( self::TITLE )->plain();
		} else {
			$content = $revision->getContent( SlotRecord::MAIN );
			$text = ( $content instanceof TextContent ) ? $content->getText() : '';
		}


		$themes = $this->listFromDefinition( $text );

		return $themes;
	}

	private function listFromDefinition( $definition ) {
		$definition = preg_replace( '/<!--.*?-->/s', '', $definition );
		$lines = preg_split( '/(\r\n|\r|\n)+/', $definition );

		$themes = [];

		foreach ( $lines as $line ) {
			$theme = $this->newFromText( $line );
			if ( $theme ) {
				$themes[] = $theme;
			}
		}

		return $themes;
	}

	public function newFromText( $definition ) {
		$m = [];
		if ( !preg_match(
			'/^\*+ *([a-zA-Z](?:[-_:.\w ]*[a-zA-Z0-9])?)(\s*\[.*?\])?\s*$/',
			$definition,
			$m
		) ) {
			return false;
		}

		return trim( str_replace( ' ', '_', $m[1] ) );
	}
}
