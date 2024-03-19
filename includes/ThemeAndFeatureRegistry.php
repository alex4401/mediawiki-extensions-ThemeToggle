<?php
namespace MediaWiki\Extension\ThemeToggle;

use MediaWiki\Linker\LinkTarget;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use ObjectCache;
use TextContent;
use Title;
use User;
use WANObjectCache;
use Wikimedia\Rdbms\Database;
use InvalidArgumentException;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ThemeToggle\Data\ThemeInfo;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;

class ThemeAndFeatureRegistry {
    public const SERVICE_NAME = 'ThemeToggle.ThemeAndFeatureRegistry';

    /**
     * @internal Use only in ServiceWiring
     */
    public const CONSTRUCTOR_OPTIONS = [
        ConfigNames::DefaultTheme,
        ConfigNames::DisableAutoDetection,
    ];

    public const CACHE_GENERATION = 8;
    public const CACHE_TTL = 24 * 60 * 60;
    public const TITLE = 'Theme-definitions';

    /** @var ServiceOptions */
    private ServiceOptions $options;

    /** @var ExtensionConfig */
    private ExtensionConfig $config;

    /** @var RevisionLookup */
    private RevisionLookup $revisionLookup;

    /** @var UserOptionsLookup */
    private UserOptionsLookup $userOptionsLookup;

    /** @var UserGroupManager */
    private UserGroupManager $userGroupManager;

    /** @var WANObjectCache */
    private WANObjectCache $wanObjectCache;

    public function __construct(
        ServiceOptions $options,
        ExtensionConfig $config,
        RevisionLookup $revisionLookup,
        UserOptionsLookup $userOptionsLookup,
        UserGroupManager $userGroupManager,
        WANObjectCache $wanObjectCache
    ) {
        $options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
        $this->options = $options;

        $this->config = $config;
        $this->revisionLookup = $revisionLookup;
        $this->userOptionsLookup = $userOptionsLookup;
        $this->userGroupManager = $userGroupManager;
        $this->wanObjectCache = $wanObjectCache;
    }

    protected ?array $ids = null;
    protected ?array $infos = null;

    public function getIds(): array {
        $this->load();
        return $this->ids;
    }

    public function getAll(): array {
        $this->load();
        return $this->infos;
    }

    /**
     * @param UserIdentity $user
     * @return array
     */
    public function getAvailableForUser( UserIdentity $userIdentity ): array {
        $this->load();

        // End early if no configured theme needs special user groups
        if ( empty( array_filter( $this->infos, fn ( $item ) => empty( $item->getEntitledUserGroups() ) ) ) ) {
            return $this->infos;
        }

        $userGroups = $this->userGroupManager->getUserEffectiveGroups( $userIdentity );
        return array_filter( $this->infos, static function ( $info ) use ( &$userGroups ) {
            if ( empty( $info->getEntitledUserGroups() ) ) {
                return true;
            }
            return !empty( array_intersect( $info->getEntitledUserGroups(), $userGroups ) );
        } );
    }

    public function get( string $id ): ?ThemeInfo {
        return $this->infos[$id] ?? null;
    }

    public function isEligibleForAuto(): bool {
        if ( $this->options->get( ConfigNames::DisableAutoDetection ) ) {
            return false;
        }

        $this->load();
        return in_array( 'dark', $this->getIds() ) && in_array( 'light', $this->getIds() );
    }

    public function getDefaultThemeId(): string {
        $this->load();

        // Return the config variable if non-null and found in definitions
        $configDefault = $this->options->get( ConfigNames::DefaultTheme );
        if ( $configDefault !== null && in_array( $configDefault, $this->ids ) ) {
            return $configDefault;
        }

        // Search for the first theme with the `default` flag
        $default = null;
        foreach ( $this->infos as $id => $info ) {
            if ( $info->isDefault() ) {
                $default = $id;
                break;
            }
        }

        if ( $default !== null ) {
            return $default;
        }

        // If none found and dark and light themes are defined, return auto
        if ( $this->isEligibleForAuto() ) {
            return 'auto';
        }

        // Otherwise return the first defined theme
        return $this->ids[0];
    }

