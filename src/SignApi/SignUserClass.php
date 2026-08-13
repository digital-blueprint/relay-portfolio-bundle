<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * The user classes (data models), identified by the suffix of the
 * fully-qualified `@class` field of a user object, e.g. the `@class`
 * "com.example.api.ExternalUser" maps to SignUserClass::EXTERNAL_USER.
 *
 * Different classes imply different data models. Simpler models are applicable
 * when placeholder-visualization is not required; the *_FOR_ROLE /
 * *_FOR_SIGNATURE_FIELD variants are mandated when it is.
 */
enum SignUserClass: string
{
    /** Internal users. */
    case USER = 'User';
    /** External users. */
    case EXTERNAL_USER = 'ExternalUser';
    /** External approvers (deprecated). */
    case EXTERNAL_APPROVER = 'ExternalApprover';
    /** Signature fields. */
    case USER_FOR_SIGNATURE_FIELD = 'UserForSignatureField';
    /**
     * Internal users; mandated if placeholder-visualization is used.
     */
    case USER_FOR_ROLE = 'UserForRole';
    /**
     * External users, exclusively for qualified signatures;
     * mandated if placeholder-visualization is used.
     */
    case EXTERNAL_USER_FOR_ROLE = 'ExternalUserForRole';
    /**
     * Mandated if placeholder-visualization is used, exclusively used for the
     * approval type category (deprecated).
     */
    case EXTERNAL_APPROVER_FOR_ROLE = 'ExternalApproverForRole';

    /**
     * Resolves the user class from a fully-qualified `@class` string by matching
     * its suffix, e.g. "com.example.api.ExternalUser".
     *
     * Returns null if the suffix does not correspond to a known class.
     */
    public static function fromClassString(string $class): ?self
    {
        foreach (self::cases() as $case) {
            if (str_ends_with($class, '.'.$case->value)) {
                return $case;
            }
        }

        return null;
    }
}
