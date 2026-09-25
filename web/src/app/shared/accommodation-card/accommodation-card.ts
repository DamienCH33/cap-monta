import { DatePipe } from '@angular/common';
import { Component, input, computed } from '@angular/core';
import { RouterLink } from '@angular/router';
import { DISTRICT_AREAS, DistrictArea } from '../../core/models/district';
import { FavoriteButton } from '../favorite-button/favorite-button';
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
      ? 'Aucune semaine libre'
      : `${free} ${free > 1 ? 'semaines libres' : 'semaine libre'}`;
  });

  stripLabel(): string {
    const weeks = this.accommodation().availability;
    const free = weeks.filter((week) => week.free).length;

    return `${free} ${free > 1 ? 'semaines libres' : 'semaine libre'} sur ${weeks.length}`;
  }
}
