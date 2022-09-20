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
	const CACHE_GENERATION = 2;
	const CACHE_TTL = 24*60*60;
	const TITLE = 'Theme-definitions';

	protected string $titlePrefix = 'MediaWiki:Theme-';
	protected ?array $ids = null;
	protected ?array $infos = null;

	private static $instance = null;

	public static function get() {
		if ( self::$instance === null ) {
			self::$instance = new ThemeDefinitions();
		}
		return self::$instance;
	}

	public function getIds(): array {
		$this->load();
		return $this->ids;
	}

	public function getAll(): array {
		$this->load();
		return $this->infos;
	}

	public function isEligibleForAuto(): bool {
		global $wgThemeToggleDisableAutoDetection;
		if ( $wgThemeToggleDisableAutoDetection ) {
			return false;
		}
		$this->load();
		return in_array( 'dark', $this->getIds() ) && in_array( 'light', $this->getIds() );
	}

	public function handlePageUpdate( LinkTarget $target ): void {
		if ( $target->getNamespace() === NS_MEDIAWIKI && $target->getText() == self::TITLE ) {
			$this->purgeCache();
		}
	}

	private function purgeCache(): void {
		$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
		$srvCache = ObjectCache::getLocalServerInstance( 'hash' );
		$key = $this->makeDefinitionCacheKey( $wanCache );

		$wanCache->delete( $key );
		$srvCache->delete( $key );

		$this->ids = null;
		$this->infos = null;
	}

	private function makeDefinitionCacheKey( WANObjectCache $cache ): string {
		return $cache->makeKey( 'theme-definitions', self::CACHE_GENERATION );
	}

	protected function load(): void {
		// From back to front:
		//
		// 3. wan cache (e.g. memcached)
		//    This improves end-user latency and reduces database load.
		//    It is purged when the data changes.
		//
		// 2. server cache (e.g. APCu).
		//    Very short blind TTL, mainly to avoid high memcached I/O.
		//
		// 1. process cache
		if ( $this->infos === null ) {
			$wanCache = MediaWikiServices::getInstance()->getMainWANObjectCache();
			$srvCache = ObjectCache::getLocalServerInstance( 'hash' );
			$key = $this->makeDefinitionCacheKey( $wanCache );
			// Between 7 and 15 seconds to avoid memcached/lockTSE stampede (T203786)
			$srvCacheTtl = mt_rand( 7, 15 );
			$this->infos = $srvCache->getWithSetCallback( $key, $srvCacheTtl, function () use ( $wanCache, $key ) {
					return $wanCache->getWithSetCallback( $key, self::CACHE_TTL, function ( $old, &$ttl, &$setOpts ) {
							// Reduce caching of known-stale data (T157210)
							$setOpts += Database::getCacheSetOptions( wfGetDB( DB_REPLICA ) );
							return $this->fetchStructuredList();
						}, [
							// Avoid database stampede
							'lockTSE' => 300,
						] );
			} );

			$this->ids = array_keys( $this->infos );
		}
	}

	public function fetchStructuredList(): array {
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

	private function listFromDefinition( $definition ): array {
		$definition = preg_replace( '/<!--.*?-->/s', '', $definition );
		$lines = preg_split( '/(\r\n|\r|\n)+/', $definition );

		$themes = [];

		foreach ( $lines as $line ) {
			$themeInfo = $this->newFromText( $line );
			if ( $themeInfo ) {
				$themes[$themeInfo->getId()] = $themeInfo;
			}
		}

		return $themes;
	}

	public function newFromText( $definition ) {
		$match = [];
		if ( !preg_match(
			'/^\*+ *([a-zA-Z](?:[-_:.\w ]*[a-zA-Z0-9])?)(\s*\[.*?\])?\s*$/',
			$definition,
			$match
		) ) {
			return false;
		}

		$info = [
			'id' => trim( str_replace( ' ', '_', $match[1] ) )
		];

		$options = trim( $match[2], ' []' );
		foreach ( preg_split( '/\s*\|\s*/', $options, -1, PREG_SPLIT_NO_EMPTY ) as $option ) {
			$arr = preg_split( '/\s*=\s*/', $option, 2 );
			$option = $arr[0];
			if ( isset( $arr[1] ) ) {
				$params = explode( ',', $arr[1] );
				$params = array_map( 'trim', $params );
			} else {
				$params = [];
			}

			switch ( $option ) {
				case 'rights':
					$info['rights'] = $params;
					break;
			}
		}

		return new ThemeInfo( $info );
	}
}
