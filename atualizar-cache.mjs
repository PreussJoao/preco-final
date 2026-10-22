// Run with Node.js after editing an asset: node atualizar-cache.mjs
// No packages or build server required. HTML-only edits do not need this step.
import { readdir, readFile, writeFile, rename } from 'node:fs/promises';
import { createHash } from 'node:crypto';
import { dirname, join, parse } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = dirname(fileURLToPath(import.meta.url));
const assets = join(root, 'assets');
const documents = [join(root, 'index.html'), join(root, 'quiz', 'index.html')];
let changed = 0;
// Update leaf media first, then their CSS/JS dependents, then document links.
for (const extensions of [/\.(webp|avif|woff2|mp4)$/, /\.css$/, /\.js$/]) {
  const entries = await readdir(assets, { withFileTypes: true });
  const mapping = [];
  for (const entry of entries) {
    if (!entry.isFile() || !extensions.test(entry.name)) continue;
    const file = join(assets, entry.name);
    const bytes = await readFile(file);
    const hash = createHash('sha256').update(bytes).digest('hex').slice(0, 12);
    const { name, ext } = parse(entry.name);
    const next = `${name.replace(/\.[a-f0-9]{12}$/, '')}.${hash}${ext}`;
    if (entry.name !== next) mapping.push([entry.name, next]);
  }
  for (const [before, after] of mapping) {
    await rename(join(assets, before), join(assets, after));
    changed++;
  }
  const textAssets = (await readdir(assets)).filter(name => /\.(css|js)$/.test(name)).map(name => join(assets, name));
  for (const file of [...documents, ...textAssets]) {
    const text = await readFile(file, 'utf8');
    let next = text;
    for (const [before, after] of mapping) next = next.split(before).join(after);
    if (next !== text) await writeFile(file, next, 'utf8');
  }
}
console.log(`${changed} arquivo(s) versionado(s). Publique os assets antes dos HTMLs e mantenha as versões antigas durante a troca.`);
