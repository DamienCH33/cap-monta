import { Component, computed, ElementRef, inject, model, signal } from '@angular/core';

import { Guests, guestsSummary, NO_GUESTS } from '../../core/models/guests';

type GuestKey = keyof Guests;

/** Capacité maximale d'un logement du site (bungalow 8 places). */
const MAX_TRAVELLERS = 8;
const MAX_INFANTS_OR_PETS = 5;

@Component({
  selector: 'cm-guest-picker',
  templateUrl: './guest-picker.html',
  styleUrl: './guest-picker.scss',
  host: {
    '(document:click)': 'onDocumentClick($event)',
    '(document:keydown.escape)': 'close()',
  },
})
export class GuestPicker {
  readonly addLabel = $localize`:@@guests.add-one:Ajouter : `;
  readonly removeLabel = $localize`:@@guests.remove-one:Retirer : `;

  private readonly host = inject<ElementRef<HTMLElement>>(ElementRef);

  readonly value = model<Guests>(NO_GUESTS);
  readonly open = signal(false);
  readonly summary = computed(() => guestsSummary(this.value()));

  readonly rows: readonly { key: GuestKey; title: string; hint: string }[] = [
    {
      key: 'adults',
      title: $localize`:@@guests.adults:Adultes`,
      hint: $localize`:@@guests.adults-hint:13 ans et plus`,
    },
    {
      key: 'children',
      title: $localize`:@@guests.children:Enfants`,
      hint: $localize`:@@guests.children-hint:De 2 à 12 ans`,
    },
    {
      key: 'infants',
      title: $localize`:@@guests.infants:Bébés`,
      hint: $localize`:@@guests.infants-hint:Moins de 2 ans`,
    },
    {
      key: 'pets',
      title: $localize`:@@guests.pets:Animaux`,
      hint: $localize`:@@guests.pets-hint:À confirmer avec le propriétaire`,
    },
  ];

  toggle(): void {
    this.open.update((isOpen) => !isOpen);
  }

  close(): void {
    this.open.set(false);
  }

  onDocumentClick(event: MouseEvent): void {
    if (this.open() && !this.host.nativeElement.contains(event.target as Node)) {
      this.close();
    }
  }

  canIncrement(key: GuestKey): boolean {
    const guests = this.value();

    if (key === 'infants' || key === 'pets') {
      return guests[key] < MAX_INFANTS_OR_PETS;
    }

    return guests.adults + guests.children < MAX_TRAVELLERS;
  }

  canDecrement(key: GuestKey): boolean {
    const guests = this.value();

    if (key === 'adults') {
      const hasCompanions = guests.children + guests.infants + guests.pets > 0;
      return guests.adults > (hasCompanions ? 1 : 0);
    }

    return guests[key] > 0;
  }

  increment(key: GuestKey): void {
    if (!this.canIncrement(key)) {
      return;
    }

    this.value.update((guests) => {
      const next = { ...guests, [key]: guests[key] + 1 };

      // Un enfant, un bébé ou un animal ne voyage pas sans adulte.
      if (key !== 'adults' && next.adults === 0) {
        next.adults = 1;
      }

      return next;
    });
  }

  decrement(key: GuestKey): void {
    if (!this.canDecrement(key)) {
      return;
    }

    this.value.update((guests) => ({ ...guests, [key]: guests[key] - 1 }));
  }
}
