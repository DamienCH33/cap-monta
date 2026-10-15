import type { RequestHandler } from 'express';
import { request as httpRequest, type IncomingHttpHeaders } from 'node:http';
import { request as httpsRequest } from 'node:https';
import { isIP } from 'node:net';

/** Ce qui part vers Symfony : l'API, et les photos des logements (servies par l'API). */
const PROXIED_PREFIXES = ['/api', '/media/photos'];

/** En-têtes propres à une connexion, qui ne se recopient pas d'un saut à l'autre. */
const HOP_BY_HOP = new Set([
  'connection',
  'keep-alive',
  'proxy-connection',
  'proxy-authenticate',
  'proxy-authorization',
  'te',
  'trailer',
  'transfer-encoding',
  'upgrade',
]);

const TIMEOUT_MS = 30_000;

/** Une seule adresse IPv4 ou IPv6 bien formée, sinon null (en local, pas de X-Real-IP). */
export function clientIp(header: string | string[] | undefined): string | null {
  const value = (Array.isArray(header) ? header[0] : header)?.trim() ?? '';

  return isIP(value) ? value : null;
}

/** « /api », « /api/… », « /api?… » : oui. « /apis » ou « /media/polices » : non. */
export function isProxied(url: string): boolean {
  return PROXIED_PREFIXES.some(
    (prefix) => url === prefix || url.startsWith(`${prefix}/`) || url.startsWith(`${prefix}?`),
  );
}

function withoutHopByHop(headers: IncomingHttpHeaders): IncomingHttpHeaders {
  return Object.fromEntries(
    Object.entries(headers).filter(([name]) => !HOP_BY_HOP.has(name.toLowerCase())),
  );
}

/**
 * Relais vers Symfony, écrit à la main avec node:http : http-proxy-middleware tirait
 * micromatch, puis braces, signalé « high » par npm audit sans version corrigée. On ne
 * filtre que deux préfixes fixes : pas besoin de motifs.
 *
 * L'adresse du visiteur : le bord de Railway ajoute l'IP réelle au X-Forwarded-For envoyé par
 * le client, qui peut donc y glisser de fausses adresses privées, que Symfony (qui fait
 * confiance aux réseaux privés) prenait pour la vraie : les limiteurs de débit se
 * contournaient (audit de prod du 30/09). Railway pose aussi X-Real-IP, que le client ne
 * peut pas fixer : c'est elle qu'on transmet, seule, dans X-Forwarded-For. Le X-Forwarded-For
 * du client, lui, ne passe jamais.
 */
export function apiProxy(target: string): RequestHandler {
  const base = new URL(target);
  const send = 'https:' === base.protocol ? httpsRequest : httpRequest;

  return (request, response, next) => {
    const url = request.originalUrl;

    if (!isProxied(url)) {
      next();

      return;
    }

    const headers = withoutHopByHop(request.headers);
    delete headers['x-forwarded-for'];

    const realIp = clientIp(request.headers['x-real-ip']);
    if (null !== realIp) {
      headers['x-forwarded-for'] = realIp;
    }

    const failed = (): void => {
      if (!response.headersSent) {
        response.writeHead(502, { 'Content-Type': 'application/problem+json' });
        response.end('{"title":"L’API ne répond pas.","status":502}');
      } else {
        response.destroy();
      }
    };

    const upstream = send(
      {
        protocol: base.protocol,
        hostname: base.hostname,
        port: base.port || undefined,
        method: request.method,
        path: `${base.pathname.replace(/\/$/, '')}${url}`,
        // Même Host que le visiteur (changeOrigin: false) : Symfony construit ses liens dessus.
        headers,
        timeout: TIMEOUT_MS,
      },
      (answer) => {
        response.writeHead(answer.statusCode ?? 502, withoutHopByHop(answer.headers));
        answer.pipe(response);
        answer.on('error', () => response.destroy());
      },
    );

    upstream.on('timeout', () => upstream.destroy(new Error('timeout')));
    upstream.on('error', failed);
    request.on('aborted', () => upstream.destroy());
    request.pipe(upstream);
  };
}
