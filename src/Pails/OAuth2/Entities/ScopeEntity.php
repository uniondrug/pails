<?php
/**
 * ScopeEntity.php
 */
namespace Pails\OAuth2\Entities;

use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait;

    public function jsonSerialize()
    {
        return json_encode($this->identifier);
    }
}
