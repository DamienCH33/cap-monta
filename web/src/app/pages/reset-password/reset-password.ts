import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { apiErrorMessage } from '../../core/http/api-error';
import { AuthService } from '../../core/services/auth';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-reset-password',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './reset-password.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ResetPassword {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  private readonly jeton = inject(ActivatedRoute).snapshot.queryParamMap.get('jeton') ?? '';

  readonly form = inject(FormBuilder).nonNullable.group({
    password: ['', [Validators.required, Validators.minLength(10)]],
  });

  /** Sans jeton dans l'URL, la page n'a rien à proposer. */
  readonly usable = '' !== this.jeton;

  readonly submitting = signal(false);
  readonly submitted = signal(false);
  readonly failed = signal<string | null>(null);

  constructor() {
    inject(SeoService).apply({
      title: 'Nouveau mot de passe',
      description: 'Choisissez un nouveau mot de passe pour votre compte.',
      path: '/nouveau-mot-de-passe',
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

    this.auth.resetPassword(this.jeton, this.form.getRawValue().password).subscribe({
      next: () => {
        void this.router.navigate(['/connexion'], { queryParams: { motdepasse: 'ok' } });
      },
      error: (error: HttpErrorResponse) => {
        this.submitting.set(false);
        this.failed.set(apiErrorMessage(error));
      },
    });
  }

  showError(): boolean {
    const control = this.form.controls.password;

    return control.invalid && (control.touched || this.submitted());
  }
}
