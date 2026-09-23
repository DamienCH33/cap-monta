<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Why an import did not succeed, and what that means for the next attempt. The message is the
 * one the owner reads: it always says his text is kept and the form works without the assistant.
 */
enum ListingImportFailure: string
{
    /** The provider did not answer (network, server error): worth retrying soon. */
    case Unavailable = 'unavailable';
    /** The provider's rate limit or free quota is reached: retry later, pause the assistant. */
    case ProviderLimit = 'provider_limit';
    /** The answer could not be read: one more try, models are not deterministic. */
    case Unreadable = 'unreadable';
    /** Wrong key, unknown model: retrying changes nothing, someone must fix the configuration. */
    case Configuration = 'configuration';

    public function isWorthRetrying(): bool
    {
        return self::Configuration !== $this;
    }

    /** How long the assistant is switched off for everyone after this failure, in seconds. */
    public function pauseSeconds(): int
    {
        return match ($this) {
            self::Unavailable => 5 * 60,
            self::ProviderLimit => 30 * 60,
            self::Configuration => 60 * 60,
            self::Unreadable => 0,
        };
    }

    public function messageForOwner(): string
    {
        $reason = match ($this) {
            self::Unavailable, self::ProviderLimit => 'L\'assistant de lecture est indisponible pour le moment.',
            self::Unreadable => 'L\'assistant n\'a pas réussi à lire cette annonce.',
            self::Configuration => 'L\'assistant de lecture est en maintenance.',
        };

        return $reason.' Votre texte est gardé : vous pouvez remplir le formulaire vous-même en le gardant sous les yeux, ou réessayer plus tard.';
    }
}
