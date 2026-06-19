# Booh Finance & IA Pricing Landing

Landing page para captación de clientes de Booh Finance.

## 🚀 Cómo publicarla en Netlify
1. Sube esta carpeta a un repositorio en GitHub.
2. Crea una cuenta en [Netlify](https://netlify.com).
3. En Netlify: **Add new site → Import from GitHub → selecciona tu repo**.
4. En 'Publish directory' pon `.` y pulsa **Deploy**.
5. Conecta tu dominio si lo deseas.

## ✨ Estructura
- `index.html` → contenido principal.
- `style.css` → estilos base Booh.
- `script.js` → animaciones opcionales.
- `/img` → imágenes del sitio.

## 🔐 Callback HubRise en Netlify
El callback OAuth se sirve con una Netlify Function definida en `netlify/functions/hubrise-callback.js`.

Configura estas variables en Netlify antes de usar la integracion:
- `HUBRISE_CLIENT_SECRET` (obligatoria)
- `HUBRISE_CLIENT_ID` (opcional; por defecto usa el client ID publico de la app)
- `HUBRISE_REDIRECT_URI` (opcional; por defecto `https://booh-finance.app/api/hubrise/callback.php`)

## 💡 Contacto
Financiación inteligente para restaurantes digitales.
