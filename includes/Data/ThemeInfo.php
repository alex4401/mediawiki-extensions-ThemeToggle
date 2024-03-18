<?php
namespace MediaWiki\Extension\ThemeToggle\Data;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;

class ThemeInfo {
    /** @var string */
    private string $id;
    /** @var string[] Required rights to advertise. */
    private array $rights = [];
    /** @var bool Whether default. */
    private bool $default = false;
    /** @var bool Whether included in site CSS. */
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

    /**
     * @return string
     */
    public function getId(): string {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getMessageId(): string {
        return 'theme-' . $this->id;
    }

    /**
     * @return string
     */
    public function getCssPageName(): string {
        return 'MediaWiki:Theme-' . $this->id . '.css';
    }

    /**
     * @return bool Whether included in site CSS.
     */
    public function isBundled(): bool {
        return $this->bundled;
    }

    /**
     * @return bool
     */
    public function isDefault(): bool {
        return $this->default;
    }

    /**
     * @return string[]
     */
    public function getRequiredUserRights(): array {
        return $this->rights;
    }

    /**
     * Serialise this gadget to an array.
     *
     * @return array
     */
    public function toArray(): array {
        return [
            'id' => $this->id,
            'default' => $this->default,
            'bundled' => $this->bundled,
            'rights' => $this->rights,
        ];
    }
}
