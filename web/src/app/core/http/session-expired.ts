import { isPlatformBrowser } from '@angular/common';
import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject, PLATFORM_ID } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, throwError } from 'rxjs';

import { AuthService } from '../services/auth';

/**
 * Session expirée pendant qu'on travaille dans l'espace propriétaire : l'API répond 401.
 * Plutôt qu'un « Impossible de charger », retour à la connexion, qui ramènera ensuite ici.
 */
export const sessionExpiredInterceptor: HttpInterceptorFn = (request, next) => {
  if (!isPlatformBrowser(inject(PLATFORM_ID))) {
    return next(request);
  }

  const auth = inject(AuthService);
  const router = inject(Router);

  return next(request).pipe(
    catchError((error: unknown) => {
      if (
        error instanceof HttpErrorResponse &&
        401 === error.status &&
        request.url.includes('/api/owner/') &&
        auth.isLoggedIn()
      ) {
        auth.forget();
        void router.navigate(['/connexion'], { queryParams: { suite: router.url } });
      }

      return throwError(() => error);
    }),
  );
};
