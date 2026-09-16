import { DatePipe } from '@angular/common';
import { Component, input } from '@angular/core';

import { Accommodation } from '../../core/models/accommodation';

@Component({
  selector: 'cm-accommodation-card',
  imports: [DatePipe],
  templateUrl: './accommodation-card.html',
  styleUrl: './accommodation-card.scss',
})
export class AccommodationCard {
  readonly accommodation = input.required<Accommodation>();
}
