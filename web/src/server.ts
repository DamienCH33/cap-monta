import {
  AngularNodeAppEngine,
  createNodeRequestHandler,
  isMainModule,
  writeResponseToNodeResponse,
} from '@angular/ssr/node';
import compression from 'compression';
import express from 'express';
import { createProxyMiddleware } from 'http-proxy-middleware';
import { join } from 'node:path';
import { APP_PATHS } from './app/app.paths';
import { LangOption, LANGS, pathIn } from './app/core/i18n/lang';

const browserDistFolder = join(import.meta.dirname, '../browser');

const production = 'production' === process.env['NODE_ENV'];

/** L'API Symfony, vue d'ici : l'adresse interne de Railway en production. */
const apiUrl = (process.env['API_URL'] ?? 'http://127.0.0.1:8001').replace(/\/$/, '');

/** L'origine publique, celle qui apparaît dans le sitemap. */
const siteUrl = (process.env['SITE_URL'] ?? 'http://localhost:4201').replace(/\/$/, '');

// Un sitemap qui pointe vers localhost en production serait pire que pas de sitemap.
if (production && !process.env['SITE_URL']) {
  throw new Error('SITE_URL manquant : l’adresse publique du site (https://…).');
}

/** Un appel à l'API qui ne répond pas ne doit pas bloquer une requête indéfiniment. */
const API_TIMEOUT_MS = 5_000;

/**
 * Les chemins qu'Angular sait rendre (app.paths.ts), dans chaque langue : « /recherche »,
 * « /en/recherche »… « :slug » devient « n'importe quoi sauf un / ».
 */
const prefixes = LANGS.map((lang) => lang.prefix.replace('/', ''))
  .filter(Boolean)
  .join('|');
const knownRoutes = APP_PATHS.map(
  (path) => new RegExp(`^(?:/(?:${prefixes}))?/${path.replace(/:[^/]+/g, '[^/]+')}/?$`),
);

function isKnownRoute(pathname: string): boolean {
  return knownRoutes.some((pattern) => pattern.test(pathname));
}

/**
 * Au rendu serveur, Angular appelle « /api/… » sur l'adresse de la page demandée, donc sur le
 * domaine public : un aller-retour par Internet pour revenir ici. Ces appels partent
 * directement vers l'API interne. Les URL restent relatives côté application, ce qui garde
 * le cache de transfert : le navigateur ne refait pas les appels déjà faits au rendu.
 */
const siteHosts = new Set(
  [new URL(siteUrl).host, ...(process.env['NG_ALLOWED_HOSTS'] ?? '').split(',')]
    .map((host) => host.trim())
    .filter(Boolean),
);
const publicFetch = globalThis.fetch;

globalThis.fetch = (input, init) => {
  const url = new URL(input instanceof Request ? input.url : input.toString());
  const ownHost =
    siteHosts.has(url.host) || siteHosts.has(url.hostname) || 'localhost' === url.hostname;

  if (ownHost && url.pathname.startsWith('/api/')) {
    const internal = `${apiUrl}${url.pathname}${url.search}`;

    return publicFetch(input instanceof Request ? new Request(internal, input) : internal, init);
  }

  return publicFetch(input, init);
};

const app = express();

// Pages rendues et relais de l'API compressés (gzip) : Railway ne le fait pas toujours.
app.use(compression());
// Derrière le proxy de Railway, l'adresse d'origine (https, domaine) arrive dans ces en-têtes.
const angularApp = new AngularNodeAppEngine({
  trustProxyHeaders: ['x-forwarded-proto', 'x-forwarded-host'],
});

// Pas de « X-Powered-By: Express » : inutile de dire aux curieux ce qui tourne.
app.disable('x-powered-by');

/**
 * En-têtes de sécurité de toutes les pages. La politique de contenu (CSP) ne restreint pas
 * les scripts : Angular injecte ses propres scripts en ligne au rendu serveur. Elle interdit
 * ce qui ne sert jamais ici (plugins, iframes, changement de <base>, formulaires vers
 * ailleurs), dont l'affichage du site dans le cadre d'un autre (clickjacking).
 */
