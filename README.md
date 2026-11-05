# DbpRelayPortfolioBundle

## Signing workflow state

The internal signing state uses schema version 1. Compatibility with earlier
internal state layouts is not supported yet. The external signing API's field
names are unchanged.

The snapshot has two parts:

- `job` is the immutable, normalized job definition: `signingProcessId`,
  constituent, metadata, original document, attachments, and iteration definitions.
- `state` contains mutable execution results and lifecycle facts: iteration task
  references and signed documents, cancellation, expiration, and retrieval.

Iteration definitions in `job.iterations` contain `number`, `kind`, and
`participants`. The internal kinds `internal_approval` and `external_signing` are
translated into API categories only by `SignService`.

Each `state.iterations` record has the matching `number`, its `tasks`, and its
`signedDocument`. Each task links a generated `taskId` to the zero-based
`participantIndex` in that iteration's immutable participant list. Iteration
numbers and task IDs must be unique, and every participant has exactly one task
reference. Results are matched by iteration number, not by array position.

Documents consistently use `{fileId, filename}` references:

- `job.originalDocument` is the original uploaded PDF.
- `job.attachments` contains unsigned supporting documents.
- Each execution iteration's `signedDocument` is `null` until completed, then contains its
  output PDF reference. The current document is derived from completed iterations.

Identity fields have the following meanings:

- `job.constituent` is the requester, not necessarily a signer. Its email, internal
  person ID and display name are captured at creation in an identity object
  containing `email`, `userId`, and `displayName`.
- `state.cancelledBy` uses the same identity object, captured on cancellation, and is
  `null` before cancellation. Neither identity carries participant task IDs or locale.
- A participant's `userId` is its resolved internal person ID; `null` explicitly
  denotes an external signer, never an unresolved internal user.
- Email and display name are creation-time snapshots. `locale` is an optional
  external signer preference, not an identity attribute.

Signing status is derived, not stored. Cancellation (`cancelledAt`) takes
precedence over recorded expiration (`expiredAt`); otherwise, all iterations
having a `signedDocument` means completion. Remaining jobs are active. The
handler prevents terminal transitions from overwriting each other, and only
`SignService` translates these facts into signing API status values.

An elapsed `job.expirationDate` blocks signing immediately but does not itself change
the recorded status. The timeout check records `expiredAt` and closes the workflow
in one transaction. Successful jobs remain open until their final document is
retrieved. `cancelledAt` records the first cancellation in UTC and stays unchanged
on retries, together with the cancellation actor. `lastRetrievedAt` records the
most recent final-document retrieval. Existing database completion timestamps
are not duplicated in this state.

Actions and timeout checks reload workflow state under a database write lock
inside their transaction. Duplicate signature actions are rejected without
uploading another document; cancellation is idempotent and retrieval is
repeatable. Signing after the deadline is rejected even before a timeout check
runs, and expiration cannot overwrite a final signing status. The manual expire
action records expiration without changing the original job deadline.
