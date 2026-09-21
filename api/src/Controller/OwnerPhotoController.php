<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Accommodation;
use App\Entity\Photo;
use App\Repository\AccommodationRepository;
use App\Security\Voter\AccommodationVoter;
use App\Service\Photo\InvalidPhotoException;
use App\Service\Photo\PhotoResizer;
use App\Service\Photo\PhotoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * The photos of an accommodation, in its owner's space: upload, delete, reorder.
 *
 * A plain controller rather than an API Platform operation: a multipart upload reads
 * much more simply this way. Same rules as the other owner routes: access_control
 * requires a session, and someone else's accommodation gives a 404.
 *
 * Refusals are answered like API Platform's validation errors (422 with "violations"),
 * so the front displays them the same way.
 */
#[Route('/api/owner/accommodations/{slug}/photos')]
final class OwnerPhotoController
{
    public const MAX_PHOTOS = 12;

    public function __construct(
        private readonly AccommodationRepository $accommodations,
        private readonly EntityManagerInterface $em,
        private readonly PhotoResizer $resizer,
        private readonly PhotoStorage $storage,
        private readonly ValidatorInterface $validator,
        private readonly Security $security,
    ) {
    }

    #[Route('', name: 'api_owner_photo_upload', methods: ['POST'])]
    public function upload(string $slug, Request $request): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);

        // Naturist resorts: nobody may be recognisable on a published picture.
        if (!$request->request->getBoolean('noPeople')) {
            return $this->refuse('noPeople', "Confirmez qu'aucune personne n'apparaît sur cette photo.");
        }

        $file = $request->files->get('photo');

        if (!$file instanceof UploadedFile) {
            return $this->refuse('photo', 'Choisissez une photo à envoyer.');
        }

        $violations = $this->validator->validate($file, new Assert\Image(
            maxSize: '10M',
            mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
            maxSizeMessage: 'Cette photo dépasse 10 Mo : réduisez-la ou choisissez-en une autre.',
            mimeTypesMessage: 'Choisissez une photo au format JPEG, PNG ou WebP.',
            uploadIniSizeErrorMessage: 'Cette photo dépasse 10 Mo : réduisez-la ou choisissez-en une autre.',
        ));

        if (\count($violations) > 0) {
            return $this->refuse('photo', (string) $violations->get(0)->getMessage());
        }

        if ($accommodation->countPhotos() >= self::MAX_PHOTOS) {
            return $this->refuse('photo', \sprintf(
                '%d photos maximum par logement : supprimez-en une pour en ajouter une autre.',
                self::MAX_PHOTOS,
            ));
        }

        try {
            $resized = $this->resizer->resize($file->getPathname());
        } catch (InvalidPhotoException $e) {
            return $this->refuse('photo', $e->getMessage());
        }

        $photo = new Photo($accommodation, $resized->width, $resized->height);
        $accommodation->addPhoto($photo);

        // Files first: if they cannot be written, nothing is recorded in the database.
        $this->storage->save($photo, $resized);

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->storage->delete($photo);

            throw $e;
        }

        return new JsonResponse($this->view($photo), Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_owner_photo_delete', requirements: ['id' => Requirement::UUID], methods: ['DELETE'])]
    public function delete(string $slug, string $id): Response
    {
        $accommodation = $this->ownedAccommodation($slug);
        $photo = $accommodation->findPhoto($id) ?? throw new NotFoundHttpException();

        if ($accommodation->isPublished() && 1 === $accommodation->countPhotos()) {
            return $this->refuse('photo', 'Une annonce en ligne doit garder au moins une photo : ajoutez-en une autre avant de supprimer celle-ci.');
        }

        $accommodation->removePhoto($photo);
        $this->em->flush();

        // Database first: a leftover file is harmless, a missing one would be a broken image.
        $this->storage->delete($photo);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/order', name: 'api_owner_photo_order', methods: ['PUT'])]
    public function reorder(string $slug, Request $request): JsonResponse
    {
        $accommodation = $this->ownedAccommodation($slug);
        $ids = $request->toArray()['ids'] ?? null;

        if (!\is_array($ids) || !array_is_list($ids) || array_filter($ids, 'is_string') !== $ids) {
            return $this->refuse('ids', "L'ordre des photos n'a pas pu être lu. Rechargez la page.");
        }

        try {
            $accommodation->reorderPhotos($ids);
        } catch (\InvalidArgumentException) {
            return $this->refuse('ids', 'Les photos ont changé entre-temps. Rechargez la page.');
        }

        $this->em->flush();

        return new JsonResponse(array_map($this->view(...), $accommodation->getPhotos()));
    }

    private function ownedAccommodation(string $slug): Accommodation
    {
        $accommodation = $this->accommodations->findOneBy(['slug' => $slug]);

        // 404 and not 403 for someone else's accommodation: the slug must not leak.
        if (null === $accommodation || !$this->security->isGranted(AccommodationVoter::EDIT, $accommodation)) {
            throw new NotFoundHttpException();
        }

        return $accommodation;
    }

    /**
     * @return array{id: string, url: string, thumbUrl: string, position: int, width: int, height: int}
     */
    private function view(Photo $photo): array
    {
        return [
            'id' => $photo->getId()->toRfc4122(),
            'url' => $this->storage->url($photo),
            'thumbUrl' => $this->storage->thumbUrl($photo),
            'position' => $photo->getPosition(),
            'width' => $photo->getWidth(),
            'height' => $photo->getHeight(),
        ];
    }

    private function refuse(string $field, string $message): JsonResponse
    {
        return new JsonResponse([
            'title' => 'An error occurred',
            'detail' => $message,
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'violations' => [['propertyPath' => $field, 'message' => $message, 'title' => $message]],
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
