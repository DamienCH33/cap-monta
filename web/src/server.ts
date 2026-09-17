import {
  AngularNodeAppEngine,
  createNodeRequestHandler,
  isMainModule,
  writeResponseToNodeResponse,
} from '@angular/ssr/node';
import express from 'express';
import { join } from 'node:path';

const browserDistFolder = join(import.meta.dirname, '../browser');

/** L'API que le serveur interroge pour construire le sitemap. */
const apiUrl = process.env['API_URL'] ?? 'http://127.0.0.1:8001';

/** L'origine publique, celle qui apparaît dans le sitemap. */
const siteUrl = process.env['SITE_URL'] ?? 'http://localhost:4201';

const app = express();
const angularApp = new AngularNodeAppEngine();

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
  const paths = ['/', '/recherche'];

  try {
    for (const slug of await listSlugs()) {
      paths.push(`/logement/${encodeURIComponent(slug)}`);
    }
  } catch (error) {
    // Une API indisponible ne doit pas produire une erreur 500 : Google
    // retenterait plus tard, mais un sitemap partiel vaut mieux qu'aucun.
    console.error('sitemap: API injoignable, seules les pages fixes sont listées', error);
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
 */
app.use((req, res, next) => {
  angularApp
    .handle(req)
    .then((response) => (response ? writeResponseToNodeResponse(response, res) : next()))
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
