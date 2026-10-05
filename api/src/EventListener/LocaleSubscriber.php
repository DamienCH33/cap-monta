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
 */
final readonly class LocaleSubscriber implements EventSubscriberInterface
{
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

        if (Locales::SOURCE === $locale || !str_contains((string) $response->headers->get('Content-Type'), 'json')) {
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

        $translated = $this->walk($data, $locale);

        if ($translated !== $data) {
            $response->setContent((string) json_encode($translated, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION));
        }
    }

    private function walk(mixed $value, string $locale): mixed
    {
        if (\is_string($value)) {
            return $this->translator->translateMessage($value, $locale);
        }

        if (\is_array($value)) {
            foreach ($value as $key => $item) {
                $value[$key] = $this->walk($item, $locale);
            }
        }

        return $value;
    }
}
