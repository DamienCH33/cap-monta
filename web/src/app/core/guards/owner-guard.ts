import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { catchError, map, of } from 'rxjs';

import { AuthService } from '../services/auth';

/** Ferme l'espace propriétaire et mémorise où la personne voulait aller. */
export const ownerGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);
  const router = inject(Router);

  const toLogin = () => router.createUrlTree(['/connexion'], { queryParams: { suite: state.url } });

  return auth.restore().pipe(
    map((owner) => (null !== owner ? true : toLogin())),
    // API injoignable : la page s'ouvre et dit elle-même qu'elle ne peut pas charger, plutôt
    // que d'envoyer vers une connexion qui échouerait aussi.
    catchError(() => of(true)),
  );
};
