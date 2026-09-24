import { Routes } from '@angular/router';

import { Home } from './pages/home/home';
import { ownerGuard } from './core/guards/owner-guard';

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
  {
    path: 'connexion',
    loadComponent: () => import('./pages/login/login').then((m) => m.Login),
  },
  {
    path: 'mon-espace',
    canActivate: [ownerGuard],
    loadComponent: () => import('./pages/owner-home/owner-home').then((m) => m.OwnerHome),
  },
  {
    path: 'mon-espace/logements/nouveau',
    canActivate: [ownerGuard],
    loadComponent: () =>
      import('./pages/owner-accommodation-form/owner-accommodation-form').then(
        (m) => m.OwnerAccommodationForm,
      ),
  },
  {
    path: 'mon-espace/logements/:slug/modifier',
    canActivate: [ownerGuard],
    loadComponent: () =>
      import('./pages/owner-accommodation-form/owner-accommodation-form').then(
        (m) => m.OwnerAccommodationForm,
      ),
  },
  {
    path: 'mon-espace/importer',
    canActivate: [ownerGuard],
    loadComponent: () => import('./pages/owner-import/owner-import').then((m) => m.OwnerImport),
  },
  {
    path: 'mon-espace/demandes',
    canActivate: [ownerGuard],
    loadComponent: () =>
      import('./pages/owner-requests/owner-requests').then((m) => m.OwnerRequests),
  },
  {
    path: 'mon-espace/logements/:slug/tarifs',
    canActivate: [ownerGuard],
    loadComponent: () => import('./pages/owner-rates/owner-rates').then((m) => m.OwnerRates),
  },
  {
    path: 'mon-espace/profil',
    canActivate: [ownerGuard],
    loadComponent: () => import('./pages/owner-profile/owner-profile').then((m) => m.OwnerProfile),
  },
  {
    path: 'mes-demandes',
    loadComponent: () => import('./pages/my-requests/my-requests').then((m) => m.MyRequests),
  },
  {
    path: 'demande/:token',
    loadComponent: () =>
      import('./pages/booking-tracking/booking-tracking').then((m) => m.BookingTracking),
  },
  {
    path: 'mon-espace/logements/:slug/calendrier',
    canActivate: [ownerGuard],
    loadComponent: () =>
      import('./pages/owner-calendar/owner-calendar').then((m) => m.OwnerCalendar),
  },
  {
    path: 'mon-espace/logements',
    canActivate: [ownerGuard],
    loadComponent: () =>
      import('./pages/owner-accommodations/owner-accommodations').then(
        (m) => m.OwnerAccommodations,
      ),
  },
  {
    path: 'inscription',
    loadComponent: () => import('./pages/register/register').then((m) => m.Register),
  },
  {
    path: 'mot-de-passe-oublie',
    loadComponent: () =>
      import('./pages/forgotten-password/forgotten-password').then((m) => m.ForgottenPassword),
  },
  {
    path: 'nouveau-mot-de-passe',
    loadComponent: () =>
      import('./pages/reset-password/reset-password').then((m) => m.ResetPassword),
  },
  {
    path: 'proprietaire',
    loadComponent: () => import('./pages/owner-landing/owner-landing').then((m) => m.OwnerLanding),
  },
  {
    path: '**',
    loadComponent: () => import('./pages/not-found/not-found').then((m) => m.NotFound),
  },
];
