import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { catchError, map, of } from 'rxjs';

import { ListingImportService } from '../../core/services/listing-import';
import { Icon } from '../icon/icon';

/**
 * Premier pas d'un propriétaire qui n'a encore aucun logement (tableau de bord et Mes
 * logements) : deux chemins côte à côte plutôt qu'un bouton et une phrase. L'import passe
 * en premier, c'est le plus rapide pour qui a déjà une annonce ailleurs ; il n'apparaît que
 * si l'assistant peut lire tout de suite (ADR 027).
 */
@Component({
  selector: 'cm-first-listing',
  imports: [RouterLink, Icon],
  templateUrl: './first-listing.html',
  styleUrl: './first-listing.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FirstListing {
  readonly assistantAvailable = toSignal(
    inject(ListingImportService)
      .assistant()
      .pipe(
        map((status) => status.available),
        catchError(() => of(false)),
      ),
    { initialValue: false },
  );
}
