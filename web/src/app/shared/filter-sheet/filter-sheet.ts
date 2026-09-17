import { AfterViewInit, Component, ElementRef, input, output, viewChild } from '@angular/core';

import {
  ACCOMMODATION_TYPES,
  AccommodationTypeKey,
  AMENITIES,
  DISTRICT_SIDES,
  MAX_BEDROOMS_FILTER,
  NO_FILTERS,
  SearchFilters,
  toggle,
} from '../../core/models/search-filters';

@Component({
  selector: 'cm-filter-sheet',
  templateUrl: './filter-sheet.html',
  styleUrl: './filter-sheet.scss',
})
export class FilterSheet implements AfterViewInit {
  readonly filters = input.required<SearchFilters>();
  readonly count = input.required<number>();

  readonly filtersChange = output<SearchFilters>();
  readonly closed = output<void>();

  private readonly dialog = viewChild.required<ElementRef<HTMLDialogElement>>('dialog');

  readonly types = ACCOMMODATION_TYPES;
  readonly sides = DISTRICT_SIDES;
  readonly amenities = AMENITIES;
  readonly bedroomOptions = [
    { value: 0, label: 'Toutes' },
    ...Array.from({ length: MAX_BEDROOMS_FILTER }, (_, index) => ({
      value: index + 1,
      label: `${index + 1}+`,
    })),
  ];

  ngAfterViewInit(): void {
    const dialog = this.dialog().nativeElement;

    // Absent de certains environnements de test : le panneau s'affiche alors sans fond modal.
    if (typeof dialog.showModal === 'function') {
      dialog.showModal();
    }
  }

  toggleType(key: AccommodationTypeKey): void {
    this.update({ types: toggle(this.filters().types, key) });
  }

  toggleDistrict(district: string): void {
    this.update({ districts: toggle(this.filters().districts, district) });
  }

  setBedrooms(bedrooms: number): void {
    this.update({ bedrooms });
  }

  toggleAmenity(key: string): void {
    this.update({ amenities: toggle(this.filters().amenities, key) });
  }

  clear(): void {
    this.filtersChange.emit({ ...NO_FILTERS, order: this.filters().order });
  }

  close(): void {
    this.dialog().nativeElement.close();
  }

  onDialogClose(): void {
    this.closed.emit();
  }

  onBackdropClick(event: MouseEvent): void {
    // Le contenu est dans .sheet__inner : un clic dont la cible est le <dialog> lui-même vient du fond.
    if (event.target === this.dialog().nativeElement) {
      this.close();
    }
  }

  private update(patch: Partial<SearchFilters>): void {
    this.filtersChange.emit({ ...this.filters(), ...patch });
  }
}
