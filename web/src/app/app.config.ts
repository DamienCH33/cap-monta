import { provideHttpClient, withInterceptors, withXhr } from '@angular/common/http';
import { ApplicationConfig, LOCALE_ID, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideClientHydration } from '@angular/platform-browser';

import { routes } from './app.routes';
import { sessionExpiredInterceptor } from './core/http/session-expired';
import { currentLang } from './core/i18n/lang';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    // Fixée par le build (voir currentLang) : la valeur par défaut d'Angular lit la même
    // variable globale partagée entre les langues côté serveur (dates, pluriels).
    { provide: LOCALE_ID, useValue: currentLang() },
    provideRouter(routes),
    provideClientHydration(),
    // XHR dans le navigateur : c'est lui qui rend la progression de l'envoi des photos.
    provideHttpClient(withXhr(), withInterceptors([sessionExpiredInterceptor])),
  ],
};
