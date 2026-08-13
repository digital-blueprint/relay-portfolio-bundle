<?php

declare(strict_types=1);

namespace Dbp\Relay\PortfolioBundle\SignApi;

/**
 * Verifies the HTTP Basic credentials the signature client sends on every
 * Sign request.
 *
 * The signature client can only ever send HTTP Basic auth, so this is the single
 * credential gate for the Sign endpoints. The expected username/password come
 * from the bundle configuration (dbp_relay_portfolio.sign_api).
 */
class SignCredentials
{
    private ?string $username = null;
    private ?string $password = null;

    /**
     * @param array<string, mixed> $config
     */
    public function setConfig(array $config): void
    {
        $signApi = $config['sign_api'] ?? [];
        $this->username = $signApi['username'] ?? null;
        $this->password = $signApi['password'] ?? null;
    }

    /**
     * Returns true if the given credentials match the configured ones.
     *
     * Uses hash_equals for both fields to avoid leaking timing information.
     * Access is denied if no credentials are configured, or if the request did
     * not provide a username/password.
     */
    public function check(?string $username, ?string $password): bool
    {
        if ($this->username === null || $this->password === null) {
            return false;
        }

        if ($username === null || $password === null) {
            return false;
        }

        $userOk = hash_equals($this->username, $username);
        $passOk = hash_equals($this->password, $password);

        return $userOk && $passOk;
    }
}
