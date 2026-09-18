import { HttpErrorResponse } from '@angular/common/http';

/**
 * L'API explique ses refus : 422 avec des violations, 400 avec un « detail ».
 * On affiche ce qu'elle dit plutôt que de redire ses règles ici.
 */
export function apiErrorMessage(
  error: HttpErrorResponse,
  fallback = "Une information n'a pas été acceptée.",
): string {
  if (429 === error.status) {
    return 'Trop de tentatives depuis cette adresse. Réessayez dans une heure.';
  }

  const body = error.error as { violations?: { title?: string }[]; detail?: string } | null;

  return body?.violations?.[0]?.title ?? body?.detail ?? fallback;
}
