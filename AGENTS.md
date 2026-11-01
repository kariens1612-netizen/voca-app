# Deployment requirements

Production uses a Hostinger Agency Cloud hosting package. Node/npm builds cannot run on the production server.

For frontend changes, run `npm run build` locally from `homedir/apps/voca/app-src` and include the generated production assets in Git alongside the source changes. Track `homedir/apps/voca/build/` (including the Vite manifest), `homedir/apps/voca/manifest.webmanifest`, `homedir/apps/voca/sw.js`, and `homedir/apps/voca/workbox-*.js`. Check that new assets and removed obsolete assets are included before committing a release.

Keep credentials, `.env`, dependencies, local Vite `hot` files, and native build/cache directories excluded. Do not replace the local build requirement with instructions to run npm on Hostinger.
