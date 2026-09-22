import { DatePipe, DecimalPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { apiErrorMessage } from '../../core/http/api-error';
import {
  bookingPrice,
  bookingStatusLabel,
  competing,
  hoursLeft,
  inboxTab,
  InboxTab,
  OwnerBookingRequest,
  travellers,
} from '../../core/models/booking-answer';
import { isoDay } from '../../core/models/owner-calendar';
import { OwnerBookingRequestService } from '../../core/services/owner-booking-request';
import { SeoService } from '../../core/services/seo';

type Action = 'accept' | 'decline' | 'cancel';

interface Tab {
  key: InboxTab;
  label: string;
  count: number;
}

const TAB_PARAMS: Record<InboxTab, string> = {
  pending: 'a-traiter',
  accepted: 'acceptees',
  history: 'historique',
};

const EMPTY: Record<InboxTab, string> = {
  pending: 'Aucune demande en attente : vous êtes à jour.',
  accepted: 'Aucune réservation à venir pour le moment.',
  history: 'Rien dans l’historique pour l’instant.',
};

const DONE: Record<Action, string> = {
  accept: 'Demande acceptée : les dates sont bloquées et le voyageur a reçu vos coordonnées.',
  decline: 'Demande refusée : le voyageur en est prévenu.',
  cancel: 'Réservation annulée : les dates sont de nouveau libres et le voyageur est prévenu.',
};

/**
 * La boîte de réception du propriétaire : répondre en un clic, sans quitter la page.
 * Les demandes à traiter d'abord, avec le délai restant avant expiration.
 */
@Component({
  selector: 'cm-owner-requests',
  imports: [DatePipe, DecimalPipe, FormsModule, RouterLink],
  templateUrl: './owner-requests.html',
  styleUrl: './owner-requests.scss',
})
export class OwnerRequests {
  private readonly service = inject(OwnerBookingRequestService);
  private readonly router = inject(Router);

  readonly requests = signal<OwnerBookingRequest[] | null>(null);
  readonly loadFailed = signal(false);
  readonly tab = signal<InboxTab>('pending');

  /** La demande dont le panneau de réponse est ouvert, et l'action choisie. */
  readonly open = signal<{ id: string; action: Action } | null>(null);
  readonly price = signal<number | null>(null);
  readonly message = signal('');
  readonly saving = signal(false);
  readonly error = signal<string | null>(null);
  readonly notice = signal<string | null>(null);

  private readonly today = isoDay(new Date());

  readonly tabs = computed<Tab[]>(() => {
    const list = this.requests() ?? [];
    const count = (key: InboxTab) => list.filter((r) => inboxTab(r, this.today) === key).length;

    return [
      { key: 'pending', label: 'À traiter', count: count('pending') },
      { key: 'accepted', label: 'Acceptées', count: count('accepted') },
      { key: 'history', label: 'Historique', count: count('history') },
    ];
  });

  readonly visible = computed(() => {
    const list = (this.requests() ?? []).filter((r) => inboxTab(r, this.today) === this.tab());

    // À traiter : la plus urgente d'abord. Acceptées : la plus proche d'abord.
    return 'history' === this.tab()
      ? list
      : [...list].sort((a, b) =>
          'pending' === this.tab()
            ? a.expiresAt.localeCompare(b.expiresAt)
            : a.start.localeCompare(b.start),
        );
  });

  readonly emptyMessage = computed(() => EMPTY[this.tab()]);

  readonly statusLabel = bookingStatusLabel;
  readonly travellers = travellers;
  readonly bookingPrice = bookingPrice;
  readonly tabParams = TAB_PARAMS;

  constructor() {
    inject(SeoService).apply({
      title: 'Demandes de réservation',
      description: 'Répondez aux demandes de réservation de vos logements.',
      path: '/mon-espace/demandes',
      noindex: true,
    });

    const param = inject(ActivatedRoute).snapshot.queryParamMap.get('onglet');
    const fromParam = (Object.keys(TAB_PARAMS) as InboxTab[]).find(
      (key) => TAB_PARAMS[key] === param,
    );
    this.tab.set(fromParam ?? 'pending');

    this.service.list().subscribe({
      next: (list) => this.requests.set(list),
      error: () => this.loadFailed.set(true),
    });
  }

  select(tab: InboxTab): void {
    this.tab.set(tab);
    this.close();
    void this.router.navigate([], { queryParams: { onglet: TAB_PARAMS[tab] }, replaceUrl: true });
  }

  hoursLeft(request: OwnerBookingRequest): number {
    return hoursLeft(request.expiresAt);
  }

  /** Les autres demandes qui seront refusées d'office si celle-ci est acceptée. */
  competing(request: OwnerBookingRequest): OwnerBookingRequest[] {
    return competing(request, this.requests() ?? []);
  }

  isOpen(request: OwnerBookingRequest, action: Action): boolean {
    const open = this.open();

    return null !== open && open.id === request.id && open.action === action;
  }

  start(request: OwnerBookingRequest, action: Action): void {
    this.open.set({ id: request.id, action });
    this.message.set('');
    this.error.set(null);
    this.notice.set(null);
    // Le prix ne se saisit que pour un séjour « à convenir » : sinon, c'est celui des tarifs.
    this.price.set(null);
  }

  close(): void {
    this.open.set(null);
    this.error.set(null);
  }

  confirm(request: OwnerBookingRequest, action: Action): void {
    const euros = this.price();

    if ('accept' === action && null === request.estimatedPrice && (null === euros || euros < 1)) {
      this.error.set('Indiquez le prix du séjour : aucun tarif ne couvre ces dates.');

      return;
    }

    // Couvert par les tarifs : on n'envoie pas de prix, l'API reprend celui de la grille.
    const cents =
      null === request.estimatedPrice && null !== euros ? Math.round(euros * 100) : null;

    const call =
      'accept' === action
        ? this.service.accept(request.id, cents, this.message())
        : 'decline' === action
          ? this.service.decline(request.id, this.message())
          : this.service.cancel(request.id, this.message());

    this.saving.set(true);
    this.error.set(null);

    call.subscribe({
      next: (list) => {
        this.requests.set(list);
        this.saving.set(false);
        this.open.set(null);
        this.notice.set(DONE[action]);
      },
      error: (error: HttpErrorResponse) => {
        this.saving.set(false);
        this.error.set(
          apiErrorMessage(error, 'La réponse n’a pas pu être enregistrée. Réessayez.'),
        );
      },
    });
  }
}
