<?php
namespace MediaWiki\Extension\ThemeToggle\Data;

use InvalidArgumentException;

class FeatureInfo {
    private string $id;
    private bool $default = false;

    public function __construct( array $info ) {
        foreach ( $info as $option => $params ) {
            switch ( $option ) {
                case 'id':
                case 'default':
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
        return 'theme-feature-' . $this->id;
    }

    public function isDefault(): bool {
        return $this->default;
    }
}