app.use((request, response, next) => {
  response.set({
    'Content-Security-Policy':
      "frame-ancestors 'none'; object-src 'none'; base-uri 'self'; form-action 'self'",
    'X-Frame-Options': 'DENY',
    'X-Content-Type-Options': 'nosniff',
    'Referrer-Policy': 'strict-origin-when-cross-origin',
    'Permissions-Policy': 'camera=(), microphone=(), geolocation=(), payment=()',
  });

  // HSTS seulement derrière HTTPS (Railway le signale par X-Forwarded-Proto).
  if ('https' === request.get('x-forwarded-proto')) {
    response.set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
  }

  next();
});

/**
 * Un seul domaine public (décision 009) : « /api/… » (et les photos, « /media/photos/… ») est relayé
 * tel quel vers Symfony.
 * Les cookies de session restent sur le même domaine, sans CORS. X-Forwarded-For est
 * transmis tel que le bord de Railway l'a posé, sans y ajouter d'adresse : Symfony, qui
 * fait confiance à ce serveur (TRUSTED_PROXIES), y lit l'IP réelle pour ses limiteurs.
 */
app.use(
  createProxyMiddleware({
    // /media/photos : les photos des logements, servies par l'API (volume Railway). Pas tout
    // « /media » : Angular y range aussi les polices du site, que ce serveur sert lui-même.
    pathFilter: ['/api', '/media/photos'],
    target: apiUrl,
    changeOrigin: false,
    xfwd: false,
    proxyTimeout: 30_000,
    timeout: 30_000,
    on: {
      error: (_error, _request, response) => {
        if ('writeHead' in response && !response.headersSent) {
          response.writeHead(502, { 'Content-Type': 'application/problem+json' });
        }
        response.end('{"title":"L’API ne répond pas.","status":502}');
      },
    },
  }),
);

interface AccommodationSummary {
  slug: string;
}

interface JsonLdCollection {
  member: AccommodationSummary[];
  view?: { next?: string };
}

/** Tous les logements en ligne : la liste est paginée, on suit les pages jusqu'au bout. */
async function listSlugs(): Promise<string[]> {
  const slugs: string[] = [];
  let next: string | undefined = '/api/accommodations?page=1';

  for (let pages = 0; next && pages < 200; pages++) {
    const response = await fetch(`${apiUrl}${next}`, {
      headers: { Accept: 'application/ld+json' },
      signal: AbortSignal.timeout(API_TIMEOUT_MS),
    });

    if (!response.ok) {
      throw new Error(`API responded ${response.status}`);
    }

    const payload = (await response.json()) as JsonLdCollection;
    slugs.push(...payload.member.map((accommodation) => accommodation.slug));
    next = payload.view?.next;
  }

  return slugs;
}

/**
 * robots.txt servi par le serveur : l'adresse du sitemap suit SITE_URL. Les pages privées
 * (espace propriétaire, suivi d'une demande par son lien personnel) restent hors index.
 * La page propriétaires publique (/proprietaire) est indexable.
 */
app.get('/robots.txt', (_request, response) => {
  const privatePaths = ['/api/', '/mon-espace', '/demande/', '/*/demande/'];

  response
    .type('text/plain')
    .set('Cache-Control', 'public, max-age=3600')
    .send(
      [
        'User-agent: *',
        'Allow: /',
        ...privatePaths.map((path) => `Disallow: ${path}`),
        '',
        `Sitemap: ${siteUrl}/sitemap.xml`,
        '',
      ].join('\n'),
    );
});

/**
 * Sitemap construit à la demande : la liste des logements change dès qu'un
 * propriétaire publie, un fichier statique serait périmé le lendemain.
 */
