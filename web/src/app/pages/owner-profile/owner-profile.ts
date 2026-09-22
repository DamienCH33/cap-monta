import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { apiErrorMessage } from '../../core/http/api-error';
import { AuthService } from '../../core/services/auth';
import { SeoService } from '../../core/services/seo';

/** Même règle que l'API : numéro français, espaces, points ou tirets tolérés. */
export const FRENCH_PHONE = /^(?:\+33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/;

@Component({
  selector: 'cm-owner-profile',
  imports: [ReactiveFormsModule, RouterLink],
  templateUrl: './owner-profile.html',
  styleUrl: './owner-profile.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerProfile {
  private readonly auth = inject(AuthService);
  private readonly fb = inject(FormBuilder).nonNullable;

  readonly owner = this.auth.currentOwner;

  readonly profile = this.fb.group({
    displayName: [
      this.owner()?.displayName ?? '',
      [Validators.required, Validators.minLength(2), Validators.maxLength(80)],
    ],
    phone: [this.owner()?.phone ?? '', [Validators.pattern(FRENCH_PHONE)]],
  });

  readonly password = this.fb.group({
    currentPassword: ['', [Validators.required]],
    newPassword: ['', [Validators.required, Validators.minLength(10)]],
  });

  readonly profileState = signal<'idle' | 'saving' | 'saved'>('idle');
  readonly profileError = signal<string | null>(null);
  readonly profileSubmitted = signal(false);

  readonly passwordState = signal<'idle' | 'saving' | 'saved'>('idle');
  readonly passwordError = signal<string | null>(null);
  readonly passwordSubmitted = signal(false);

  constructor() {
    inject(SeoService).apply({
      title: 'Mon profil',
      description: 'Nom affiché, téléphone et mot de passe du compte propriétaire.',
      path: '/mon-espace/profil',
      noindex: true,
    });
  }

  saveProfile(): void {
    this.profileSubmitted.set(true);
    this.profile.markAllAsTouched();

    if (this.profile.invalid || 'saving' === this.profileState()) {
      return;
    }

    this.profileState.set('saving');
    this.profileError.set(null);

    this.auth.updateProfile(this.profile.getRawValue()).subscribe({
      next: (owner) => {
        this.profile.setValue({ displayName: owner.displayName, phone: owner.phone ?? '' });
        this.profile.markAsPristine();
        this.profileState.set('saved');
      },
      error: (error: HttpErrorResponse) => {
        this.profileState.set('idle');
        this.profileError.set(apiErrorMessage(error));
      },
    });
  }

  savePassword(): void {
    this.passwordSubmitted.set(true);
    this.password.markAllAsTouched();

    if (this.password.invalid || 'saving' === this.passwordState()) {
      return;
    }

    this.passwordState.set('saving');
    this.passwordError.set(null);

    const { currentPassword, newPassword } = this.password.getRawValue();

    this.auth.changePassword(currentPassword, newPassword).subscribe({
      next: () => {
        this.password.reset();
        this.passwordSubmitted.set(false);
        this.passwordState.set('saved');
      },
      error: (error: HttpErrorResponse) => {
        this.passwordState.set('idle');
        this.passwordError.set(apiErrorMessage(error));
      },
    });
  }

  /** Le message « enregistré » disparaît dès qu'on retouche le formulaire. */
  edited(form: 'profile' | 'password'): void {
    if ('profile' === form && 'saved' === this.profileState()) {
      this.profileState.set('idle');
    }
    if ('password' === form && 'saved' === this.passwordState()) {
      this.passwordState.set('idle');
    }
  }

  showProfileError(field: 'displayName' | 'phone'): boolean {
    const control = this.profile.controls[field];

    return control.invalid && (control.touched || this.profileSubmitted());
  }

  showPasswordError(field: 'currentPassword' | 'newPassword'): boolean {
    const control = this.password.controls[field];

    return control.invalid && (control.touched || this.passwordSubmitted());
  }
}
