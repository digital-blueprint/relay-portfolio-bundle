<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * The signature category values carried by an iteration's "category" field.
 *
 * The category determines which type of signature is applied. It can be
 * assigned differently per iteration, and role-restrictions may apply.
 */
enum SignCategory: string
{
    /** Qualified signature, internal signer. */
    case QSIG = 'QSIG';
    /** Qualified signature, external signer. */
    case EXTERNAL_QSIG = 'EXTERNAL_QSIG';
    /** Simple (SES or SES+) signature, internal signer. */
    case APPROVAL = 'APPROVAL';
    /** Simple (SES or SES+) signature, external signer. */
    case EXTERNAL_APPROVAL = 'EXTERNAL_APPROVAL';
    /** Just workflow driven, no signature. */
    case APPROVAL_NOSIG = 'APPROVAL_NOSIG';
}
