import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { environment } from '../../../environments/environment';

import {
  AccommodationStatus,
  byCalendarUrgency,
  OwnerAccommodation,
  STATUS_PARAMS,
} from '../../core/models/owner-accommodation';
import { hoursLeft, OwnerBookingRequest } from '../../core/models/booking-answer';
import { AuthService } from '../../core/services/auth';
import { OwnerBookingRequestService } from '../../core/services/owner-booking-request';
import { OwnerAccommodationService } from '../../core/services/owner-accommodation';
import { SeoService } from '../../core/services/seo';
import { FirstListing } from '../../shared/first-listing/first-listing';
import { Icon } from '../../shared/icon/icon';
import { CalendarList } from './calendar-list/calendar-list';

interface Stat {
  status: AccommodationStatus;
  /** Filtre de la liste, dans l'adresse : ?statut=en-ligne */
  param: string;
  label: string;
  count: number | null;
}

@Component({
  selector: 'cm-owner-home',
  imports: [RouterLink, FirstListing, Icon, CalendarList],
  templateUrl: './owner-home.html',
  styleUrl: './owner-home.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerHome {
  /** Page de dons : l'encart n'apparaît que si elle est renseignée. */
  readonly supportUrl = environment.supportUrl;

  private readonly auth = inject(AuthService);

  readonly owner = this.auth.currentOwner;

  /** Lien de confirmation redemandé depuis le bandeau. */
  readonly resend = signal<'idle' | 'sending' | 'sent' | 'error'>('idle');

  resendVerification(): void {
    this.resend.set('sending');
    this.auth.resendVerification().subscribe({
      next: () => this.resend.set('sent'),
      error: () => this.resend.set('error'),
    });
  }

  /** null tant que la liste n'est pas arrivée. */
  private readonly accommodations = signal<OwnerAccommodation[] | null>(null);

  /** Aucun logement du tout : le tableau de bord montre le premier pas au lieu de trois zéros. */
  readonly noListing = computed(() => 0 === this.accommodations()?.length);

  /** Les trois chiffres du tableau de bord ; null pendant le chargement. */
  readonly stats = computed<Stat[]>(() => {
    const list = this.accommodations();
    const count = (status: AccommodationStatus) =>
      null === list ? null : list.filter((a) => a.status === status).length;

    return [
      {
        status: 'published',
        param: STATUS_PARAMS.published,
        label: $localize`:@@owner.home.stat-published:En ligne`,
        count: count('published'),
      },
      {
        status: 'draft',
        param: STATUS_PARAMS.draft,
        label: $localize`:@@owner.home.stat-drafts:Brouillons`,
        count: count('draft'),
      },
      {
        status: 'archived',
        param: STATUS_PARAMS.archived,
        label: $localize`:@@owner.home.stat-archived:Retirés du site`,
        count: count('archived'),
      },
    ];
  });

  /** Un brouillon est une action en attente : on la signale dès l'arrivée. */
  readonly draftNotice = computed(() => {
    const drafts = (this.accommodations() ?? []).filter((a) => 'draft' === a.status).length;

    if (0 === drafts) {
      return null;
    }

    return drafts > 1
      ? $localize`:@@owner.home.draft-notice.other:${drafts}:count: brouillons attendent d'être publiés.`
      : $localize`:@@owner.home.draft-notice.one:1 brouillon attend d'être publié.`;
  });

  /** Les logements en ligne : c'est sur eux que le badge « Calendrier à jour » se voit. */
  private readonly requests = signal<OwnerBookingRequest[]>([]);

  /** Des demandes attendent : c'est la première chose à voir en arrivant. */
  readonly requestNotice = computed(() => {
    const pending = this.requests().filter((r) => 'pending' === r.status);

    if (0 === pending.length) {
      return null;
    }

    const soonest = Math.min(...pending.map((r) => hoursLeft(r.expiresAt)));

    return {
      text:
        pending.length > 1
          ? $localize`:@@owner.home.request-notice.other:${pending.length}:count: demandes de réservation attendent votre réponse.`
          : $localize`:@@owner.home.request-notice.one:Une demande de réservation attend votre réponse.`,
      detail:
        soonest < 1
          ? $localize`:@@owner.home.request-expiry-soon:La plus urgente expire dans moins d’une heure.`
          : $localize`:@@owner.home.request-expiry-hours:La plus urgente expire dans ${soonest}:hours: h.`,
    };
  });

  readonly calendars = computed(() =>
    (this.accommodations() ?? []).filter((a) => 'published' === a.status).sort(byCalendarUrgency),
  );

  /** Le filtre « Brouillons » de Mes logements, pour le lien de la tâche. */
  readonly draftParam = STATUS_PARAMS.draft;

  /** Sous le bonjour : l'essentiel en une ligne, vide tant que rien n'est chargé. */
  readonly summary = computed(() => {
    const list = this.accommodations();

    if (null === list || 0 === list.length) {
      return null;
    }

    const online = list.filter((a) => 'published' === a.status).length;
    const pending = this.requests().filter((r) => 'pending' === r.status).length;
    const parts = [
      online > 1
        ? $localize`:@@owner.home.summary-online.other:${online}:count: logements en ligne`
        : $localize`:@@owner.home.summary-online.one:${online}:count: logement en ligne`,
    ];

    if (pending > 0) {
      parts.push(
        pending > 1
          ? $localize`:@@owner.home.summary-pending.other:${pending}:count: demandes à traiter`
          : $localize`:@@owner.home.summary-pending.one:1 demande à traiter`,
      );
    }

    return parts.join(' · ');
  });

  readonly calendarSummary = computed(() => {
    const list = this.calendars();
    const fresh = list.filter((a) => a.calendarUpToDate).length;

    if (fresh === list.length) {
      return list.length > 1
        ? $localize`:@@owner.home.calendars-all-fresh:Tous vos calendriers sont à jour.`
        : $localize`:@@owner.home.calendar-fresh:Votre calendrier est à jour.`;
    }

    const total = list.length;

    return total > 1
      ? $localize`:@@owner.home.calendars-stale.other:${fresh}:fresh: calendriers à jour sur ${total}:total:. Sans vérification depuis 30 jours, le badge « Calendrier à jour » disparaît de l'annonce.`
      : $localize`:@@owner.home.calendars-stale.one:${fresh}:fresh: calendrier à jour sur ${total}:total:. Sans vérification depuis 30 jours, le badge « Calendrier à jour » disparaît de l'annonce.`;
  });

  constructor() {
    inject(SeoService).apply({
      title: $localize`:@@owner.home.seo-title:Mon espace`,
      description: $localize`:@@owner.home.seo-description:Gérez vos logements, votre calendrier et vos demandes.`,
      path: '/mon-espace',
      noindex: true,
    });

    inject(OwnerBookingRequestService)
      .list()
      .subscribe({
        next: (list) => this.requests.set(list),
        // Sans la liste, pas de bandeau : la page Demandes reste accessible depuis Mes logements.
        error: () => undefined,
      });

    inject(OwnerAccommodationService)
      .list()
      .subscribe({
        next: (list) => this.accommodations.set(list),
        // Les chiffres restent à « – » : le lien vers la liste fonctionne quand même.
        error: () => undefined,
      });
  }
}
