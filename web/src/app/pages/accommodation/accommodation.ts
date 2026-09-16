import { DatePipe } from '@angular/common';
import { Component, computed, inject, OnInit, signal } from '@angular/core';
import { ActivatedRoute, Params, RouterLink } from '@angular/router';
import { switchMap } from 'rxjs';

import { Accommodation, typeLabel } from '../../core/models/accommodation';
import { AccommodationService } from '../../core/services/accommodation';

@Component({
  selector: 'cm-accommodation',
  imports: [DatePipe, RouterLink],
  templateUrl: './accommodation.html',
  styleUrl: './accommodation.scss',
})
export class AccommodationPage implements OnInit {
  private readonly route = inject(ActivatedRoute);
  private readonly accommodations = inject(AccommodationService);

  readonly accommodation = signal<Accommodation | null>(null);
  readonly notFound = signal(false);
  readonly searchParams = signal<Params>({});

  readonly typeLabel = typeLabel;

  readonly cameFromSearch = computed(() => Object.keys(this.searchParams()).length > 0);

  ngOnInit(): void {
    this.route.queryParams.subscribe((params) => this.searchParams.set(params));

    this.route.paramMap
      .pipe(switchMap((params) => this.accommodations.getBySlug(params.get('slug') ?? '')))
      .subscribe({
        next: (found) => this.accommodation.set(found),
        error: () => this.notFound.set(true),
      });
  }
}
