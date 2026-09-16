import { Component, inject, OnInit, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { Accommodation } from '../../core/models/accommodation';
import { AccommodationService } from '../../core/services/accommodation';
import { AccommodationCard } from '../../shared/accommodation-card/accommodation-card';
import { SearchBar } from '../../shared/search-bar/search-bar';

@Component({
  selector: 'cm-home',
  imports: [RouterLink, SearchBar, AccommodationCard],
  templateUrl: './home.html',
  styleUrl: './home.scss',
})
export class Home implements OnInit {
  private readonly accommodations = inject(AccommodationService);

  readonly highlights = signal<Accommodation[]>([]);

  ngOnInit(): void {
    this.accommodations.search({}).subscribe((found) => this.highlights.set(found.slice(0, 3)));
  }
}
