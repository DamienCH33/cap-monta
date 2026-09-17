import { DatePipe } from '@angular/common';
import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Accommodation, typeLabel } from '../../core/models/accommodation';

@Component({
  selector: 'cm-accommodation-card',
  imports: [DatePipe, RouterLink],
  templateUrl: './accommodation-card.html',
  styleUrl: './accommodation-card.scss',
})
export class AccommodationCard {
  readonly accommodation = input.required<Accommodation>();

  readonly typeLabel = typeLabel;

  stripLabel(): string {
    const weeks = this.accommodation().availability;
    const free = weeks.filter((week) => week.free).length;

    return `${free} ${free > 1 ? 'semaines libres' : 'semaine libre'} sur ${weeks.length}`;
  }
}
