import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink } from '@angular/router';
import { filter } from 'rxjs';

import { AuthService } from '../../core/services/auth';
import { GuestRequests } from '../../core/services/guest-requests';
import { Icon } from '../../shared/icon/icon';

@Component({
  selector: 'cm-header',
  imports: [Icon, RouterLink],
  templateUrl: './header.html',
  styleUrl: './header.scss',
  host: { '(document:keydown.escape)': 'menuOpen.set(false)' },
})
export class Header {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  readonly owner = this.auth.currentOwner;
  readonly sessionChecked = this.auth.sessionChecked;
  /** Demandes envoyées depuis ce navigateur : le lien « Mes demandes » apparaît. */
  readonly guestRequests = inject(GuestRequests).list;

  /** Sur téléphone, les liens sont repliés derrière le bouton « Menu ». */
  readonly menuOpen = signal(false);

  constructor() {
    this.auth.restore().subscribe();

    // Un lien suivi referme le menu : la page suivante s'affiche en entier.
    this.router.events
      .pipe(
        filter((event) => event instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.menuOpen.set(false));
  }

  toggleMenu(): void {
    this.menuOpen.update((open) => !open);
  }

  logout(): void {
    this.menuOpen.set(false);
    this.auth.logout().subscribe(() => void this.router.navigateByUrl('/'));
  }
}
