import { RenderMode, ServerRoute } from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  // Pages privées : rendues dans le navigateur uniquement. Le serveur n'a pas le
  // cookie de session, il conclurait toujours « personne n'est connecté ».
  { path: 'connexion', renderMode: RenderMode.Client },
  { path: 'inscription', renderMode: RenderMode.Client },
  { path: 'mot-de-passe-oublie', renderMode: RenderMode.Client },
  { path: 'nouveau-mot-de-passe', renderMode: RenderMode.Client },
  { path: 'mon-espace', renderMode: RenderMode.Client },
  { path: 'mon-espace/**', renderMode: RenderMode.Client },
  // Lien privé du voyageur : rien à indexer, et la clé n'a pas à passer par le serveur de rendu.
  { path: 'demande/**', renderMode: RenderMode.Client },
  // Liste lue dans le navigateur (demandes retenues sur cet appareil) : rien à rendre côté serveur.
  { path: 'mes-demandes', renderMode: RenderMode.Client },
  { path: 'favoris', renderMode: RenderMode.Client },

  // Tout le reste est rendu côté serveur : c'est ce qui rend le site indexable.
  { path: '**', renderMode: RenderMode.Server },
];
