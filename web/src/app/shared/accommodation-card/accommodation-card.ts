import { DatePipe } from '@angular/common';
import { Component, input, computed } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DISTRICT_AREAS, DistrictArea } from '../../core/models/district';
import { FavoriteButton } from '../favorite-button/favorite-button';
import { euros } from '../../core/models/stay-terms';
import { Icon } from '../icon/icon';
import { IconName } from '../icon/icons';

import { Accommodation, typeLabel } from '../../core/models/accommodation';

@Component({
  selector: 'cm-accommodation-card',
  imports: [DatePipe, RouterLink, Icon, FavoriteButton],
  templateUrl: './accommodation-card.html',
  styleUrl: './accommodation-card.scss',
})
export class AccommodationCard {
  readonly euros = euros;
  readonly freeSuffix = $localize`:@@card.week-free: : libre`;
  readonly takenSuffix = $localize`:@@card.week-taken: : indisponible`;

  readonly accommodation = input.required<Accommodation>();
  readonly area = computed(() => {
    const key = this.accommodation().districtArea;

    return DISTRICT_AREAS.find((area) => area.key === key) ?? null;
  });

  readonly areaIcons: Record<DistrictArea, IconName> = {
    dunes: 'ripple',
    central: 'trees',
    roadside: 'building-store',
  };
  readonly typeLabel = typeLabel;

  readonly freeLabel = computed(() => {
    const free = this.accommodation().availability.filter((week) => week.free).length;

    return 0 === free
      ? $localize`:@@card.no-free-week:Aucune semaine libre`
      : 1 === free
        ? $localize`:@@card.free-week.one:1 semaine libre`
        : $localize`:@@card.free-week.other:${free}:count: semaines libres`;
  });

  stripLabel(): string {
    const weeks = this.accommodation().availability;
    const free = weeks.filter((week) => week.free).length;

    return 1 >= free
      ? $localize`:@@card.strip-label.one:${free}:free: semaine libre sur ${weeks.length}:total:`
      : $localize`:@@card.strip-label.other:${free}:free: semaines libres sur ${weeks.length}:total:`;
  }
}
