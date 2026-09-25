import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { currentLang } from '../../core/i18n/lang';
import { Icon } from '../../shared/icon/icon';
import { LangSwitch } from '../../shared/lang-switch/lang-switch';

@Component({
  selector: 'cm-footer',
  imports: [Icon, LangSwitch, RouterLink],
  templateUrl: './footer.html',
  styleUrl: './footer.scss',
})
export class Footer {
  readonly year = new Date().getFullYear();
  /** L'espace propriétaire n'existe qu'en français : ailleurs, lien vers le site français. */
  readonly french = 'fr' === currentLang();
}
