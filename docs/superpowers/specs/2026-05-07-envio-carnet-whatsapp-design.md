# Diseño: Envío de Carnets por WhatsApp

**Fecha:** 2026-05-07  
**Rama:** develop  
**Estado:** Aprobado

---

## Contexto

Los afiliados tienen un carnet/credencial en PDF que debe enviarse por WhatsApp al momento de ser procesado por un administrador. El campo `affiliates.carnet` (`si`/`no`) indica si ya fue enviado. Esta funcionalidad existía en el sistema anterior (con Zend_Pdf + tabla `users`) y se migra al nuevo sistema (FPDI+TCPDF + tabla `affiliates`).

---

## Arquitectura

### Archivos nuevos
- `app/Http/Controllers/CarnetController.php` — controlador único para PDF + envío WA
- `resources/pdf/carnet.pdf` — plantilla PDF base (carnet vertical ~216×374 mm)

### Archivos modificados
- `routes/api.php` — nueva ruta protegida con `auth:sanctum`
- `composer.json` — dependencias `setasign/fpdi` y `tecnickcom/tcpdf`

### Modelos existentes utilizados (sin cambios)
| Modelo | Uso |
|---|---|
| `Affiliate` | Datos del afiliado: `name`, `lastname`, `id_card`, `movil`, `validity_end` |
| `Beneficiary` | Lista de beneficiarios del afiliado (`affiliate_id`) |
| `User` | Franquicias: `state=1` y `type=2` — campos `name`, `movil` |
| `Setting` | Config WA: `wa_api_version`, `wa_phone_number_id`, `wa_bearer_token`, `wa_template_name` |
| `WhatsappMessage` | Registro de cada envío: `response`, `recipient_id`, `deleted` |

---

## Ruta

```
POST /api/affiliates/{id}/carnet
```

- Protegida con middleware `auth:sanctum`
- No requiere body — el `id` en la URL identifica al afiliado
- Controlador: `CarnetController@send`

Ubicación en `routes/api.php`: dentro del grupo `auth:sanctum`, antes del `apiResource('affiliates')`.

---

## Flujo completo

```
1. Recibir POST /api/affiliates/{id}/carnet
2. Obtener afiliado o retornar 404
3. Validar movil (exactamente 10 dígitos numéricos) → 422 si falla
4. Obtener settings de WA → 500 si no están configurados
5. Generar PDF sobre plantilla base → guardar en storage/app/public/carnets/
6. Enviar PDF por WhatsApp Cloud API
7. Guardar respuesta en whatsapp_messages
8. Si messages[0].id existe → carnet='si', retornar 200
9. Si falla → retornar 422 con error de Meta (NO marcar carnet='si')
```

---

## Generación del PDF (FPDI + TCPDF)

**Librería:** `setasign/fpdi` + `tecnickcom/tcpdf`  
FPDI importa la página del PDF base como fondo; TCPDF escribe el texto encima.

**Nombre del archivo:** `carnet_{id}_{timestamp}.pdf`  
**Ruta física:** `storage/app/public/carnets/carnet_{id}_{timestamp}.pdf`  
**URL pública:** `{APP_URL}/storage/carnets/carnet_{id}_{timestamp}.pdf`

> El timestamp garantiza una URL única en cada envío, evitando que Meta sirva una versión cacheada del documento anterior. No se resetea `carnet='no'` al editar el afiliado — el re-envío es decisión explícita del operador.

**Fuente:** Helvetica Bold  
**Colores:**
- Rojo: `#e73c3c`
- Azul: `#26c6da`
- Negro: `#000000`

### Contenido y coordenadas (origen arriba-izquierda, unidades mm)

| Elemento | x (mm) | y (mm) | Tamaño | Color |
|---|---|---|---|---|
| "CREDENCIAL AFILIADO" (espaciado) | 22 | 55 | 9 pt | Rojo |
| Nombre completo (mayúsculas) | 55 | 72 | 8 pt | Negro |
| "CC." + número de cédula | 57 | 80 | 8 pt | Negro |
| "BENEFICIARIOS" label | 22 | 95 | 7.5 pt | Rojo |
| Cada beneficiario (mayúsculas) | 22 | 107 + n×8 | 7.5 pt | Negro |
| "VÁLIDO HASTA" | 152 | 255 | 7.5 pt | Azul |
| Mes de vigencia (mayúsculas, x dinámico) | dinámico | 263 | 7.5 pt | Rojo |
| Año (dd/yy) | 195 | 263 | 7.5 pt | Rojo |
| Franquicias (3 columnas, nombre + movil) | 2 | 285 + fila×8 | 7 pt | Negro |

