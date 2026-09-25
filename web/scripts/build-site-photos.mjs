// Prépare les photos du site (accueil, zones, bandeau propriétaires) à partir des originaux.
//
//   npm run photos                      (lit ~/Images/cap-monta-site/photos.json)
//   SITE_PHOTOS_DIR=/autre/dossier npm run photos
//
// Pour chaque photo déclarée dans photos.json : AVIF + WebP en plusieurs largeurs (jamais
// agrandie), écrits dans public/photos/site/, et src/app/core/site-photos.ts réécrit avec les
// dimensions, le texte alternatif et le crédit. Les originaux restent hors du dépôt.
//
// Le script s'arrête sur un message clair si un fichier manque ou si une photo est trop petite
// pour l'emplacement choisi (elle serait floue à l'écran).

import { existsSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as prettier from 'prettier';
import sharp from 'sharp';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const sourceDir = (
  process.env.SITE_PHOTOS_DIR ?? join(homedir(), 'Images', 'cap-monta-site')
).replace(/^~(?=$|\/)/, homedir());
const outputDir = join(root, 'public', 'photos', 'site');
const moduleFile = join(root, 'src', 'app', 'core', 'site-photos.ts');

/** Emplacements connus : largeur minimale de l'original et largeurs produites. */
const SLOTS = {
  hero: { minWidth: 1920, widths: [640, 960, 1280, 1920, 2560] },
  dunes: { minWidth: 1000, widths: [480, 800, 1200] },
  central: { minWidth: 1000, widths: [480, 800, 1200] },
  roadside: { minWidth: 1000, widths: [480, 800, 1200] },
  owner: { minWidth: 1000, widths: [480, 800, 1200] },
};

function fail(message) {
  console.error(`\n✗ ${message}\n`);
  process.exit(1);
}

const configFile = join(sourceDir, 'photos.json');
if (!existsSync(configFile)) {
  fail(
    `${configFile} introuvable.\n` +
      `  Créez le dossier ${sourceDir}, mettez-y vos photos et un photos.json\n` +
      `  (modèle : web/scripts/photos.example.json).`,
  );
}

let config;
try {
  config = JSON.parse(readFileSync(configFile, 'utf8'));
} catch (error) {
  fail(`photos.json n'est pas du JSON valide : ${error.message}`);
}

const unknown = Object.keys(config).filter((slot) => !(slot in SLOTS));
if (unknown.length) {
  fail(
    `Emplacement inconnu dans photos.json : ${unknown.join(', ')}. Connus : ${Object.keys(SLOTS).join(', ')}.`,
  );
}
if (!config.hero) {
  fail('photos.json doit au moins déclarer "hero" (la photo de l\'accueil).');
}

// Tout vérifier avant d'écrire quoi que ce soit.
const jobs = [];
for (const [slot, entry] of Object.entries(config)) {
  for (const field of ['file', 'alt', 'credit']) {
    if (typeof entry[field] !== 'string' || '' === entry[field].trim()) {
      fail(`"${slot}" : le champ "${field}" est obligatoire (voir photos.example.json).`);
    }
  }
  const file = join(sourceDir, entry.file);
  if (!existsSync(file)) {
    fail(`"${slot}" : ${file} introuvable.`);
  }
  // rotate() applique l'orientation EXIF : les dimensions lues sont celles de la photo redressée.
  const { data, info } = await sharp(file).rotate().toBuffer({ resolveWithObject: true });
  if (info.width < SLOTS[slot].minWidth) {
    fail(
      `"${slot}" : ${entry.file} fait ${info.width} px de large, il en faut au moins ` +
        `${SLOTS[slot].minWidth} pour qu'elle reste nette. Prenez l'original en pleine taille.`,
    );
  }
  jobs.push({ slot, entry, data, width: info.width, height: info.height });
}

rmSync(outputDir, { recursive: true, force: true });
mkdirSync(outputDir, { recursive: true });

const photos = {};
for (const { slot, entry, data, width, height } of jobs) {
  const widths = SLOTS[slot].widths.filter((w) => w <= width);
  if (!widths.includes(Math.min(width, SLOTS[slot].widths.at(-1)))) {
    widths.push(Math.min(width, SLOTS[slot].widths.at(-1)));
  }

  for (const w of widths) {
    const resized = sharp(data).resize({ width: w, withoutEnlargement: true });
    await resized
      .clone()
      .avif({ quality: 55, effort: 6 })
      .toFile(join(outputDir, `${slot}-${w}.avif`));
    await resized
      .clone()
      .webp({ quality: 80 })
      .toFile(join(outputDir, `${slot}-${w}.webp`));
  }

  if ('hero' === slot) {
    // Aperçu des liens partagés (Facebook, WhatsApp…) : 1200 × 630, en JPEG, lu partout.
    await sharp(data)
      .resize(1200, 630, { fit: 'cover', position: 'attention' })
      .jpeg({ quality: 82, mozjpeg: true })
      .toFile(join(outputDir, 'partage.jpg'));
  }

  photos[slot] = {
    widths,
    ratio: Math.round((width / height) * 1000) / 1000,
    alt: entry.alt.trim(),
    focus: entry.focus ?? 'center',
    credit: entry.credit.trim(),
    license: entry.license?.trim() || null,
    source: entry.source?.trim() || null,
  };
  console.log(`✓ ${slot} : ${entry.file} (${width} × ${height}) → ${widths.join(', ')} px`);
}

const source = `// Fichier écrit par \`npm run photos\` (scripts/build-site-photos.mjs) : ne pas modifier à la main.
import { SitePhotos } from './models/site-photo';

export const SITE_PHOTOS: SitePhotos = ${JSON.stringify(photos, null, 2)};
`;
const options = (await prettier.resolveConfig(moduleFile)) ?? {};
writeFileSync(moduleFile, await prettier.format(source, { ...options, filepath: moduleFile }));
console.log(`\n${Object.keys(photos).length} photo(s) prête(s) dans public/photos/site/.`);
