<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\RateLimiter\AbstractRequestRateLimiter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Login throttling in three counters, the first refusal wins:
 * - per address and IP (5 / 15 min): the usual typing mistakes;
 * - per IP (50 / 15 min): one machine trying many accounts;
 * - per address, whatever the IP (20 / hour): one account attacked from many machines.
 *   Symfony's default throttling only counts by address AND IP; changing IP reset it.
 *
 * The keys are hashed: neither addresses nor IPs are stored in clear in the counters.
 */
final class LoginRateLimiter extends AbstractRequestRateLimiter
{
    public function __construct(
        #[Target('login_email_ip')] private readonly RateLimiterFactoryInterface $emailAndIp,
        #[Target('login_ip')] private readonly RateLimiterFactoryInterface $ip,
        #[Target('login_email')] private readonly RateLimiterFactoryInterface $email,
        #[Autowire('%kernel.secret%')] private readonly string $secret,
    ) {
    }

    protected function getLimiters(Request $request): array
    {
        $username = (string) $request->attributes->get(SecurityRequestAttributes::LAST_USERNAME, '');
        $username = mb_strtolower(trim($username));
        $ip = (string) $request->getClientIp();

        return [
            $this->emailAndIp->create($this->hash($username.'|'.$ip)),
            $this->ip->create($this->hash($ip)),
            $this->email->create($this->hash($username)),
        ];
    }

    private function hash(string $value): string
    {
        return substr(hash_hmac('sha256', $value, $this->secret), 0, 24);
    }
}
