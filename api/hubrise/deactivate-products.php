<?php

require_once __DIR__ . '/hubrise_inventory.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    hubrise_json_response(405, [
        'error' => 'Método no permitido. Usa POST.',
    ]);
    exit;
}

try {
    $payload = hubrise_read_json_body();

    $catalogId = hubrise_required_string($payload, 'catalog_id');
    $locationId = hubrise_optional_string($payload, 'location_id');
    $expiresAt = hubrise_optional_string($payload, 'expires_at');
    $dryRun = (bool) ($payload['dry_run'] ?? false);

    $skuRefs = hubrise_string_list($payload, 'sku_refs');
    $productRefs = hubrise_string_list($payload, 'product_refs');
    $productIds = hubrise_string_list($payload, 'product_ids');

    if ($skuRefs === [] && $productRefs === [] && $productIds === []) {
        throw new InvalidArgumentException('Indica al menos sku_refs, product_refs o product_ids.');
    }

    $accessToken = hubrise_bearer_token()
        ?? hubrise_optional_string($payload, 'access_token')
        ?? getenv('HUBRISE_ACCESS_TOKEN')
        ?: null;

    $needsCatalogLookup = $productRefs !== [] || $productIds !== [];
    if (($needsCatalogLookup || !$dryRun) && $accessToken === null) {
        throw new InvalidArgumentException('Falta el token de HubRise. Envíalo en Authorization: Bearer <token>.');
    }

    if ($needsCatalogLookup) {
        $catalogResponse = hubrise_api_request('GET', '/catalogs/' . rawurlencode($catalogId), $accessToken);

        if ($catalogResponse['status_code'] < 200 || $catalogResponse['status_code'] >= 300 || !is_array($catalogResponse['data'])) {
            hubrise_json_response($catalogResponse['status_code'] === 401 ? 401 : 502, [
                'error' => 'No se pudo obtener el catálogo de HubRise para resolver los productos.',
                'hubrise_status' => $catalogResponse['status_code'],
                'hubrise_response' => $catalogResponse['data'] ?? $catalogResponse['raw_body'],
            ]);
            exit;
        }

        $resolved = hubrise_resolve_product_skus($catalogResponse['data'], $productRefs, $productIds);

        if ($resolved['missing_product_refs'] !== [] || $resolved['missing_product_ids'] !== [] || $resolved['products_without_sku_refs'] !== []) {
            hubrise_json_response(422, [
                'error' => 'No se pudieron resolver todos los productos a SKU refs.',
                'missing_product_refs' => $resolved['missing_product_refs'],
                'missing_product_ids' => $resolved['missing_product_ids'],
                'products_without_sku_refs' => $resolved['products_without_sku_refs'],
            ]);
            exit;
        }

        $skuRefs = hubrise_unique_values(array_merge($skuRefs, $resolved['sku_refs']));
    }

    $patch = hubrise_build_deactivation_patch($skuRefs, $expiresAt);

    if ($dryRun) {
        hubrise_json_response(200, [
            'status' => 'dry_run',
            'inventory_endpoint' => hubrise_inventory_path($catalogId, $locationId),
            'patch' => $patch,
        ]);
        exit;
    }

    $inventoryResponse = hubrise_api_request(
        'PATCH',
        hubrise_inventory_path($catalogId, $locationId),
        $accessToken,
        $patch
    );

    if ($inventoryResponse['status_code'] < 200 || $inventoryResponse['status_code'] >= 300) {
        hubrise_json_response($inventoryResponse['status_code'] === 401 ? 401 : 502, [
            'error' => 'HubRise no aceptó la desactivación de inventario.',
            'hubrise_status' => $inventoryResponse['status_code'],
            'hubrise_response' => $inventoryResponse['data'] ?? $inventoryResponse['raw_body'],
        ]);
        exit;
    }

    hubrise_json_response(200, [
        'status' => 'success',
        'deactivated_sku_refs' => $skuRefs,
        'hubrise_response' => $inventoryResponse['data'],
    ]);
} catch (InvalidArgumentException $exception) {
    hubrise_json_response(400, [
        'error' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    hubrise_json_response(500, [
        'error' => 'Error interno al desactivar productos.',
    ]);
}
