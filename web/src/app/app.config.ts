import { provideHttpClient, withInterceptors, withXhr } from '@angular/common/http';
import { ApplicationConfig, provideBrowserGlobalErrorListeners } from '@angular/core';
import { provideRouter } from '@angular/router';
import { provideClientHydration } from '@angular/platform-browser';

import { routes } from './app.routes';
import { sessionExpiredInterceptor } from './core/http/session-expired';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes),
    provideClientHydration(),
    // XHR dans le navigateur : c'est lui qui rend la progression de l'envoi des photos.
    provideHttpClient(withXhr(), withInterceptors([sessionExpiredInterceptor])),
  ],
};
