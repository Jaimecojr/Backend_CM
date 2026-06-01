# Diseño: Notificación WhatsApp al Crear/Editar Cita

**Fecha:** 2026-05-15  
**Estado:** Aprobado

---

## Resumen

Al crear o actualizar una cita médica, el sistema envía automáticamente un mensaje de WhatsApp al número del paciente confirmando los detalles de la cita. Usa la misma infraestructura (WhatsApp Cloud API + tabla `whatsapp_messages`) que el módulo de carnets.

---

## Cambios requeridos

### 1. Base de datos

**Migration A — `whatsapp_messages`:** Agregar columna `type` (string, nullable, default null) para distinguir el origen del mensaje.  
- Valores posibles: `'carnet'`, `'cita'`  
- `CarnetController` actualizado para guardar `type = 'carnet'`  
- `AppointmentController` guardará `type = 'cita'`

**Migration B — `settings`:** Agregar columna `wa_appointment_template_name` (string, nullable) para el nombre de la plantilla de citas en Meta.

### 2. Modelos

- `WhatsappMessage`: agregar `type` al `$fillable`
- `Setting`: agregar `wa_appointment_template_name` al `$fillable`

### 3. SettingController

Agregar `wa_appointment_template_name` a las reglas de validación del método `update()` para que pueda editarse desde el panel de configuración.

---

## Lógica principal — `AppointmentController`

### Método privado `enviarNotificacionWA(Appointment $appointment): array`

Encapsula toda la lógica de envío. Retorna un array con `['enviado' => bool, 'detalle' => string|null]`.

**Flujo:**

1. Verificar que `$appointment->phone` tenga exactamente 10 dígitos numéricos. Si no, retornar `['enviado' => false, 'detalle' => 'El teléfono no es válido']`.
2. Leer `Setting::first()`. Verificar que `wa_api_version`, `wa_phone_number_id`, `wa_bearer_token` y `wa_appointment_template_name` estén presentes. Si no, retornar `['enviado' => false, 'detalle' => 'Configuración de WhatsApp incompleta']`.
3. Cargar relaciones necesarias: `$appointment->load('doctor.specialty')`.
4. Construir los parámetros de la plantilla:
   - `{{1}}` → `$appointment->name`
   - `{{2}}` → fecha formateada (ej: `15/05/2026`)
   - `{{3}}` → `$appointment->hour`
   - `{{4}}` → `$appointment->address`
   - `{{5}}` → nombre de la especialidad del doctor (`$appointment->doctor->specialty->name`)
   - `{{6}}` → nombre completo del doctor (`name . ' ' . lastname`)
   - `{{7}}` → valor formateado (ej: `$ 50,000`)
5. Construir payload con `type: template`, `name: $settings->wa_appointment_template_name`, `language: es`, componente `body` con los 7 parámetros de tipo `text`.
6. Llamar a la API de Meta con `Http::withToken()` (sin SSL verify en entorno `local`).
7. Registrar en `whatsapp_messages` con `type = 'cita'` siempre (éxito o fallo).
8. Si `messages[0].id` presente en respuesta → retornar `['enviado' => true]`. Si no → `['enviado' => false, 'detalle' => 'Error en la API de WhatsApp']`.

### Integración en `store()` y `update()`

Después de crear/actualizar el appointment, llamar a `$this->enviarNotificacionWA($appointment)` e incluir el resultado en la respuesta JSON bajo la clave `whatsapp`.

```json
// Éxito total
{
  "message": "Cita creada correctamente.",
  "data": { ... },
  "whatsapp": { "enviado": true }
}

// Cita creada, WA falló
{
  "message": "Cita creada correctamente.",
  "data": { ... },
  "whatsapp": { "enviado": false, "detalle": "El teléfono no es válido" }
}
```

---

## Plantilla WhatsApp (Meta)

Nombre a registrar en `settings.wa_appointment_template_name`. Estructura acordada:

```
Hola {{1}} 👋

Su cita médica está confirmada:

📅 *Fecha:* {{2}}
🕐 *Hora:* {{3}}
🏥 *Lugar:* {{4}}
👨‍⚕️ *Especialidad:* {{5}}
👤 *Médico:* {{6}}
💰 *Valor:* {{7}}

📋 *Recomendaciones:*
- Llegar 20 min antes
- Presentar carnet de Contacto Médico (físico o digital)
- Traer orden médica si aplica
- Menores de edad y mayores de 60 años deben asistir con acompañante

⚠️ Para cancelar o reprogramar, avisar con 24 horas de anticipación.

Quedamos atentos a cualquier inquietud 🙂
```

Todos los parámetros `{{1}}` a `{{7}}` son de tipo `text` en el componente `body`.

---

## Archivos a modificar / crear

| Archivo | Acción |
|---|---|
| `database/migrations/..._add_type_to_whatsapp_messages.php` | Nuevo — columna `type` |
| `database/migrations/..._add_appointment_template_to_settings.php` | Nuevo — columna `wa_appointment_template_name` |
| `app/Models/WhatsappMessage.php` | Agregar `type` a `$fillable` |
| `app/Models/Setting.php` | Agregar `wa_appointment_template_name` a `$fillable` |
| `app/Http/Controllers/CarnetController.php` | Agregar `type => 'carnet'` al `WhatsappMessage::create()` |
| `app/Http/Controllers/AppointmentController.php` | Agregar método privado + llamada en store/update |
| `app/Http/Controllers/SettingController.php` | Agregar campo al update |

---

## Consideraciones

- **No bloqueante:** La cita se crea/actualiza siempre. El fallo de WA solo afecta el campo `whatsapp` en la respuesta.
- **Validación de phone:** Safety net en backend (10 dígitos numéricos). El frontend ya valida esto.
- **Formato de valor:** `number_format($appointment->value, 0, ',', '.')` → `$ 50.000` (formato colombiano).
- **SSL local:** Igual que el carnet, usar `withoutVerifying()` en entorno `local`.
- **Specialty null:** Si el doctor no tiene especialidad asignada, usar string vacío o `'No especificada'` para no romper el envío.
