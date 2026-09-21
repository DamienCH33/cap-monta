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

import { RenderMode, ServerRoute } from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  // Pages privées : rendues dans le navigateur uniquement. Le serveur n'a pas le
  // cookie de session, il conclurait toujours « personne n'est connecté ».
  { path: 'connexion', renderMode: RenderMode.Client },
  { path: 'inscription', renderMode: RenderMode.Client },
  { path: 'mon-espace', renderMode: RenderMode.Client },
  { path: 'mon-espace/**', renderMode: RenderMode.Client },
  { path: 'inscription', renderMode: RenderMode.Client },
  { path: 'mot-de-passe-oublie', renderMode: RenderMode.Client },
  { path: 'nouveau-mot-de-passe', renderMode: RenderMode.Client },

  // Tout le reste est rendu côté serveur : c'est ce qui rend le site indexable.
  { path: '**', renderMode: RenderMode.Server },
];
