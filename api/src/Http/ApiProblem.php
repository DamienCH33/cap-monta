<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one error format of the API (RFC 9457, application/problem+json), the same as API
 * Platform's own errors: the front reads « detail », and « violations » when a field is at fault.
 *
 *   {"type": "/errors/422", "title": "Unprocessable Content", "status": 422,
 *    "detail": "Ajoutez au moins une photo.",
 *    "violations": [{"propertyPath": "photos", "message": "…", "title": "…"}]}
 */
final class ApiProblem
{
    /**
     * @param array<string, mixed> $extra other members, e.g. the slug an import was saved to
     */
    public static function response(int $status, string $detail, ?string $field = null, array $extra = []): JsonResponse
    {
        $body = [
            'type' => '/errors/'.$status,
            'title' => Response::$statusTexts[$status] ?? 'Error',
            'status' => $status,
            'detail' => $detail,
        ];

        if (null !== $field) {
            $body['violations'] = [['propertyPath' => $field, 'message' => $detail, 'title' => $detail]];
        }

        return new JsonResponse($body + $extra, $status, ['Content-Type' => 'application/problem+json']);
    }

    /**
     * Several fields at fault at once: the first message is the summary.
     *
     * @param list<array{propertyPath: string, message: string}> $violations
     */
    public static function violations(string $detail, array $violations, int $status = Response::HTTP_UNPROCESSABLE_ENTITY): JsonResponse
    {
        return self::response($status, $detail, extra: ['violations' => $violations]);
    }
}
