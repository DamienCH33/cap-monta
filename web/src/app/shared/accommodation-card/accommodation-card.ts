import { DatePipe } from '@angular/common';
import { Component, input, computed } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Accommodation, typeLabel } from '../../core/models/accommodation';
import { districtSide } from '../../core/models/search-filters';

@Component({
  selector: 'cm-accommodation-card',
  imports: [DatePipe, RouterLink],
  templateUrl: './accommodation-card.html',
  styleUrl: './accommodation-card.scss',
})
export class AccommodationCard {
  readonly accommodation = input.required<Accommodation>();

  readonly typeLabel = typeLabel;
  readonly side = computed(() => districtSide(this.accommodation().district));

  stripLabel(): string {
    const weeks = this.accommodation().availability;
    const free = weeks.filter((week) => week.free).length;

    return `${free} ${free > 1 ? 'semaines libres' : 'semaine libre'} sur ${weeks.length}`;
  }
}
