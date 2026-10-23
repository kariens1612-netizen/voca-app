import { readFile, writeFile } from 'node:fs/promises';

const manifest = JSON.parse(await readFile(new URL('../../build/manifest.json', import.meta.url), 'utf8'));
const entry = manifest['resources/js/app.js'];
const stylesheet = manifest['resources/css/app.css']?.file || entry.css?.[0];

if (!entry?.file || !stylesheet) {
    throw new Error('Unable to find the Voca frontend assets in the Vite manifest.');
}

const index = `<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0b2b4b">
    <title>Voca</title>
    <link rel="stylesheet" href="/${stylesheet}">
  </head>
  <body>
    <div id="app"></div>
    <script type="module" src="/${entry.file}"></script>
  </body>
</html>
`;

await writeFile(new URL('../../build/index.html', import.meta.url), index);
