import { HttpErrorResponse } from '@angular/common/http';

/**
 * L'API explique ses refus : 422 avec des violations, 400 avec un « detail ».
 * On affiche ce qu'elle dit plutôt que de redire ses règles ici.
 */
export function apiErrorMessage(
  error: HttpErrorResponse,
  fallback = $localize`:@@owner.common.error-rejected:Une information n'a pas été acceptée.`,
): string {
  if (429 === error.status) {
    return $localize`:@@owner.common.error-too-many:Trop de tentatives depuis cette adresse. Réessayez dans une heure.`;
  }

  const body = error.error as { violations?: { title?: string }[]; detail?: string } | null;

  // Message générique du Serializer (type inattendu, JSON illisible) : illisible pour un visiteur.
  if ('The input data is misformatted.' === body?.detail) {
    return $localize`:@@owner.common.error-misformatted:Une information envoyée n'a pas pu être lue. Vérifiez les champs et réessayez.`;
  }

  return body?.violations?.[0]?.title ?? body?.detail ?? fallback;
}
