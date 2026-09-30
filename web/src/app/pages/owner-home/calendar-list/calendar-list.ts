import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';

import { typeLabel } from '../../../core/models/accommodation';
import { checkedLabel, OwnerAccommodation, plural } from '../../../core/models/owner-accommodation';
import { Icon } from '../../../shared/icon/icon';

/**
 * Les logements en ligne du tableau de bord, un par ligne : photo et capacité pour distinguer
 * trois « Bungalow · Europa », état du calendrier (icône + mot, jamais la couleur seule),
 * puis les deux outils du quotidien.
 */
@Component({
  selector: 'cm-calendar-list',
  imports: [RouterLink, Icon],
  templateUrl: './calendar-list.html',
  styleUrl: './calendar-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CalendarList {
  readonly items = input.required<OwnerAccommodation[]>();

  readonly checkedLabel = checkedLabel;

  name(item: OwnerAccommodation): string {
    return `${typeLabel(item.type)} · ${item.district ?? ('chm' === item.resort ? 'CHM Montalivet' : 'Euronat')}`;
  }

  details(item: OwnerAccommodation): string {
    return `${plural(item.capacity, 'personne', 'personnes')} · ${plural(item.bedrooms, 'chambre', 'chambres')}`;
  }
}
