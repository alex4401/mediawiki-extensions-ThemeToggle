<?php
namespace MediaWiki\Extension\ThemeToggle\Data;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;

class ThemeInfo {
    private string $id;
    private array $rights = [];
    private bool $default = false;
    private bool $bundled = false;

    public function __construct( array $info ) {
        foreach ( $info as $option => $params ) {
            switch ( $option ) {
                case 'id':
                case 'rights':
                case 'default':
                case 'bundled':
                    $this->{$option} = $params;
                    break;
                default:
                    throw new InvalidArgumentException( "Unrecognized '$option' parameter" );
            }
        }
    }

    public function getId(): string {
        return $this->id;
    }

    public function getMessageId(): string {
        return 'theme-' . $this->id;
    }

    public function isBundled(): bool {
        return $this->bundled;
    }

    public function isDefault(): bool {
        return $this->default;
    }

    public function getRequiredUserRights(): array {
        return $this->rights;
    }

    public function isUserAllowedToUse( Authority $user ): bool {
        if ( count( $this->rights ) ) {
            return $user->isAllowedAll( ...$this->rights );
        }
        return true;
    }
}
