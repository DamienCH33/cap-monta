import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AuthService } from '../../core/services/auth';
import { SeoService } from '../../core/services/seo';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'cm-register',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './register.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Register {
  private readonly auth = inject(AuthService);

  readonly form = inject(FormBuilder).nonNullable.group({
    displayName: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(80)]],
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(10)]],
    /** « Comment avez-vous connu Cap Monta ? » : facultatif, '' = pas de réponse. */
    heardFrom: [''],
  });

  /** Les valeurs sont celles de l'API (App\Enum\HeardFrom). */
  readonly heardFromOptions: { value: string; label: string }[] = [
    { value: 'facebook_group', label: $localize`:@@owner.entry.heard-facebook:Un groupe Facebook` },
    { value: 'other_owner', label: $localize`:@@owner.entry.heard-owner:Un autre propriétaire` },
    { value: 'cote_monta', label: $localize`:@@owner.entry.heard-cotemonta:Le site CôtéMonta` },
    { value: 'press', label: $localize`:@@owner.entry.heard-press:La presse` },
    { value: 'search', label: $localize`:@@owner.entry.heard-search:Une recherche sur Google` },
    { value: 'other', label: $localize`:@@owner.entry.heard-other:Autre` },
  ];

  readonly submitting = signal(false);
  readonly sent = signal(false);
  readonly failed = signal<string | null>(null);

  /** Passe à vrai à la première tentative : avant, on n'accuse personne. */
  readonly submitted = signal(false);

  constructor() {
    inject(SeoService).apply({
      title: $localize`:@@owner.entry.register-title:Créer un compte propriétaire`,
      description: $localize`:@@owner.entry.seo-register-description:Publiez gratuitement votre logement au CHM Montalivet ou à Euronat.`,
      path: '/inscription',
      noindex: true,
    });
  }

  submit(): void {
    this.submitted.set(true);
    this.form.markAllAsTouched();

    if (this.form.invalid || this.submitting()) {
      return;
    }

    this.submitting.set(true);
    this.failed.set(null);

    const { heardFrom, ...account } = this.form.getRawValue();

    this.auth.register({ ...account, heardFrom: heardFrom || null }).subscribe({
      next: () => {
        this.submitting.set(false);
        this.sent.set(true);
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.failed.set(Register.messageFor(error));
      },
    });
  }

  /** Un champ n'affiche son erreur qu'une fois touché, ou après une tentative d'envoi. */
  showError(field: 'displayName' | 'email' | 'password'): boolean {
    const control = this.form.controls[field];

    return control.invalid && (control.touched || this.submitted());
  }

  /** L'API connaît ses règles : on affiche ce qu'elle dit plutôt que de le redire ici. */
  private static messageFor(error: HttpErrorResponse): string {
    if (429 === error.status) {
      return $localize`:@@owner.entry.register-throttled:Trop de tentatives depuis cette adresse. Réessayez dans une heure.`;
    }

    const body = error.error as { violations?: { title?: string }[] } | null;

    return (
      body?.violations?.[0]?.title ??
      $localize`:@@owner.entry.register-rejected:Une information n'a pas été acceptée.`
    );
  }
}
