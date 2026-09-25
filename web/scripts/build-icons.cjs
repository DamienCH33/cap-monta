// Copie les icônes utilisées par le site depuis @tabler/icons (licence MIT)
// vers src/app/shared/icon/icons.ts. Pour ajouter une icône : ajouter son nom
// dans NAMES, puis lancer `npm run icons`.
const fs = require('node:fs');
const path = require('node:path');

const NAMES = [
  'alert-circle',
  'arrow-left',
  'beach',
  'building-store',
  'calendar-check',
  'chevron-left',
  'chevron-right',
  'clock',
  'heart',
  'layout-grid',
  'menu-2',
  'paw',
  'photo',
  'receipt-off',
  'refresh',
  'share',
  'ripple',
  'star',
  'trash',
  'trees',
  'upload',
  'user-circle',
  'world',
  'x',
];

const source = path.join(__dirname, '..', 'node_modules', '@tabler', 'icons', 'icons', 'outline');
const target = path.join(__dirname, '..', 'src', 'app', 'shared', 'icon', 'icons.ts');

const entries = NAMES.map((name) => {
  const svg = fs.readFileSync(path.join(source, `${name}.svg`), 'utf8');

  const body = svg
    .replace(/^[\s\S]*?<svg[^>]*>/, '') // retire la balise <svg> d'ouverture
    .replace(/<\/svg>\s*$/, '') // et celle de fermeture
    .replace(/\s+/g, ' ')
    .replace(/<path(?=[a-z])/g, '<path '); // remet l'espace si la source l'a perdu

  const paths = [];

  for (const [, tag, attributes] of body.matchAll(/<(\w+)\b([^>]*)>/g)) {
    // Le composant ne sait dessiner que des <path> : on refuse le reste plutôt que de le perdre.
    if (tag !== 'path') {
      throw new Error(`${name} : élément <${tag}> non pris en charge`);
    }

    if (/stroke="none"/.test(attributes)) {
      continue; // cadre transparent 24×24, inutile
    }

    const d = attributes.match(/\bd="([^"]+)"/);

    if (!d) {
      throw new Error(`${name} : tracé sans attribut d`);
    }

    paths.push(d[1]);
  }

  return `  ${JSON.stringify(name)}: ${JSON.stringify(paths)},`;
});

const output = `// Fichier généré par scripts/build-icons.cjs depuis @tabler/icons (MIT).
// Ne pas modifier à la main : lancer \`npm run icons\`.

export const ICONS = {
${entries.join('\n')}
} as const;

export type IconName = keyof typeof ICONS;
`;

fs.mkdirSync(path.dirname(target), { recursive: true });
fs.writeFileSync(target, output);
console.log(`${NAMES.length} icônes écrites dans ${path.relative(process.cwd(), target)}`);
