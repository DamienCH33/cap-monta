<?php

declare(strict_types=1);

namespace App\Service\StayAlert;

use App\Entity\StayAlert;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;

/**
 * The key of an alert's links: its id, signed. Nothing to store, and nothing to guess: without
 * the server's secret, a key for another alert cannot be built.
 *
 *   base64url(uuid, 16 bytes) . "." . base64url(HMAC-SHA256(id), first 24 bytes)
 */
final readonly class StayAlertLinks
{
    private const string PURPOSE = 'stay-alert|';
    private const int SIGNATURE_BYTES = 24;

    public function __construct(
        #[Autowire('%kernel.secret%')] private string $secret,
    ) {
    }

    public function keyFor(StayAlert $alert): string
    {
        $id = $alert->getId()->toBinary();

        return self::encode($id).'.'.self::encode($this->sign($id));
    }

    /** The alert's id, or null for a key the site did not sign. */
    public function idFrom(string $key): ?Uuid
    {
        $parts = explode('.', $key);

        if (2 !== \count($parts)) {
            return null;
        }

        $id = self::decode($parts[0]);
        $signature = self::decode($parts[1]);

        if (null === $id || 16 !== \strlen($id) || null === $signature || !hash_equals($this->sign($id), $signature)) {
            return null;
        }

        return Uuid::fromBinary($id);
    }

    private function sign(string $id): string
    {
        return substr(hash_hmac('sha256', self::PURPOSE.$id, $this->secret, true), 0, self::SIGNATURE_BYTES);
    }

    private static function encode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function decode(string $text): ?string
    {
        if ('' === $text || 1 !== preg_match('/^[A-Za-z0-9_-]+$/', $text)) {
            return null;
        }

        $bytes = base64_decode(strtr($text, '-_', '+/'), true);

        return false === $bytes ? null : $bytes;
    }
}
