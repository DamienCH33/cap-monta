<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ListingReportRequest;
use App\Entity\ListingReport;
use App\Enum\ReportReason;
use App\Repository\AccommodationRepository;
use App\Service\Http\FloodGuard;
use App\Service\Moderation\ModerationMailer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Signaler cette annonce": anyone, no account. Kept in the database and emailed to the
 * moderation address; the listing stays online until someone looks at it.
 */
final readonly class ListingReportController
{
    public function __construct(
        private AccommodationRepository $accommodations,
        private EntityManagerInterface $em,
        private ModerationMailer $mailer,
        private FloodGuard $floodGuard,
        private ClockInterface $clock,
        #[Target('listing_reports')]
        private RateLimiterFactoryInterface $listingReportsLimiter,
    ) {
    }

    #[Route('/api/accommodations/{slug}/reports', name: 'api_listing_report', methods: ['POST'])]
    public function __invoke(string $slug, #[MapRequestPayload] ListingReportRequest $payload): JsonResponse
    {
        $this->floodGuard->check($this->listingReportsLimiter);

        $accommodation = $this->accommodations->findOnePublishedBySlug($slug) ?? throw new NotFoundHttpException();

        $message = null === $payload->message ? null : trim($payload->message);
        $email = null === $payload->email ? null : mb_strtolower(trim($payload->email));

        $report = new ListingReport(
            $accommodation,
            ReportReason::from($payload->reason),
            '' === $message ? null : $message,
            '' === $email ? null : $email,
            $this->clock->now(),
        );

        $this->em->persist($report);
        $this->em->flush();
        $this->mailer->reportReceived($report);

        return new JsonResponse(null, Response::HTTP_ACCEPTED);
    }
}
