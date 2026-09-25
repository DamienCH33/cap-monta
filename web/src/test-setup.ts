// Les tests tournent sans traduction : $localize rend le texte source, en français.
import { registerLocaleData } from '@angular/common';
import localeFr from '@angular/common/locales/fr';

registerLocaleData(localeFr);
$localize.locale = 'fr';
