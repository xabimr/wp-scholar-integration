# Changelog

## 1.5.3
- Evita duplicar el año al final de la revista ("…, 2016, 2016").

## 1.5.2
- Licencia cambiada a MIT; añadidos LICENSE y README.
- Traducciones incluidas: gallego (gl_ES) y castellano (es_ES); el idioma se toma del idioma del sitio en WordPress.
- Más espacio entre el selector de orden y la primera publicación.

## 1.5.1
- Selector de orden en la web (año más reciente / más antiguo / más citadas), con JS ligero.
- Atributos `sort` (year_desc, year_asc, cited_desc) y `show_sort`. El orden se aplica antes de `limit`.

## 1.5.0
- Seguridad: `title_tag` validado con lista blanca (corrige XSS almacenado).
- Detecta bloqueos de Google (HTTP != 200, CAPTCHA, consentimiento) y ya no los cachea como "sin publicaciones".
- Caché negativa de 15 min y copia de respaldo de la última respuesta buena (se sirve si Google falla).
- Visitantes no ven errores técnicos; solo usuarios con `manage_options`.
- Paginación: hasta 500 publicaciones (`pagesize=100`, `cstart`).
- Codificación UTF-8 forzada (acentos y eñes correctos).
- Selectores XPath por `contains(class)`, menos frágiles; `hl=en` fijo.
- El cron refresca todos los IDs usados, no solo el de ajustes.
- CSS se encola al renderizar el shortcode (compatible con Elementor y widgets).
- Nuevo atributo `show_authors` (autores por publicación) separado de `show_author` (encabezado).
- `id` del shortcode validado/normalizado (acepta URL del perfil).
- Añadido `uninstall.php`.
