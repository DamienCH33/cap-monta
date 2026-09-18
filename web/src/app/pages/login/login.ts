import {
  ChangeDetectionStrategy,
  Component,
  ElementRef,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { AuthService } from '../../core/services/auth';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-login',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './login.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class Login {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly form = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', Validators.required],
  });
  private readonly emailInput = viewChild.required<ElementRef<HTMLInputElement>>('emailInput');

  readonly redirected = null !== this.route.snapshot.queryParamMap.get('suite');
  readonly submitting = signal(false);
  readonly failed = signal(false);

  /** Posé par le lien de vérification reçu par email : « ok » ou « lien-invalide ». */
  readonly verification = this.route.snapshot.queryParamMap.get('verification');

  constructor() {
    inject(SeoService).apply({
      title: 'Connexion propriétaire',
      description: 'Accédez à votre espace pour gérer votre calendrier et vos tarifs.',
      path: '/connexion',
      noindex: true,
    });
  }

  submit(): void {
    if (this.form.invalid || this.submitting()) {
      return;
    }

    this.submitting.set(true);
    this.failed.set(false);

    const { email, password } = this.form.getRawValue();

    this.auth.login(email, password).subscribe({
      next: () => {
        void this.router.navigateByUrl(
          this.route.snapshot.queryParamMap.get('suite') ?? '/mon-espace',
        );
      },
      error: () => {
        this.submitting.set(false);
        this.failed.set(true);
        this.emailInput().nativeElement.focus();
      },
    });
  }
}
