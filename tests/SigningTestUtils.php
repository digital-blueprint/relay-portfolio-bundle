<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\Tests;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class SigningTestUtils
{
    public static function jobDescription(): array
    {
        $user = ['@class' => 'com.example.api.User', 'classifier' => 'EMAIL', 'name' => 'owner@example.com'];

        return [
            'constituent' => $user,
            'positionType' => 'SIGNATURE_PAGE',
            'iterationData' => [
                ['iterationNumber' => 0, 'category' => 'APPROVAL', 'invitees' => [[...$user, 'name' => 'approver@example.com']]],
                ['iterationNumber' => 1, 'category' => 'EXTERNAL_APPROVAL', 'invitees' => [[...$user, '@class' => 'com.example.api.ExternalUser', 'name' => 'guest@example.com', 'externalUserName' => 'Guest', 'locale' => 'de']]],
            ],
        ];
    }

    public static function pdf(string $filename = 'contract.pdf'): UploadedFile
    {
        return new UploadedFile(__DIR__.'/../src/Workflow/Signing/Resources/signed-document.pdf', $filename, 'application/pdf', null, true);
    }
}
