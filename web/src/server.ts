import {
  AngularNodeAppEngine,
  createNodeRequestHandler,
  isMainModule,
  writeResponseToNodeResponse,
} from '@angular/ssr/node';
import express from 'express';
import { join } from 'node:path';
import { routes } from './app/app.routes';

const browserDistFolder = join(import.meta.dirname, '../browser');

/** L'API que le serveur interroge pour construire le sitemap. */
const apiUrl = process.env['API_URL'] ?? 'http://127.0.0.1:8001';

/** L'origine publique, celle qui apparaît dans le sitemap. */
const siteUrl = process.env['SITE_URL'] ?? 'http://localhost:4201';

/**
 * Les chemins qu'Angular sait rendre, dérivés de app.routes.ts plutôt que recopiés :
 * une route ajoutée là-bas est reconnue ici sans qu'on y pense.
 * « :slug » devient « n'importe quoi sauf un / ».
 */
const knownRoutes = routes
  .map((route) => route.path)
  .filter((path): path is string => 'string' === typeof path && '**' !== path)
  .map((path) => new RegExp(`^/${path.replace(/:[^/]+/g, '[^/]+')}/?$`));

function isKnownRoute(pathname: string): boolean {
  return knownRoutes.some((pattern) => pattern.test(pathname));
}

const app = express();
const angularApp = new AngularNodeAppEngine();

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

interface AccommodationSummary {
  slug: string;
}

interface JsonLdCollection {
  member: AccommodationSummary[];
}

async function listSlugs(): Promise<string[]> {
  const response = await fetch(`${apiUrl}/api/accommodations`, {
    headers: { Accept: 'application/ld+json' },
  });

  if (!response.ok) {
    throw new Error(`API responded ${response.status}`);
  }

  const payload = (await response.json()) as JsonLdCollection;

  return payload.member.map((accommodation) => accommodation.slug);
}

/**
 * Sitemap construit à la demande : la liste des logements change dès qu'un
 * propriétaire publie, un fichier statique serait périmé le lendemain.
 */
app.get('/sitemap.xml', async (_request, response) => {
  const paths = [
    '/',
    '/recherche',
    '/proprietaire',
    '/comment-ca-marche',
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
    });

    if (!response.ok) {
      throw new Error(`API responded ${response.status}`);
    }

    const payload = (await response.json()) as { member: { slug: string }[] };

    return payload.member.map((district) => district.slug);
  }

  const xml = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
    ...paths.map((path) => `  <url><loc>${siteUrl}${path}</loc></url>`),
    '</urlset>',
  ].join('\n');

  response.type('application/xml').set('Cache-Control', 'public, max-age=3600').send(xml);
});

/**
 * Serve static files from /browser
 */
app.use(
  express.static(browserDistFolder, {
    maxAge: '1y',
    index: false,
    redirect: false,
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
