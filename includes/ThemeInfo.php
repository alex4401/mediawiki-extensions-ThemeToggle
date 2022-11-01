<?php
namespace MediaWiki\Extension\Ark\ThemeToggle;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;

class ThemeInfo {
    private string $id;
    private array $rights = [];

    public function __construct( array $info ) {
        foreach ( $info as $option => $params ) {
            switch ( $option ) {
                case 'id':
                case 'rights':
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
