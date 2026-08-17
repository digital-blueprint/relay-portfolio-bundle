<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * Verifies the HTTP Basic credentials the signature client sends on every
 * Sign request and enforces per-process access control.
 *
 * The signature client can only ever send HTTP Basic auth, so this is the single
 * credential gate for the Sign endpoints. The known API users and the per-process
 * admin lists come from the bundle configuration (dbp_relay_portfolio.sign_api).
 */
class SignCredentials
{
    /**
     * Map of username => password hash (as configured).
     *
     * @var array<string, string>
     */
    private array $users = [];

    /**
     * Map of processId => list of admin usernames.
     *
     * @var array<string, list<string>>
     */
    private array $processAdmins = [];

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $signApi = $config['sign_api'] ?? [];

        $this->users = [];
        foreach ($signApi['api_users'] ?? [] as $username => $apiUser) {
            $passwordHash = $apiUser['password_hash'] ?? null;
            if (is_string($username) && is_string($passwordHash)) {
                $this->users[$username] = $passwordHash;
            }
        }

        $this->processAdmins = [];
        foreach ($signApi['processes'] ?? [] as $processId => $process) {
            $admins = $process['admins'] ?? [];
            $this->processAdmins[$processId] = array_values(array_filter(
                $admins,
                static fn ($admin): bool => is_string($admin),
            ));
        }
    }

    /**
     * Authenticates the given credentials against the configured API users and
     * returns the matched username, or null if the credentials are invalid.
     *
     * Uses password_verify to compare the provided password against the stored
     * hash, which is inherently constant-time to avoid leaking timing information.
     * Access is denied if the request did not provide a username/password, or if
     * the username is unknown.
     */
    public function authenticate(?string $username, ?string $password): ?string
    {
        if ($username === null || $password === null) {
            return null;
        }

        $expectedHash = $this->users[$username] ?? null;
        if ($expectedHash === null) {
            return null;
        }

        return password_verify($password, $expectedHash) ? $username : null;
    }

    /**
     * Returns true if the given username is allowed to use the given process.
     *
     * A user is allowed if they are listed in the process' admins. Unknown
     * processes (not present in the configuration) deny access.
     */
    public function isProcessAdmin(string $username, string $processId): bool
    {
        return in_array($username, $this->processAdmins[$processId] ?? [], true);
    }
}
