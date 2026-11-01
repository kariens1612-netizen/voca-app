import { readFile, writeFile, cp, mkdir, readdir } from 'node:fs/promises';

const manifest = JSON.parse(await readFile(new URL('../../build/manifest.json', import.meta.url), 'utf8'));
const entry = manifest['resources/js/app.js'];
// Vite can emit component styles separately from the global stylesheet.
// Include CSS from the entry and its static dependencies, as Laravel's @vite does.
const stylesheets = new Set();
const visited = new Set();
const collectStyles = (key) => {
    if (visited.has(key)) return;
    visited.add(key);
    const chunk = manifest[key];
    for (const dependency of chunk?.imports || []) collectStyles(dependency);
    for (const stylesheet of chunk?.css || []) stylesheets.add(stylesheet);
};
collectStyles('resources/js/app.js');
const globalStylesheet = manifest['resources/css/app.css']?.file;
if (globalStylesheet) stylesheets.add(globalStylesheet);

if (!entry?.file || !stylesheets.size) {
    throw new Error('Unable to find the Voca frontend assets in the Vite manifest.');
}

const index = `<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2b4b">
    <title>Voca</title>
    ${[...stylesheets].map((stylesheet) => `<link rel="stylesheet" href="/${stylesheet}">`).join('\n    ')}
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/${entry.file}"></script>
  </body>
</html>
`;

await writeFile(new URL('../../build/index.html', import.meta.url), index);

// Native apps serve bundled assets rather than Laravel's public directory.
const imageDirectory = new URL('../../build/images/voca/', import.meta.url);
await mkdir(imageDirectory, { recursive: true });
await cp(new URL('../../images/voca/community/', import.meta.url), new URL('community/', imageDirectory), { recursive: true });
for (const filename of ['logo.png', 'africa-logo.png', 'voca-logo.png', 'about-branding.png']) {
    await cp(new URL(`../../images/voca/${filename}`, import.meta.url), new URL(filename, imageDirectory));
}

// Laravel's compiled image URLs start with /build/assets/. Native apps mount
// webDir at /, so retain that URL layout for images referenced by the bundle.
const compiledAssets = new URL('../../build/assets/', import.meta.url);
const nativeImageAssets = new URL('../../build/build/assets/', import.meta.url);
await mkdir(nativeImageAssets, { recursive: true });
for (const filename of await readdir(compiledAssets)) {
    if (/\.(png|webp|jpe?g|gif|svg|avif|ico)$/i.test(filename)) {
        await cp(new URL(filename, compiledAssets), new URL(filename, nativeImageAssets));
    }
}

// Bundle the offline-sharing guide images in native builds.
await cp(new URL('../../images/voca/ethne/', import.meta.url), new URL('ethne/', imageDirectory), { recursive: true });
