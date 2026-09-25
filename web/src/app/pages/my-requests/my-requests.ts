import { DatePipe } from '@angular/common';
import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Component, inject, OnInit, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { catchError, of } from 'rxjs';

import { environment } from '../../../environments/environment';
import { apiErrorMessage } from '../../core/http/api-error';
import { bookingStatusLabel, BookingStatus } from '../../core/models/booking-answer';
import { BookingService } from '../../core/services/booking';
import { GuestRequests, RememberedRequest } from '../../core/services/guest-requests';
import { SeoService } from '../../core/services/seo';

/**
 * « Mes demandes », pour un voyageur sans compte : celles envoyées depuis cet appareil, et
 * un formulaire qui renvoie par email les liens de toutes ses demandes en cours.
 */
@Component({
  selector: 'cm-my-requests',
  imports: [DatePipe, FormsModule, RouterLink],
  templateUrl: './my-requests.html',
  styleUrl: './my-requests.scss',
})
export class MyRequests implements OnInit {
  private readonly http = inject(HttpClient);
  private readonly booking = inject(BookingService);
  readonly remembered = inject(GuestRequests);

  /** Statut à jour de chaque demande retenue, par jeton ; absent tant qu'il n'est pas arrivé. */
  readonly statuses = signal<Record<string, BookingStatus | 'gone'>>({});
  readonly email = signal('');
  readonly sending = signal(false);
  readonly sent = signal(false);
  readonly error = signal<string | null>(null);

  readonly statusLabel = bookingStatusLabel;

  constructor() {
    inject(SeoService).apply({
      title: $localize`:@@seo.mine.title:Mes demandes de réservation`,
      description: $localize`:@@seo.mine.description:Retrouvez vos demandes de réservation.`,
      path: '/mes-demandes',
      noindex: true,
    });
  }

  ngOnInit(): void {
    for (const item of this.remembered.list()) {
      this.booking
        .track(item.token)
        .pipe(catchError(() => of(null)))
        .subscribe((request) =>
          this.statuses.update((all) => ({ ...all, [item.token]: request?.status ?? 'gone' })),
        );
    }
  }

  label(item: RememberedRequest): string {
    const status = this.statuses()[item.token];

    if (undefined === status) {
      return '…';
    }

    return 'gone' === status ? $localize`:@@mine.gone:Introuvable` : this.statusLabel(status);
  }

  recover(): void {
    this.sending.set(true);
    this.error.set(null);

    this.http
      .post(`${environment.apiUrl}/api/booking-requests/recover`, { email: this.email().trim() })
      .subscribe({
        next: () => {
          this.sending.set(false);
          this.sent.set(true);
        },
        error: (error: HttpErrorResponse) => {
          this.sending.set(false);
          this.error.set(apiErrorMessage(error, $localize`:@@common.send-failed:L'envoi a échoué. Réessayez dans un instant.`));
        },
      });
  }
}
