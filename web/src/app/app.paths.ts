/**
 * Les chemins que l'application sait rendre (sans le préfixe de langue), pour le serveur :
 * un chemin absent de cette liste part avec un vrai code 404. Gardé à part d'app.routes.ts,
 * qui charge des composants et des textes traduits que le serveur Express n'a pas à charger.
 * Un test vérifie que les deux listes restent identiques.
 */
export const APP_PATHS: readonly string[] = [
  '',
  'recherche',
  'logement/:slug',
  'comment-ca-marche',
  'mentions-legales',
  'conditions-generales',
  'confidentialite',
  'quartier/:slug',
  'connexion',
  'mon-espace',
  'mon-espace/logements/nouveau',
  'mon-espace/logements/:slug/modifier',
  'mon-espace/importer',
  'mon-espace/demandes',
  'mon-espace/logements/:slug/tarifs',
  'mon-espace/profil',
  'favoris',
  'mes-demandes',
  'demande/:token',
  'mon-espace/logements/:slug/calendrier',
  'mon-espace/logements',
  'inscription',
  'mot-de-passe-oublie',
  'nouveau-mot-de-passe',
  'proprietaire',
];
