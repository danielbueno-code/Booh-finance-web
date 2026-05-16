<?php

const HUBRISE_API_BASE_URL = 'https://api.hubrise.com/v1';

function hubrise_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function hubrise_read_json_body(): array
{
    $rawBody = file_get_contents('php://input');

    if ($rawBody === false || trim($rawBody) === '') {
        return [];
    }

    $decoded = json_decode($rawBody, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
        throw new InvalidArgumentException('El cuerpo de la petición debe ser JSON válido.');
    }

    return $decoded;
}

function hubrise_bearer_token(): ?string
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $authorization = $headers['Authorization'] ?? $headers['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
        return trim($matches[1]);
    }

    return null;
}

function hubrise_required_string(array $payload, string $field): string
{
    $value = $payload[$field] ?? null;

    if (!is_string($value) || trim($value) === '') {
        throw new InvalidArgumentException("El campo '{$field}' es obligatorio.");
    }

    return trim($value);
}

function hubrise_optional_string(array $payload, string $field): ?string
{
    if (!array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
        return null;
    }

    if (!is_string($payload[$field])) {
        throw new InvalidArgumentException("El campo '{$field}' debe ser texto.");
    }

    return trim($payload[$field]);
}

function hubrise_string_list(array $payload, string $field): array
{
    if (!array_key_exists($field, $payload) || $payload[$field] === null) {
        return [];
    }

    if (!is_array($payload[$field])) {
        throw new InvalidArgumentException("El campo '{$field}' debe ser un array.");
    }

    $values = [];
    foreach ($payload[$field] as $value) {
        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException("Todos los valores de '{$field}' deben ser textos no vacíos.");
        }

        $values[] = trim($value);
    }

    return hubrise_unique_values($values);
}

function hubrise_unique_values(array $values): array
{
    $unique = [];

    foreach ($values as $value) {
        if (!in_array($value, $unique, true)) {
            $unique[] = $value;
        }
    }

    return $unique;
}

function hubrise_inventory_path(string $catalogId, ?string $locationId): string
{
    $catalogPath = rawurlencode($catalogId);

    if ($locationId === null) {
        return "/catalogs/{$catalogPath}/location/inventory";
    }

    return "/catalogs/{$catalogPath}/locations/" . rawurlencode($locationId) . '/inventory';
}

function hubrise_build_deactivation_patch(array $skuRefs, ?string $expiresAt): array
{
    $entries = [];

    foreach (hubrise_unique_values($skuRefs) as $skuRef) {
        $entry = [
            'sku_ref' => $skuRef,
            'stock' => '0',
        ];

        if ($expiresAt !== null) {
            $entry['expires_at'] = $expiresAt;
        }

        $entries[] = $entry;
    }

    return $entries;
}

function hubrise_resolve_product_skus(array $catalog, array $productRefs, array $productIds): array
{
    $data = $catalog['data'] ?? $catalog;
    $products = $data['products'] ?? [];

    if (!is_array($products)) {
        throw new InvalidArgumentException('La respuesta del catálogo no contiene una lista de productos válida.');
    }

    $skuRefs = [];
    $matchedRefs = [];
    $matchedIds = [];
    $productsWithoutSkuRefs = [];

    foreach ($products as $product) {
        if (!is_array($product)) {
            continue;
        }

        $productRef = isset($product['ref']) ? (string) $product['ref'] : null;
        $productId = isset($product['id']) ? (string) $product['id'] : null;
        $matchesRef = $productRef !== null && in_array($productRef, $productRefs, true);
        $matchesId = $productId !== null && in_array($productId, $productIds, true);

        if (!$matchesRef && !$matchesId) {
            continue;
        }

        if ($matchesRef) {
            $matchedRefs[] = $productRef;
        }
        if ($matchesId) {
            $matchedIds[] = $productId;
        }

        $productSkuRefs = [];
        foreach (($product['skus'] ?? []) as $sku) {
            if (is_array($sku) && isset($sku['ref']) && trim((string) $sku['ref']) !== '') {
                $productSkuRefs[] = trim((string) $sku['ref']);
            }
        }

        if ($productSkuRefs === []) {
            $productsWithoutSkuRefs[] = $productRef ?? $productId ?? '(sin ref/id)';
        }

        $skuRefs = array_merge($skuRefs, $productSkuRefs);
    }

    return [
        'sku_refs' => hubrise_unique_values($skuRefs),
        'missing_product_refs' => array_values(array_diff($productRefs, hubrise_unique_values($matchedRefs))),
        'missing_product_ids' => array_values(array_diff($productIds, hubrise_unique_values($matchedIds))),
        'products_without_sku_refs' => hubrise_unique_values($productsWithoutSkuRefs),
    ];
}

function hubrise_api_request(string $method, string $path, string $accessToken, ?array $payload = null): array
{
    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Accept: application/json',
    ];

    $options = [
        'method' => strtoupper($method),
        'header' => implode("\r\n", $headers) . "\r\n",
        'ignore_errors' => true,
        'timeout' => 30,
    ];

    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new RuntimeException('No se pudo codificar la petición para HubRise.');
        }

        $options['header'] .= "Content-Type: application/json\r\n";
        $options['content'] = $body;
    }

    $context = stream_context_create(['http' => $options]);
    $rawResponse = @file_get_contents(HUBRISE_API_BASE_URL . $path, false, $context);
    $statusCode = 0;

    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $matches) === 1) {
        $statusCode = (int) $matches[1];
    }

    if ($rawResponse === false) {
        return [
            'status_code' => $statusCode,
            'data' => null,
            'raw_body' => null,
            'error' => 'No se pudo conectar con HubRise.',
        ];
    }

    $decoded = json_decode($rawResponse, true);

    return [
        'status_code' => $statusCode,
        'data' => json_last_error() === JSON_ERROR_NONE ? $decoded : null,
        'raw_body' => $rawResponse,
        'error' => null,
    ];
}