> Las coordenadas son aproximadas basadas en la conversión del sistema anterior y deben ajustarse finamente sobre el PDF real durante la implementación.

**X dinámico del mes** (replicado del sistema anterior, ajustado a mm):

| Mes | x (mm) |
|---|---|
| enero, marzo | 165 |
| febrero, octubre | 155 |
| abril, mayo | 170 |
| junio, julio | 168 |
| agosto | 158 |
| septiembre | 143 |
| noviembre | 147 |
| diciembre | 149 |
| (resto) | 145 |

---

## Integración WhatsApp Cloud API

**Endpoint Meta:**  
`POST https://graph.facebook.com/{wa_api_version}/{wa_phone_number_id}/messages`

**Headers:**
```
Authorization: Bearer {wa_bearer_token}
Content-Type: application/json
```

**Cliente HTTP:** `Http::withToken()->post()` de Laravel (no cURL).

**Número destino:** `'57' . $affiliate->movil` (prefijo Colombia)

**Payload:**
```json
{
  "messaging_product": "whatsapp",
  "recipient_type": "individual",
  "to": "57{movil}",
  "type": "template",
  "template": {
    "name": "{wa_template_name}",
    "language": { "code": "es" },
    "components": [
      {
        "type": "header",
        "parameters": [{
          "type": "document",
          "document": {
            "link": "{APP_URL}/storage/carnets/carnet_{id}_{ts}.pdf",
            "filename": "carnet.pdf"
          }
        }]
      },
      {
        "type": "body",
        "parameters": [{
          "type": "text",
          "text": "{NOMBRE APELLIDO en mayúsculas}"
        }]
      }
    ]
  }
}
```

---

## Registro en `whatsapp_messages`

Siempre se registra el intento (éxito o fallo):

| Campo | Valor |
|---|---|
| `response` | JSON completo de respuesta de Meta |
| `recipient_id` | `57{movil}` |
| `deleted` | `0` |

---

## Manejo de errores

| Caso | Código HTTP | Respuesta |
|---|---|---|
| Afiliado no encontrado | 404 | `{ message: 'Afiliado no encontrado' }` |
| `movil` vacío o no tiene 10 dígitos | 422 | `{ message: 'El celular del afiliado no es válido' }` |
| Settings de WA no configurados | 500 | `{ message: 'Configuración de WhatsApp incompleta' }` |
| Error generando PDF | 500 | `{ message: 'Error al generar el carnet' }` |
| Meta retorna error | 422 | `{ message: 'Envío fallido', error: {detalle de Meta} }` |
| Envío exitoso | 200 | `{ message: 'Carnet enviado exitosamente', data: {respuesta Meta} }` |

---

## Validaciones de negocio

- El botón de envío en el frontend solo se muestra si `carnet == 'no'` y `movil` tiene 10 dígitos (lógica del frontend, no del backend).
- El backend valida `movil` independientemente.
- `carnet = 'si'` **solo** se actualiza si Meta confirma el envío (`messages[0].id` presente).
- `carnet = 'no'` **no** se resetea automáticamente al editar el afiliado.

---

## Dependencias a instalar

```bash
composer require setasign/fpdi tecnickcom/tcpdf
```

---

## Frontend (panel administrativo)

- El botón de envío solo se muestra si `carnet == 'no'` y `movil` tiene 10 dígitos.
- Al hacer clic → confirmación con SweetAlert2 (`Swal.fire` con `showCancelButton`).
- Tras la respuesta del backend → alerta SweetAlert2 de éxito (`icon: 'success'`) o error (`icon: 'error'`), usando el sistema global de alertas ya configurado en el proyecto frontend.
- Si es exitoso → recargar la lista de afiliados para reflejar `carnet = 'si'`.

---

## Consideraciones de despliegue (Railway)

- El symlink `public/storage → storage/app/public` debe estar activo (`php artisan storage:link`).
- La carpeta `storage/app/public/carnets/` se crea automáticamente si no existe (`Storage::makeDirectory`).
- El filesystem de Railway es efímero: los PDFs generados se pierden al redeploy. Esto es aceptable porque el carnet se re-genera en cada envío y Meta almacena el documento enviado en su historial.
