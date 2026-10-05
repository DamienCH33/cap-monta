/**
 * Les langues du site visiteur. Le français est la langue source, servie à la racine ;
 * les autres sont des builds à part, servies sous /en, /nl et /de (angular.json, « i18n »).
 * Les pages légales restent en français ; l'espace propriétaire existe dans les 4 langues.
 */
export type Lang = 'fr' | 'en' | 'nl' | 'de';

export interface LangOption {
  code: Lang;
  /** Nom de la langue dans la langue elle-même. */
  label: string;
  /** Préfixe d'adresse : '' pour le français, '/en' pour l'anglais… */
  prefix: string;
  /** Étiquette pour Intl (dates, nombres) et Open Graph. */
  tag: string;
}

export const LANGS: readonly LangOption[] = [
  { code: 'fr', label: 'Français', prefix: '', tag: 'fr-FR' },
  { code: 'en', label: 'English', prefix: '/en', tag: 'en-GB' },
  { code: 'nl', label: 'Nederlands', prefix: '/nl', tag: 'nl-NL' },
  { code: 'de', label: 'Deutsch', prefix: '/de', tag: 'de-DE' },
];

/**
 * La langue du build en cours. Chaîne traduite, donc remplacée à la compilation dans chaque
 * build : jamais lue dans `$localize.locale`, qui est une variable globale que les quatre
 * builds se partagent dans le même serveur Node (une page française rendue pendant une page
 * néerlandaise recevait l'adresse canonique et le titre néerlandais).
 */
export function currentLang(): Lang {
  const code = $localize`:@@lang.code:fr` as Lang;

  return LANGS.some((lang) => lang.code === code) ? code : 'fr';
}

export function currentLangOption(): LangOption {
  return LANGS.find((lang) => lang.code === currentLang()) ?? LANGS[0];
}

/** Le même chemin dans une autre langue : '/recherche' → '/en/recherche'. */
export function pathIn(lang: LangOption, path: string): string {
  const clean = path.startsWith('/') ? path : `/${path}`;

  return `${lang.prefix}${'/' === clean && '' !== lang.prefix ? '/' : clean}`;
}
