import { Routes } from '@angular/router';

import { Home } from './pages/home/home';

/**
 * L'accueil est chargé tout de suite.
 * Les autres pages ne sont téléchargées que lorsqu'on les visite.
 */
export const routes: Routes = [
  { path: '', component: Home },
  {
    path: 'recherche',
    loadComponent: () => import('./pages/search/search').then((m) => m.Search),
  },
  {
    path: 'logement/:slug',
    loadComponent: () =>
      import('./pages/accommodation/accommodation').then((m) => m.AccommodationPage),
  },
  {
    path: 'comment-ca-marche',
    loadComponent: () => import('./pages/how-it-works/how-it-works').then((m) => m.HowItWorks),
  },
  {
    path: 'mentions-legales',
    loadComponent: () => import('./pages/legal-notice/legal-notice').then((m) => m.LegalNotice),
  },
  {
    path: 'conditions-generales',
    loadComponent: () => import('./pages/terms/terms').then((m) => m.Terms),
  },
  {
    path: 'confidentialite',
    loadComponent: () => import('./pages/privacy/privacy').then((m) => m.Privacy),
  },
  {
    path: 'quartier/:slug',
    loadComponent: () => import('./pages/district/district').then((m) => m.DistrictPage),
  },
];
