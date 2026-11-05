# DbpRelayPortfolioBundle

## Signing workflow state

The internal signing state uses schema version 1. Compatibility with earlier
internal state layouts is not supported yet. The external signing API's field
names are unchanged.

Documents consistently use `{fileId, filename}` references:

- `documentToSign` is the original uploaded PDF.
- `attachments` contains unsigned supporting documents.
- Each iteration's `signedDocument` is `null` until completed, then contains its
  output PDF reference. The current document is derived from completed iterations.

Identity fields have the following meanings:

- `constituent` is the requester, not necessarily a signer. Its email, internal
  person ID and display name are captured at creation.
- An invitee's `id` is also its task ID, unique within the workflow.
- An invitee's `userId` is its resolved internal person ID; `null` explicitly
  denotes an external signer, never an unresolved internal user.
- Email and display name are creation-time snapshots. `locale` is an optional
  external signer preference, not an identity attribute.

Signing status is derived, not stored. Cancellation (`cancelledAt`) takes
precedence over recorded expiration (`expiredAt`); otherwise, all iterations
having a `signedDocument` means completion. Remaining jobs are active. The
handler prevents terminal transitions from overwriting each other, and only
`SignService` translates these facts into signing API status values.

An elapsed `expirationDate` blocks signing immediately but does not itself change
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
runs, and expiration cannot overwrite a final signing status.
