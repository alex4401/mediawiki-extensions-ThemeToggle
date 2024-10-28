<?php
namespace MediaWiki\Extension\ThemeToggle;

use BagOStuff;
use WANObjectCache;
use Wikimedia\Rdbms\Database;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ThemeToggle\Data\ThemeInfo;
use MediaWiki\Extension\ThemeToggle\Repository\MediaWikiTextThemeDefinitions;
use MediaWiki\Extension\ThemeToggle\Repository\ThemeDefinitionsSource;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
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

    public const CACHE_GENERATION = 12;
    public const CACHE_TTL = 24 * 60 * 60;

    public function __construct(
        private ServiceOptions $options,
        private ExtensionConfig $config,
        private RevisionLookup $revisionLookup,
        private UserOptionsLookup $userOptionsLookup,
        private UserGroupManager $userGroupManager,
        private WANObjectCache $wanObjectCache,
        private BagOStuff $hashCache
    ) {
        $this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
    }

    protected ?array $ids = null;
    protected ?array $infos = null;
    private ?ThemeDefinitionsSource $source = null;

    public function getIds(): array {
        $this->load();
        return $this->ids;
    }

    public function get( string $id ): ?ThemeInfo {
        return $this->infos[$id] ?? null;
    }

    public function getAll(): array {
        $this->load();
        return $this->infos;
    }

    public function makeDefinitionCacheKey(): string {
        return $this->wanObjectCache->makeKey( 'theme-definitions', self::CACHE_GENERATION );
    }

    public function getSource(): ThemeDefinitionsSource {
        if ( $this->source === null ) {
            // TODO: open this up to other sources
            $this->source = new MediaWikiTextThemeDefinitions( $this->revisionLookup );
        }

        return $this->source;
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
        if ( $this->infos !== null ) {
            return;
        }

        $key = $this->makeDefinitionCacheKey();
        // Between 7 and 15 seconds to avoid memcached/lockTSE stampede (T203786)
        $srvCacheTtl = mt_rand( 7, 15 );
        $options = $this->hashCache->getWithSetCallback( $key, $srvCacheTtl, function () use ( $key ) {
                return $this->wanObjectCache->getWithSetCallback(
                    $key,
                    self::CACHE_TTL,
                    function ( $old, &$ttl, &$setOpts ) {
                        // Reduce caching of known-stale data (T157210)
                        $setOpts += Database::getCacheSetOptions( wfGetDB( DB_REPLICA ) );
                        return $this->loadFreshInternal();
                    },
                    [
                        // Avoid database stampede
                        'lockTSE' => 300,
                    ]
                );
        } );

        $this->postLoad();

        // Construct ThemeInfo objects from cachable constructor options
        $this->infos = array_map( fn ( $info ) => new ThemeInfo( $info ), $options );
        $this->ids = array_keys( $this->infos );
    }

    protected function loadFreshInternal(): array {
        return $this->getSource()->load();
    }

    protected function postLoad(): void {
        if ( empty( $options ) ) {
            // This should match default Theme-definitions message
            $this->infos = [
                'none' => [
                    'id' => 'none',
                    'default' => true,
                    'in-site-css' => true,
                    'kind' => 'unknown',
                ],
            ];
        }
    }

    public function handlePagePurge( Title $title ): void {
        if ( $this->getSource()->doesTitleInvalidateCache( $title ) ) {
            $key = $this->makeDefinitionCacheKey();

            $this->wanObjectCache->delete( $key );
            $this->hashCache->delete( $key );
    
            $this->ids = null;
            $this->infos = null;
        }
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

    public function getThemeKinds(): array {
        $this->load();
        return array_map( fn ( $info ) => $info->getKind(), $this->infos );
    }
}
