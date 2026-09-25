import { Component, computed, input } from '@angular/core';
import { Params, RouterLink } from '@angular/router';
import { Icon } from '../icon/icon';

/**
 * Liens de pages. Ce sont de vrais liens : Google peut les suivre, et on peut
 * ouvrir une page dans un nouvel onglet.
 */
@Component({
  selector: 'cm-pagination',
  imports: [RouterLink, Icon],
  templateUrl: './pagination.html',
  styleUrl: './pagination.scss',
})
export class Pagination {
  readonly pageLabel = $localize`:@@pager.page:Page `;

  readonly page = input.required<number>();
  readonly totalPages = input.required<number>();

  /** Première, dernière, courante et ses voisines ; « gap » remplace ce qu'on saute. */
  readonly items = computed<(number | 'gap')[]>(() => {
    const total = this.totalPages();
    const current = this.page();

    const visible = [...new Set([1, current - 1, current, current + 1, total])]
      .filter((page) => page >= 1 && page <= total)
      .sort((a, b) => a - b);

    const items: (number | 'gap')[] = [];
    let previous = 0;

    for (const page of visible) {
      if (previous > 0 && page - previous > 1) {
        items.push('gap');
      }

      items.push(page);
      previous = page;
    }

    return items;
  });

  paramsFor(page: number): Params {
    // La page 1 ne figure pas dans l'URL : /recherche et /recherche?page=1 seraient
    // deux adresses pour la même liste, ce que Google n'aime pas.
    return { page: page > 1 ? page : null };
  }
}
