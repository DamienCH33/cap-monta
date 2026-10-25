import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { apiErrorMessage } from '../../core/http/api-error';
import { StayAlertService } from '../../core/services/stay-alert';

/**
 * « Prévenez-moi » sous une recherche sans résultat : l'email, une case de consentement, et
 * c'est tout. Les dates et les voyageurs sont ceux de la recherche.
 */
@Component({
  selector: 'cm-stay-alert-form',
  imports: [FormsModule],
  templateUrl: './stay-alert-form.html',
  styleUrl: './stay-alert-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StayAlertForm {
  private readonly alerts = inject(StayAlertService);

  readonly arrival = input.required<string>();
  readonly departure = input.required<string>();
  readonly guests = input(1);
  readonly resort = input<string | null>(null);

  readonly email = signal('');
  readonly consent = signal(false);
  readonly sending = signal(false);
  readonly sent = signal(false);
  readonly error = signal<string | null>(null);

  submit(): void {
    if (this.sending()) {
      return;
    }

    if (!/^\S+@\S+\.\S+$/.test(this.email().trim())) {
      this.error.set(
        $localize`:@@alert.email-invalid:Cette adresse email ne semble pas valide.`,
      );

      return;
    }

    if (!this.consent()) {
      this.error.set(
        $localize`:@@alert.consent-required:Cochez la case pour recevoir ces emails.`,
      );

      return;
    }

    this.sending.set(true);
    this.error.set(null);

    this.alerts
      .create({
        email: this.email().trim(),
        arrival: this.arrival(),
        departure: this.departure(),
        guests: Math.max(1, this.guests()),
        resort: this.resort(),
        consent: true,
      })
      .subscribe({
        next: () => {
          this.sending.set(false);
          this.sent.set(true);
        },
        error: (error: HttpErrorResponse) => {
          this.sending.set(false);
          this.error.set(
            apiErrorMessage(
              error,
              $localize`:@@common.send-failed:L'envoi a échoué. Réessayez dans un instant.`,
            ),
          );
        },
      });
  }
}
