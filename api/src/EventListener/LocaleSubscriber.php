<?php

declare(strict_types=1);

namespace App\EventListener;

use App\I18n\Locales;
use App\I18n\Translator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * La langue de la page qui appelle l'API (en-tête Accept-Language posé par le front) devient la
 * langue de la requête ; les réponses JSON y sont traduites : messages d'erreur, violations,
 * motifs de refus. Le français passe sans aucun traitement.
 *
 * Les textes écrits par les gens (description d'une annonce, message d'un voyageur, nom…) ne
 * sont jamais retouchés : dans une réponse réussie, ces champs ne sont traduits que s'ils sont
 * mot pour mot un texte du site (« Ces dates viennent d'être prises… », posé par le site
 * comme réponse du propriétaire). Les autres textes y passent ligne par ligne.
 */
final readonly class LocaleSubscriber implements EventSubscriberInterface
{
    /** Champs qui portent un texte saisi par quelqu'un, pas un message du site. */
    private const array PEOPLE_TEXT = [
        'description', 'message', 'guestMessage', 'ownerMessage', 'answer', 'title', 'name',
        'displayName', 'guestName', 'text', 'note', 'cancellationPolicy', 'houseRules', 'label',
        'originalText', 'email', 'phone', 'slug',
    ];

    public function __construct(private Translator $translator)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Avant le routeur et la sécurité : les erreurs d'authentification sont traduites aussi.
            KernelEvents::REQUEST => ['onRequest', 100],
            KernelEvents::RESPONSE => ['onResponse', -10],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $request->setLocale(Locales::normalize($request->headers->get('Accept-Language')));
    }

    public function onResponse(ResponseEvent $event): void
    {
        $response = $event->getResponse();
        $locale = Locales::normalize($event->getRequest()->getLocale());

        if (!str_contains((string) $response->headers->get('Content-Type'), 'json')) {
            return;
        }

        // Même adresse, langues différentes : un cache ne doit pas servir la mauvaise.
        $response->setVary('Accept-Language', false);

        if (Locales::SOURCE === $locale) {
            return;
        }

        $content = (string) $response->getContent();

        if ('' === $content) {
            return;
        }

        try {
            $data = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return;
        }

        $translated = $this->walk($data, $locale, $response->getStatusCode() >= 400);

        if ($translated !== $data) {
            $response->setContent((string) json_encode($translated, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION));
            // L'empreinte calculée sur le texte français ne correspond plus au contenu envoyé.
            $response->setEtag(null);
        }
    }

    private function walk(mixed $value, string $locale, bool $error, int|string|null $key = null): mixed
    {
        if (\is_string($value)) {
            // Une erreur ne contient que des textes du site ; ailleurs, un champ saisi par
            // quelqu'un n'est traduit que s'il est exactement un texte du site.
            return $error || !\in_array($key, self::PEOPLE_TEXT, true)
                ? $this->translator->translateMessage($value, $locale)
                : $this->translator->exact($value, $locale);
        }

        if (\is_array($value)) {
            foreach ($value as $itemKey => $item) {
                // Une liste hérite du nom du champ qui la porte (« highlights », « questions »…).
                $value[$itemKey] = $this->walk($item, $locale, $error, \is_int($itemKey) ? $key : $itemKey);
            }
        }

        return $value;
    }
}
