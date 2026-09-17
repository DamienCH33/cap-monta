// Copie les icônes utilisées par le site depuis @tabler/icons (licence MIT)
// vers src/app/shared/icon/icons.ts. Pour ajouter une icône : ajouter son nom
// dans NAMES, puis lancer `npm run icons`.
const fs = require('node:fs');
const path = require('node:path');

const NAMES = ['arrow-left', 'beach', 'chevron-left', 'chevron-right', 'photo'];

const source = path.join(__dirname, '..', 'node_modules', '@tabler', 'icons', 'icons', 'outline');
const target = path.join(__dirname, '..', 'src', 'app', 'shared', 'icon', 'icons.ts');

const entries = NAMES.map((name) => {
  const svg = fs.readFileSync(path.join(source, `${name}.svg`), 'utf8');

  const inner = svg
    .replace(/^[\s\S]*?<svg[^>]*>/, '')
    .replace(/<\/svg>\s*$/, '')
    .replace(/<path[^>]*stroke="none"[^>]*\/>/, '')
    .replace(/\s+/g, ' ')
    .replace(/<path(?=[a-z])/g, '<path ') // remet l'espace si la source l'a perdu
    .trim();

  return `  ${JSON.stringify(name)}: ${JSON.stringify(inner)},`;
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
