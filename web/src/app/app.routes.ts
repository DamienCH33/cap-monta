import { Routes } from '@angular/router';

import { Home } from './pages/home/home';
import { Search } from './pages/search/search';
import { AccommodationPage } from './pages/accommodation/accommodation';
import { Privacy } from './pages/privacy/privacy';
import { Terms } from './pages/terms/terms';
import { LegalNotice } from './pages/legal-notice/legal-notice';
import { HowItWorks } from './pages/how-it-works/how-it-works';

export const routes: Routes = [
  { path: '', component: Home },
  { path: 'recherche', component: Search },
  { path: 'logement/:slug', component: AccommodationPage },
  { path: 'comment-ca-marche', component: HowItWorks },
  { path: 'mentions-legales', component: LegalNotice },
  { path: 'conditions-generales', component: Terms },
  { path: 'confidentialite', component: Privacy },
];
