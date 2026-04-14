# WPSL CSV Importer — Roadmap de lanzamiento

Objetivo: publicar en wordpress.org de forma gratuita y competitiva con plugins de pago.

---

## Fase 1 — Bugs críticos ✅ COMPLETADA

- [x] **Fix detección de duplicados por nombre+dirección**
      `wpsl_csv_find_store()` ahora compara `post_title` + `wpsl_address`.
      Los chains como "Price Chopper" con múltiples locaciones se manejan correctamente.

- [x] **Fix geocodificación en modo UPDATE**
      Se captura la dirección ANTES de actualizar. Si cambió y no hay lat/lng explícitos,
      se borran `wpsl_lat` y `wpsl_lng` para que WPSL re-geocodifique al publicar.

---

## Fase 2 — Requisitos wordpress.org ✅ COMPLETADA

- [x] **readme.txt** — Formato completo: descripción, instalación, FAQ, screenshots, changelog.

- [x] **Internacionalización (i18n)** — Todos los strings en `__()` / `esc_html__()`.
      Text domain registrado con `load_plugin_textdomain`.

- [x] **Aviso de dependencia** — Notice en admin si WPSL no está activo, con link de instalación.

- [x] **Hook de desinstalación** — `register_uninstall_hook` limpia `wpsl_csv_last_mapping`.

---

## Fase 3 — Features diferenciadores ✅ COMPLETADA

- [x] **Importación AJAX por chunks**
      Procesa en lotes de 50 filas via AJAX con barra de progreso en la UI.
      Sin timeouts independientemente del tamaño del CSV.

- [x] **Exportar tiendas a CSV**
      Botón que descarga todas las tiendas WPSL como CSV con UTF-8 BOM (compatible con Excel).
      Incluye columnas de categoría.

- [x] **Soporte para categorías de WPSL**
      Columna opcional `Category` → taxonomía `wpsl_store_category`.
      Crea la categoría si no existe. Soporta múltiples categorías separadas por `|`.

- [x] **Recordar mapeo de columnas**
      El último mapeo usado se guarda en `wp_options` y se pre-rellena en el formulario.

---

## Fase 4 — Distribución (pendiente)

- [ ] Crear screenshots para el directorio de wordpress.org (mínimo 1 del formulario de import)
- [ ] Subir a wordpress.org (requiere cuenta + SVN)
- [ ] Crear repositorio público en GitHub
- [ ] Agregar archivo de traducción `.pot` base con WP-CLI:
      `wp i18n make-pot . languages/wpsl-csv-importer.pot`

---

## Decisiones técnicas

| Decisión | Razón |
|---|---|
| Plugin de un solo archivo | Simplicidad, fácil de instalar sin composer |
| AJAX por chunks de 50 filas | Sin timeouts, experiencia en tiempo real |
| Comparación nombre+dirección | Evita falsos duplicados en chains con múltiples locaciones |
| Borrar lat/lng en UPDATE si cambió dirección | Fuerza re-geocodificación por WPSL |
| Transient para estado del batch (1h TTL) | Escalable, sin sesiones PHP, limpieza automática |
| UTF-8 BOM en export | Compatibilidad con Excel sin conversión manual |
| Temp dir → OS temp primero, uploads como fallback | OS temp no es web-accesible; uploads protegido con .htaccess |
| Categorías separadas por pipe `\|` | Convención común en CSV para valores múltiples |
| Limpieza de archivos temporales > 2h | Evita acumulación si un import se interrumpe |
