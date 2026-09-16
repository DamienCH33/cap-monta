import { Routes } from '@angular/router';

import { Home } from './pages/home/home';
import { Search } from './pages/search/search';
import { AccommodationPage } from './pages/accommodation/accommodation';

export const routes: Routes = [
  { path: '', component: Home },
  { path: 'recherche', component: Search },
  { path: 'logement/:slug', component: AccommodationPage },
];
