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
}
