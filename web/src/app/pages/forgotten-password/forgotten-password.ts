import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { apiErrorMessage } from '../../core/http/api-error';
import { AuthService } from '../../core/services/auth';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-forgotten-password',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './forgotten-password.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForgottenPassword {
  private readonly auth = inject(AuthService);

  readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
  });

  readonly submitting = signal(false);
  readonly submitted = signal(false);
  readonly sent = signal(false);
  readonly failed = signal<string | null>(null);

  constructor() {
    inject(SeoService).apply({
      title: 'Mot de passe oublié',
      description: 'Recevez un lien pour choisir un nouveau mot de passe.',
      path: '/mot-de-passe-oublie',
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

    this.auth.askPasswordReset(this.form.getRawValue().email).subscribe({
      next: () => {
        this.submitting.set(false);
        this.sent.set(true);
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.failed.set(apiErrorMessage(error));
      },
    });
  }

  showError(): boolean {
    const control = this.form.controls.email;

    return control.invalid && (control.touched || this.submitted());
  }
}