    public function getForUser( User $user ) {
        $result = $this->getDefaultThemeId();
        // Retrieve user's preference
        if ( !$user->isAnon() ) {
            $result = $this->userOptionsLookup->getOption( $user, $this->config->getThemePreferenceName(), $result );
        }
        return $result;
    }

    public function hasNonBundledThemes(): bool {
        $this->load();

        foreach ( $this->infos as $info ) {
            if ( !$info->isBundled() ) {
                return true;
            }
        }
        return false;
    }

    public function getBundledThemeIds(): array {
        $this->load();

        return array_keys( array_filter( $this->infos, static function ( $info ) {
            return $info->isBundled();
        } ) );
    }

    public function purgeCache(): void {
        $srvCache = ObjectCache::getLocalServerInstance( 'hash' );
        $key = $this->makeDefinitionCacheKey( $this->wanObjectCache );

        $this->wanObjectCache->delete( $key );
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
            $srvCache = ObjectCache::getLocalServerInstance( 'hash' );
            $key = $this->makeDefinitionCacheKey( $this->wanObjectCache );
            // Between 7 and 15 seconds to avoid memcached/lockTSE stampede (T203786)
            $srvCacheTtl = mt_rand( 7, 15 );
            $options = $srvCache->getWithSetCallback( $key, $srvCacheTtl, function () use ( $key ) {
                    return $this->wanObjectCache->getWithSetCallback(
                        $key, self::CACHE_TTL,
                        function ( $old, &$ttl, &$setOpts ) {
                            // Reduce caching of known-stale data (T157210)
                            $setOpts += Database::getCacheSetOptions( wfGetDB( DB_REPLICA ) );
                            return $this->fetchDefinitionList();
                        }, [
                            // Avoid database stampede
                            'lockTSE' => 300,
                        ]
                    );
            } );

            // Construct ThemeInfo objects
            $this->infos = array_map( fn ( $info ) => new ThemeInfo( $info ), $options );
            $this->ids = array_keys( $this->infos );
        }
    }

    private function fetchDefinitionList(): array {
        $revision = $this->revisionLookup->getRevisionByTitle( Title::makeTitle( NS_MEDIAWIKI, self::TITLE ) );
        $text = null;
        $useMessageFallback = !$revision || !$revision->getContent( SlotRecord::MAIN )
            || $revision->getContent( SlotRecord::MAIN )->isEmpty();

        if ( $useMessageFallback ) {
            $text = wfMessage( self::TITLE )->inLanguage( 'en' )->plain();
        } else {
            $content = $revision->getContent( SlotRecord::MAIN );
            $text = ( $content instanceof TextContent ) ? $content->getText() : '';
        }

        $definition = preg_replace( '/<!--.*?-->/s', '', $text );
        $lines = preg_split( '/(\r\n|\r|\n)+/', $definition );

        $themes = [];

        foreach ( $lines as $line ) {
            try {
                $themeInfo = $this->newOptionsFromText( $line );
                if ( $themeInfo ) {
                    $themes[$themeInfo['id']] = $themeInfo;
                }
            } catch ( InvalidArgumentException $ex ) {
                continue;
            }
        }

        if ( empty( $themes ) ) {
            // This should match default Theme-definitions message
            $themes = [
                'none' => [
                    'id' => 'none',
                    'default' => true,
                    'bundled' => true
                ]
            ];
        }

        return $themes;
    }

    /**
     * @param string $definition
     * @return ?array
     */
    private function newOptionsFromText( string $definition ): ?array {
        $match = [];
        if ( !preg_match(
            '/^\*+ *([a-zA-Z](?:[-_:.\w ]*[a-zA-Z0-9])?)(\s*\[.*?\])?\s*$/',
            $definition,
            $match
        ) ) {
            return null;
        }

        $info = [
            'id' => trim( str_replace( ' ', '_', $match[1] ) )
        ];

        if ( isset( $match[2] ) ) {
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
                    case 'user-groups':
                        $info['userGroups'] = $params;
                        break;
                    case 'default':
                        $info['default'] = true;
                        break;
                    case 'bundled':
                        $info['bundled'] = true;
                        break;
                }
            }
        }

        return $info;
    }
}