app.get('/sitemap.xml', async (_request, response) => {
  // Pages traduites : une entrée par langue, avec les alternatives hreflang.
  const paths = ['/', '/recherche', '/comment-ca-marche'];
  // Espace propriétaire et pages légales : en français seulement.
  const frenchOnly = [
    '/proprietaire',
    '/mentions-legales',
    '/conditions-generales',
    '/confidentialite',
  ];

  try {
    for (const slug of await listSlugs()) {
      paths.push(`/logement/${encodeURIComponent(slug)}`);
    }

    for (const slug of await listDistrictSlugs()) {
      paths.push(`/quartier/${encodeURIComponent(slug)}`);
    }
  } catch (error) {
    // Une API indisponible ne doit pas produire une erreur 500 : Google
    // retenterait plus tard, mais un sitemap partiel vaut mieux qu'aucun.
    console.error('sitemap: API injoignable, seules les pages fixes sont listées', error);
  }

  async function listDistrictSlugs(): Promise<string[]> {
    const response = await fetch(`${apiUrl}/api/districts`, {
      headers: { Accept: 'application/ld+json' },
      signal: AbortSignal.timeout(API_TIMEOUT_MS),
    });

    if (!response.ok) {
      throw new Error(`API responded ${response.status}`);
    }

    const payload = (await response.json()) as { member: { slug: string }[] };

    return payload.member.map((district) => district.slug);
  }

  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">',
    ...paths.flatMap((path) => LANGS.map((lang) => localizedUrl(lang, path))),
    ...frenchOnly.map((path) => `  <url><loc>${siteUrl}${path}</loc></url>`),
    '</urlset>',
  ].join('\n');

  /** « /en/recherche », avec ses versions dans les autres langues (Google les regroupe). */
  function localizedUrl(lang: LangOption, path: string): string {
    const alternates = LANGS.map(
      (lang) =>
        `    <xhtml:link rel="alternate" hreflang="${lang.code}" href="${siteUrl}${pathIn(lang, path)}"/>`,
    );
    alternates.push(
      `    <xhtml:link rel="alternate" hreflang="x-default" href="${siteUrl}${path}"/>`,
    );

    return [`  <url><loc>${siteUrl}${pathIn(lang, path)}</loc>`, ...alternates, '  </url>'].join(
      '\n',
    );
  }

  response.type('application/xml').set('Cache-Control', 'public, max-age=3600').send(xml);
});

/**
 * Serve static files from /browser
 */
/**
 * Fichiers statiques. Ceux dont le nom porte une empreinte (« main-AB12CD34.js ») ne
 * changent jamais : cache d'un an. Les autres (robots.txt, favicon, photos du site)
 * peuvent changer sans changer de nom : une heure.
 */
const FINGERPRINTED = /-[\w-]{8}\.(?:js|mjs|css|woff2?)$/;

app.use(
  express.static(browserDistFolder, {
    index: false,
    redirect: false,
    setHeaders: (response, path) => {
      response.setHeader(
        'Cache-Control',
        FINGERPRINTED.test(path) ? 'public, max-age=31536000, immutable' : 'public, max-age=3600',
      );
    },
  }),
);

/**
 * Handle all other requests by rendering the Angular application.
 * Un chemin qu'aucune route ne reconnaît est rendu par la page 404 : il doit
 * donc partir avec un vrai code 404, sinon Google indexe une « soft 404 ».
 */
app.use((req, res, next) => {
  angularApp
    .handle(req)
    .then((response) => {
      if (!response) {
        return next();
      }

      const { pathname } = new URL(req.url, `http://${req.headers.host ?? 'localhost'}`);

      if (isKnownRoute(pathname)) {
        return writeResponseToNodeResponse(response, res);
      }

      return writeResponseToNodeResponse(
        new Response(response.body, { status: 404, headers: response.headers }),
        res,
      );
    })
    .catch(next);
});

/**
 * Start the server if this module is the main entry point, or it is ran via PM2.
 * The server listens on the port defined by the `PORT` environment variable, or defaults to 4000.
 */
if (isMainModule(import.meta.url) || process.env['pm_id']) {
  const port = process.env['PORT'] || 4000;
  app.listen(port, (error) => {
    if (error) {
      throw error;
    }

    console.log(`Node Express server listening on http://localhost:${port}`);
  });
}

/**
 * Request handler used by the Angular CLI (for dev-server and during build) or Firebase Cloud Functions.
 */
export const reqHandler = createNodeRequestHandler(app);
