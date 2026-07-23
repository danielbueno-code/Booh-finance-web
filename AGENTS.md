# AGENTS.md

## Cursor Cloud specific instructions

This repo is a **static website** (Booh Finance landing page) — plain HTML/CSS/JS with no
package manager, build step, lint, or automated tests. Pages: `index.html`, `ia-pricing.html`,
`Ia-scoring.html`, `simulador.html`; assets in `style.css`, `animations.js`, `menu.js`, `img/`.

### Run (development)
Serve the repo root with any static file server, e.g.:

```
python3 -m http.server 8000
```

Then open `http://localhost:8000/index.html`. The core interactive feature is the credit
simulator at `/simulador.html` (pure client-side JS; enter monthly sales + requested amount,
click "Calcular" to compute the RBF repayment schedule).

### Lint / test / build
None exist. There is no build (Netlify/IONOS publish directory is `.`, i.e. the files are served
as-is) and no test/lint tooling. Do not invent a build step.

### PHP endpoints (out of scope for local dev)
`callback.php`, `info.php`, and `api/hubrise/callback.php` are HubRise OAuth callbacks. They are
served in production by PHP-capable IONOS hosting and require real HubRise OAuth credentials plus
live outbound network calls, so they cannot be meaningfully exercised locally. PHP is not required
to develop or test the landing pages / simulator. If you do need to serve the PHP files, install a
PHP CLI and run `php -S localhost:8000` instead of the Python server.

### Deployment
CI (`.github/workflows/`) deploys via IONOS "Deploy Now" and requires IONOS secrets; it is not
runnable locally.
