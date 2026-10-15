import { HttpInterceptorFn } from '@angular/common/http';

import { currentLang } from '../i18n/lang';

/**
 * Chaque appel à l'API dit la langue de la page : l'API répond dans cette langue (messages,
 * refus) et l'enregistre pour les emails (inscription, connexion, demande). Sans cet en-tête,
 * elle prenait la langue du navigateur : un voyageur allemand au navigateur réglé en français
 * recevait ses emails en français. Au rendu serveur aussi, pour les messages de la page.
 */
export const acceptLanguageInterceptor: HttpInterceptorFn = (request, next) =>
  next(
    request.url.includes('/api/')
      ? request.clone({ setHeaders: { 'Accept-Language': currentLang() } })
      : request,
  );
