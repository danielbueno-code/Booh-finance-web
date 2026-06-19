const HUBRISE_TOKEN_URL = "https://manager.hubrise.com/oauth2/token";
const HUBRISE_ACCOUNTS_URL = "https://api.hubrise.com/v1/accounts";
const DEFAULT_CLIENT_ID = "3555004761.clients.hubrise.com";
const DEFAULT_REDIRECT_URI = "https://booh-finance.app/api/hubrise/callback.php";

const JSON_HEADERS = {
  "Content-Type": "application/json; charset=utf-8",
  "Cache-Control": "no-store",
};

exports.handler = async (event) => {
  if (event.httpMethod !== "GET") {
    return jsonResponse(405, { error: "method_not_allowed" }, { Allow: "GET" });
  }

  try {
    const params = event.queryStringParameters || {};

    if (params.error) {
      return jsonResponse(400, {
        error: "hubrise_authorization_failed",
        message: params.error_description || params.error,
      });
    }

    if (!params.code) {
      return jsonResponse(400, {
        error: "missing_code",
        message: "No se recibio ningun code desde HubRise.",
      });
    }

    const clientId = process.env.HUBRISE_CLIENT_ID || DEFAULT_CLIENT_ID;
    const clientSecret = process.env.HUBRISE_CLIENT_SECRET;
    const redirectUri = process.env.HUBRISE_REDIRECT_URI || DEFAULT_REDIRECT_URI;

    if (!clientSecret) {
      return jsonResponse(500, {
        error: "missing_configuration",
        message: "Configura HUBRISE_CLIENT_SECRET en las variables de entorno de Netlify.",
      });
    }

    const tokenData = await exchangeAuthorizationCode({
      clientId,
      clientSecret,
      redirectUri,
      code: params.code,
    });

    const accounts = await fetchAccounts(tokenData.access_token);

    return jsonResponse(200, {
      status: "success",
      expires_in: tokenData.expires_in,
      scope: tokenData.scope,
      accounts,
    });
  } catch (error) {
    const statusCode = error.statusCode || 502;

    return jsonResponse(statusCode, {
      error: error.code || "hubrise_callback_failed",
      message: error.message || "No se pudo completar la conexion con HubRise.",
    });
  }
};

async function exchangeAuthorizationCode({ clientId, clientSecret, redirectUri, code }) {
  const body = new URLSearchParams({
    grant_type: "authorization_code",
    client_id: clientId,
    client_secret: clientSecret,
    redirect_uri: redirectUri,
    code,
  });

  const response = await fetchWithTimeout(HUBRISE_TOKEN_URL, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: body.toString(),
  });

  const payload = await readJson(response);

  if (!response.ok || payload.error) {
    throw httpError(502, "hubrise_token_exchange_failed", payload.error_description || payload.error || "HubRise rechazo el intercambio OAuth.");
  }

  if (!payload.access_token) {
    throw httpError(502, "hubrise_token_missing", "HubRise no devolvio access_token.");
  }

  return payload;
}

async function fetchAccounts(accessToken) {
  const response = await fetchWithTimeout(HUBRISE_ACCOUNTS_URL, {
    method: "GET",
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${accessToken}`,
    },
  });

  const payload = await readJson(response);

  if (!response.ok) {
    throw httpError(502, "hubrise_accounts_failed", payload.error_description || payload.error || "No se pudieron obtener las cuentas de HubRise.");
  }

  return payload;
}

async function fetchWithTimeout(url, options, timeoutMs = 10000) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal,
    });
  } catch (error) {
    if (error.name === "AbortError") {
      throw httpError(504, "hubrise_timeout", "HubRise no respondio a tiempo.");
    }

    throw httpError(502, "hubrise_network_error", "No se pudo contactar con HubRise.");
  } finally {
    clearTimeout(timeout);
  }
}

async function readJson(response) {
  const text = await response.text();

  if (!text) {
    return {};
  }

  try {
    return JSON.parse(text);
  } catch {
    return { raw: text.slice(0, 1000) };
  }
}

function jsonResponse(statusCode, body, headers = {}) {
  return {
    statusCode,
    headers: {
      ...JSON_HEADERS,
      ...headers,
    },
    body: JSON.stringify(body),
  };
}

function httpError(statusCode, code, message) {
  const error = new Error(message);
  error.statusCode = statusCode;
  error.code = code;
  return error;
}
