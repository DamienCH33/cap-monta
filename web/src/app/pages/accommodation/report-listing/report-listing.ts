import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { environment } from '../../../../environments/environment';
import { apiErrorMessage } from '../../../core/http/api-error';

type Reason = 'people_visible' | 'misleading' | 'scam' | 'offensive' | 'other';

/**
 * « Signaler cette annonce » : n'importe quel visiteur, sans compte. Obligation d'un
 * hébergeur qui ne modère pas avant publication, et garde-fou des domaines naturistes.
 */
@Component({
  selector: 'cm-report-listing',
  imports: [FormsModule],
  templateUrl: './report-listing.html',
  styleUrl: './report-listing.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ReportListing {
  private readonly http = inject(HttpClient);

  readonly slug = input.required<string>();

  readonly reasons: { value: Reason; label: string }[] = [
    { value: 'people_visible', label: $localize`:@@report.people:Une personne est reconnaissable sur une photo` },
    { value: 'misleading', label: $localize`:@@report.misleading:Annonce trompeuse (photos, prix, description)` },
    { value: 'scam', label: $localize`:@@report.scam:Arnaque ou logement qui n'existe pas` },
    { value: 'offensive', label: $localize`:@@report.offensive:Contenu choquant ou illégal` },
    { value: 'other', label: $localize`:@@report.other:Autre raison` },
  ];

  readonly reason = signal<Reason | ''>('');
  readonly message = signal('');
  readonly email = signal('');

  readonly sending = signal(false);
  readonly sent = signal(false);
  readonly error = signal<string | null>(null);

  submit(): void {
    if ('' === this.reason() || this.sending()) {
      this.error.set($localize`:@@report.pick:Choisissez la raison du signalement.`);

      return;
    }

    this.sending.set(true);
    this.error.set(null);

    this.http
      .post<void>(`${environment.apiUrl}/api/accommodations/${this.slug()}/reports`, {
        reason: this.reason(),
        message: this.message().trim() || null,
        email: this.email().trim() || null,
      })
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
