import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SeoService } from '../../core/services/seo';
import { SearchBar } from '../../shared/search-bar/search-bar';

@Component({
  selector: 'cm-not-found',
  imports: [RouterLink, SearchBar],
  templateUrl: './not-found.html',
  styleUrl: './not-found.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NotFound {
  constructor() {
    inject(SeoService).apply({
      title: 'Page introuvable',
      description: "Cette page n'existe pas ou a été retirée par son propriétaire.",
      path: '/404',
      noindex: true,
    });
  }
}
