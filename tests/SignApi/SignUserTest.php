<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests\SignApi;

use Dbp\Relay\PortfolioBundle\SignApi\SignClassifier;
use Dbp\Relay\PortfolioBundle\SignApi\SignException;
use Dbp\Relay\PortfolioBundle\SignApi\SignUser;
use Dbp\Relay\PortfolioBundle\SignApi\SignUserClass;
use PHPUnit\Framework\TestCase;

class SignUserTest extends TestCase
{
    private const USER_CLASS = 'com.example.api.User';
    private const EXTERNAL_USER_CLASS = 'com.example.api.ExternalUser';

    public function testParsesInternalUser(): void
    {
        $user = SignUser::fromArray([
            'classifier' => 'EMAIL',
            'name' => 'max.test@example.com',
            '@class' => self::USER_CLASS,
        ]);

        $this->assertSame(SignUserClass::USER, $user->getClass());
        $this->assertSame(SignClassifier::EMAIL, $user->getClassifier());
        $this->assertSame('max.test@example.com', $user->getName());
        $this->assertNull($user->getRoleName());
        $this->assertNull($user->getExternalUserName());
        $this->assertNull($user->getLocale());
    }

    public function testParsesExternalUserOptionalFields(): void
    {
        $user = SignUser::fromArray([
            '@class' => self::EXTERNAL_USER_CLASS,
            'classifier' => 'EMAIL',
            'name' => 'new.employee@example.com',
            'externalUserName' => 'New Employee',
            'locale' => 'de',
            'roleName' => 'Extern',
        ]);

        $this->assertSame(SignUserClass::EXTERNAL_USER, $user->getClass());
        $this->assertSame('New Employee', $user->getExternalUserName());
        $this->assertSame('de', $user->getLocale());
        $this->assertSame('Extern', $user->getRoleName());
    }

    public function testTrimsLeadingSpaceInName(): void
    {
        // Some clients prepend a space to the email in the cancelJob body.
        $user = SignUser::fromArray([
            '@class' => self::USER_CLASS,
            'classifier' => 'EMAIL',
            'name' => ' owner@example.com',
        ]);

        $this->assertSame('owner@example.com', $user->getName());
    }

    public function testEmptyClassThrows(): void
    {
        $this->expectException(SignException::class);
        SignUser::fromArray([
            '@class' => '',
            'classifier' => 'EMAIL',
            'name' => 'owner@example.com',
        ]);
    }

    public function testParsesUserForRole(): void
    {
        $user = SignUser::fromArray([
            '@class' => 'com.example.api.UserForRole',
            'classifier' => 'EMAIL',
            'name' => 'owner@example.com',
        ]);

        $this->assertSame(SignUserClass::USER_FOR_ROLE, $user->getClass());
    }

    public function testParsesExternalApproverForRole(): void
    {
        $user = SignUser::fromArray([
            '@class' => 'com.example.api.ExternalApproverForRole',
            'classifier' => 'EMAIL',
            'name' => 'guest@example.com',
        ]);

        $this->assertSame(SignUserClass::EXTERNAL_APPROVER_FOR_ROLE, $user->getClass());
    }

    public function testUnsupportedClassThrows(): void
    {
        $this->expectException(SignException::class);
        SignUser::fromArray([
            '@class' => 'com.example.api.NotAUser',
            'classifier' => 'EMAIL',
            'name' => 'owner@example.com',
        ]);
    }

    public function testNonEmailClassifierParsesButIsNotEmail(): void
    {
        $user = SignUser::fromArray([
            '@class' => self::USER_CLASS,
            'classifier' => 'ID',
            'name' => 'owner@example.com',
        ]);

        $this->assertSame(SignClassifier::ID, $user->getClassifier());
    }

    public function testUnsupportedClassifierThrows(): void
    {
        $this->expectException(SignException::class);
        SignUser::fromArray([
            '@class' => self::USER_CLASS,
            'classifier' => 'NOPE',
            'name' => 'owner@example.com',
        ]);
    }

    public function testMissingNameThrows(): void
    {
        $this->expectException(SignException::class);
        SignUser::fromArray([
            '@class' => self::USER_CLASS,
            'classifier' => 'EMAIL',
        ]);
    }
}
