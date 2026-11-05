<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Dbp\Relay\PortfolioBundle\Workflow\Signing\State\SigningIdentity;
use PHPUnit\Framework\TestCase;

class SigningIdentityTest extends TestCase
{
    public function testJsonRoundTrip(): void
    {
        $identity = new SigningIdentity('owner@example.com', 'owner-id', 'Owner');
        $data = json_decode(json_encode($identity->toArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['email' => 'owner@example.com', 'userId' => 'owner-id', 'displayName' => 'Owner'], $data);
        self::assertEquals($identity, SigningIdentity::fromArray($data));
    }

    public function testDisplayNameIsOptional(): void
    {
        $identity = SigningIdentity::fromArray(['email' => 'owner@example.com', 'userId' => 'owner-id']);

        self::assertNull($identity->displayName);
        self::assertEquals($identity, SigningIdentity::fromArray($identity->toArray()));
    }
}
