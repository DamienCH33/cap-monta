import { Component, inject, OnInit, signal } from '@angular/core';
import { Params, RouterLink } from '@angular/router';

import { Accommodation } from '../../core/models/accommodation';
import { District } from '../../core/models/district';
import { AccommodationService } from '../../core/services/accommodation';
import { DistrictService } from '../../core/services/district';
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
  private readonly districtApi = inject(DistrictService);

  readonly highlights = signal<Accommodation[]>([]);
  readonly districts = signal<District[]>([]);

  ngOnInit(): void {
    this.accommodations.search({}).subscribe((found) => this.highlights.set(found.slice(0, 3)));
    this.districtApi.list().subscribe((found) => this.districts.set(found));
  }

  queryParamsFor(district: District): Params {
    return { quartier: district.name };
  }
}
