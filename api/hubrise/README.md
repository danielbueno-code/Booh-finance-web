# Desactivar productos en HubRise

HubRise no desactiva productos con un campo `active` dentro del producto. La disponibilidad se controla por inventario de la ubicación:

- Se debe usar el endpoint de inventario del catálogo.
- Se debe enviar `PATCH`, no `PUT`, para no sobrescribir el inventario completo.
- Se desactivan SKUs, no productos directamente: `stock: "0"` sobre cada `sku_ref`.

## Endpoint local

`POST /api/hubrise/deactivate-products.php`

Autenticación recomendada:

```http
Authorization: Bearer HUBRISE_ACCESS_TOKEN
Content-Type: application/json
```

### Desactivar por SKU

```json
{
  "catalog_id": "87yu4",
  "location_id": "3r4s3-1",
  "sku_refs": ["COKE", "PEPSI"]
}
```

### Desactivar por producto

Si envías `product_refs` o `product_ids`, el endpoint descarga el catálogo, localiza todos los SKUs del producto y desactiva cada `sku_ref`.

```json
{
  "catalog_id": "87yu4",
  "location_id": "3r4s3-1",
  "product_refs": ["BURGER"]
}
```

### Reactivación automática

HubRise permite indicar cuándo el SKU volverá a estar disponible:

```json
{
  "catalog_id": "87yu4",
  "sku_refs": ["COKE"],
  "expires_at": "2026-05-17T08:00:00+02:00"
}
```

### Probar sin llamar a HubRise

```json
{
  "catalog_id": "87yu4",
  "sku_refs": ["COKE"],
  "dry_run": true
}
```

La respuesta mostrará el endpoint de inventario y el payload que se enviaría a HubRise.
