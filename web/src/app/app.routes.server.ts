import { RenderMode, ServerRoute } from '@angular/ssr';

export const serverRoutes: ServerRoute[] = [
  // Pages privées : rendues dans le navigateur uniquement. Le serveur n'a pas le
  // cookie de session, il conclurait toujours « personne n'est connecté ».
  { path: 'connexion', renderMode: RenderMode.Client },
  { path: 'mon-espace', renderMode: RenderMode.Client },
  { path: 'mon-espace/**', renderMode: RenderMode.Client },

  // Tout le reste est rendu côté serveur : c'est ce qui rend le site indexable.
  { path: '**', renderMode: RenderMode.Server },
];
