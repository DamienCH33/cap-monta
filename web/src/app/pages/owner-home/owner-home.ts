import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { Router } from '@angular/router';

import { AuthService } from '../../core/services/auth';
import { SeoService } from '../../core/services/seo';

@Component({
  selector: 'cm-owner-home',
  templateUrl: './owner-home.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnerHome {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly owner = this.auth.currentOwner;

  constructor() {
    inject(SeoService).apply({
      title: 'Mon espace',
      description: 'Gérez votre calendrier, vos tarifs et vos demandes.',
      path: '/mon-espace',
      noindex: true,
    });
  }

  logout(): void {
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/'));
  }
}
