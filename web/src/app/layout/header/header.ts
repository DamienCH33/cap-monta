import { Component, inject } from '@angular/core';
import { Router, RouterLink } from '@angular/router';

import { AuthService } from '../../core/services/auth';
import { Icon } from '../../shared/icon/icon';

@Component({
  selector: 'cm-header',
  imports: [Icon, RouterLink],
  templateUrl: './header.html',
  styleUrl: './header.scss',
})
export class Header {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly owner = this.auth.currentOwner;
  readonly sessionChecked = this.auth.sessionChecked;

  constructor() {
    this.auth.restore().subscribe();
  }

  logout(): void {
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/'));
  }
}
