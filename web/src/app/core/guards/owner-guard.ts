import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { map } from 'rxjs';

import { AuthService } from '../services/auth';

/** Ferme l'espace propriétaire et mémorise où la personne voulait aller. */
export const ownerGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  return auth
    .restore()
    .pipe(
      map((owner) =>
        null !== owner
          ? true
          : router.createUrlTree(['/connexion'], { queryParams: { suite: state.url } }),
      ),
    );
};
