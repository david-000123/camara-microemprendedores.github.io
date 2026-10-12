# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es este proyecto

Sitio web estático de la **Cámara de Trabajadores Microemprendedores de la R.A.**, construido sobre la plantilla HTML comercial *Adina – Business & Finance* (Theme_Dox). No hay framework, bundler ni `package.json`: son páginas HTML sueltas en la raíz que cargan CSS y JS desde `assets/`. La única parte con servidor es el formulario de contacto (PHP + PHPMailer).

Todo el contenido visible va en español (`lang="es"`).

## Comandos

No hay build, linter ni tests.

- **Ver el sitio:** Live Server de VS Code, puerto `5501` (`.vscode/settings.json`).
- **Probar el formulario de contacto:** Live Server no ejecuta PHP. Hace falta un servidor con PHP y las variables de entorno `GMAIL_USER` (cuenta de Gmail que envía y recibe) y `GMAIL_APP_PASSWORD` (contraseña de aplicación) definidas, por ejemplo desde la raíz del repo:
  ```bash
  GMAIL_USER="cuenta@gmail.com" GMAIL_APP_PASSWORD="xxxx xxxx xxxx xxxx" php -S localhost:8000
  ```
- **Dependencias PHP:** `composer install` dentro de `assets/` (ahí viven `composer.json`, `composer.lock` y `vendor/`, no en la raíz).

## Páginas del sitio

La raíz tiene 16 páginas. Las páginas de demostración de la plantilla (`index-2` a `index-5`, `team`, `team-details`, `portfolio`, `portfolio-details`, `service-details`, `blog-details`) **ya se eliminaron**, junto con las imágenes que solo ellas usaban.

| Grupo | Páginas |
|---|---|
| Principales | `index.html`, `about.html`, `contact.html`, `service.html` |
| Detalle de servicio (6) | `servicio-formacion`, `servicio-networking`, `servicio-beneficios`, `servicio-representacion`, `servicio-eventos`, `servicio-comercio-exterior` (`.html`) |
| Blog | `blog.html` y `blog-2.html` |
| Información legal | `faq.html`, `faq1.html`, `faq2.html` |
| Error 404 | `error.html` (sin footer ni formulario; es la página para el servidor) |

- **Servicios:** las cinco primeras de detalle corresponden a las tarjetas de `service.html`; comercio-exterior no tiene tarjeta ahí. Comparten estructura, lateral "Nuestros Servicios" y navegación anterior/siguiente circular: un cambio en una hay que repetirlo en las seis.
- **Blog:** `blog.html` y `blog-2.html` son las páginas 1 y 2 de la misma lista, enlazadas por la paginación; comparten breadcrumb y lateral, así que un cambio ahí va en las dos. Cada nota es un resumen propio que enlaza a su fuente externa (no hay páginas de detalle) y lleva "Fuente" y fecha de consulta; no se publican montos ni alícuotas.
- **Información legal:** `faq.html` = Política de Privacidad (`#privacidad`), `faq1.html` = Términos y Condiciones (`#terminos`), `faq2.html` = Política de Cookies (`#cookies`). Se enlazan desde el footer de las 15 páginas adaptadas y desde el ítem "Faq" del menú (apunta a `faq.html`). Comparten estructura (artículo + lateral de documentos + navegación anterior/siguiente) y los estilos `.td-legal-*` de `main.css`.

### Pendientes conocidos

- **Menú:** es idéntico en las 16 páginas (Inicio, La Cámara, Blog, Servicios, Eventos, Contacto) y no tiene enlaces a páginas inexistentes. "La Cámara" incluye las tres políticas; "Autoridades" apunta a `about.html#autoridades`. Un cambio de menú hay que repetirlo en las 16.
- **`about.html`:** las tarjetas del equipo (sección `#autoridades`) usan nombres de relleno de la plantilla (Mateo Silva, Sofía Mendoza, Alejandro Gómez, Chuck Norris) y ya no enlazan a ninguna página de detalle.
- **`assets/img/about/a.png`** está referenciada en `service.html` y no existe.
- **Cuestiones legales abiertas (auditoría Ley 25.326):** registro de la base ante la AAIP por confirmar con un abogado; licencia de Font Awesome Pro (`assets/css/font-awesome-pro.css`, 6.0.0, comercial) por acreditar; origen y licencia de las fotos de la plantilla y de las agregadas por el equipo sin certificado; la Política de Cookies menciona Google Analytics, que el sitio no carga (si se agrega, hace falta banner con aceptar/rechazar y no cargarlo antes del consentimiento). Google Fonts se importa desde `fonts.googleapis.com` y el mapa de `contact.html` carga cookies de Google al abrir la página.
- **Horario de atención inconsistente:** `index.html` dice "lunes a viernes, de 10:00 a 18:00" y `contact.html` dice "09:00 a 18:00". Por eso no figura en el JSON-LD.
- **Redes sociales:** los enlaces del footer son `#`; cuando haya URLs definitivas, agregarlas como `sameAs` en `Organization`.
- **Política de Privacidad:** el CUIT figura como `xx-xxxxxx-x` (falta el real). La Política de Cookies menciona Google Analytics, que el sitio todavía no carga.

## Arquitectura

### Header, offcanvas y footer están duplicados en cada HTML

No hay includes ni plantillas parciales. Cualquier cambio en el menú, el logo, el panel offcanvas o el footer hay que repetirlo en cada página. Las secciones están delimitadas con comentarios `<!-- nombre-area-start -->` / `<!-- nombre-area-end -->`, y los commits del proyecto se refieren a las secciones por esos nombres.

### CSS: se edita `main.css` directamente

`assets/scss/` contiene los fuentes de la plantilla (`main.scss` → `utils`, `theme`, `components`, `layout`) y `main.css.map` apunta a ellos, pero **no hay compilador de Sass configurado** y los cambios del proyecto se hicieron directo sobre `assets/css/main.css` (por ejemplo los estilos `.is-invalid` del formulario y el bloque `.td-legal-*` no existen en el SCSS). Recompilar el SCSS pisaría esos cambios. Mientras no se decida lo contrario, editar `main.css`.

- Las variables globales están en `:root` con prefijo `--td-` (`--td-<grupo>-<tono>`, p. ej. `--td-common-white`); en el SCSS se generan desde los mapas de `utils/_colors.scss` y `utils/_typography.scss`.
- Las clases de la plantilla usan prefijo `td-`. Los espaciados (`pt-120`, `mb-30`, `pb-50`…) son utilidades de `assets/css/spacing.css`.
- El resto de `assets/css/` son librerías de terceros (Bootstrap, Swiper, Slick, Magnific Popup, Font Awesome Pro, animate): no modificarlas.

### JS: jQuery + plugins, sin módulos

Cada página carga la misma lista de `<script>` al final del `<body>`, en un orden que importa: `vendor/jquery.js` primero, luego los plugins, y al final `ajax-form.js`, `slider-init.js` y `main.js`.

- `main.js`: inicializaciones generales de la plantilla (índice numerado en la cabecera del archivo). Las imágenes de fondo se asignan con el atributo `data-background`, no con CSS.
- `slider-init.js`: configuración de todos los carruseles Swiper/Slick.
- `ajax-form.js`: lógica propia del formulario de contacto (ver abajo).

### Imágenes

- **Tamaño:** las imágenes se mantienen a un máximo de 1920 px de ancho (JPG calidad ~82); las originales de varios MB no van en el repo. Optimizar manteniendo nombre y extensión para no tocar el HTML.
- **Banners de servicio:** cada `servicio-*.html` tiene su propio banner en `assets/img/breadcrumb/<servicio>.jpg` (blanco y negro, oscurecido para que el título blanco se lea), asignado con `data-background` en el `td-breadcrumb-area`. `assets/img/breadcrumb/bg.jpg` es el banner genérico de las demás páginas.
- **Foto principal de cada servicio:** `assets/img/service/<servicio>.jpg`, referenciada por la `<img>` del bloque `td-service-details-thumb`; su `alt` debe describir la foto.
- **Logo:** `assets/img/logo/01.png` (header, offcanvas y footer).

### SEO y GEO

Cada página lleva en el `<head>`: `<title>` y `description` únicos, `canonical`, Open Graph, Twitter Cards y un bloque JSON-LD (`@graph` con `Organization`, `WebSite`, la página y `BreadcrumbList`; las `servicio-*` suman `Service` y `FAQPage` con las preguntas del acordeón; los blogs, un `ItemList` de sus notas). `error.html` es `noindex` y no lleva datos estructurados. En la raíz hay `robots.txt`, `sitemap.xml` y `llms.txt`.

- **Dominio pendiente:** todas las URLs absolutas usan el marcador `https://www.DOMINIO-PENDIENTE.com.ar`. **Antes de publicar hay que reemplazarlo por el dominio real en todos los archivos** (los 16 HTML, `robots.txt`, `sitemap.xml` y `llms.txt`), por ejemplo con buscar y reemplazar en todo el proyecto.
- **Títulos:** formato `<tema> | Cámara de Trabajadores Microemprendedores R.A.`, de 55 a 71 caracteres. Mantener un solo `<h1>` por página.
- **Si se agrega o quita una página:** sumarla o quitarla de `sitemap.xml` y de `llms.txt`, y darle su propio título, descripción y canonical.
- **Si cambian los datos de contacto** (email, teléfono, dirección): actualizarlos también en el JSON-LD de `Organization` (`index`, `about`, `contact`) y en `llms.txt`.
- **Imagen de redes:** `og:image` usa el banner de cada página (1920 px); lo ideal es 1200x630.

### Formulario de contacto

Flujo que abarca tres archivos y que debe mantenerse sincronizado:

1. `contact.html` — `<form id="contact-form" action="assets/mailer.php" novalidate>` con campos `name`, `email` y la casilla `privacy` (obligatorios) y `message` (opcional). La casilla `privacy` (`<input type="checkbox" name="privacy" value="1">`, sin marcar por defecto) enlaza a `faq.html#privacidad` y es el consentimiento exigido por la Ley 25.326; el correo a la Cámara deja constancia de la fecha en que se aceptó. Cada campo obligatorio lleva `data-label`, que es el nombre que se muestra en el mensaje de error. Incluye el párrafo `.ajax-response` y el modal `#form-success-modal`. El formulario lateral de las seis `servicio-*.html` usa el mismo `id`, los mismos campos y el mismo modal, así que comparte todo este flujo; los estilos de error y del modal (`.form-modal`) están en `main.css`. Las páginas legales (`faq*.html`) no llevan formulario ni modal.
2. `assets/js/ajax-form.js` — valida en cliente, envía por AJAX, marca los campos con `.is-invalid` y abre el modal al tener éxito.
3. `assets/mailer.php` — vuelve a validar con **las mismas reglas y la misma regex de email** y responde JSON `{success, message, fields}`. `fields` es un mapa `campo → 'required' | 'format'` que el JS usa para marcar los campos. Si se cambia una regla o un campo, hay que cambiarlo en los tres lugares.

`mailer.php` envía dos correos por SMTP de Gmail: uno a la Cámara (con `Reply-To` del solicitante) y uno de confirmación al solicitante, cuyo HTML es un heredoc muy largo (líneas ~139-279) exportado de un editor de emails. La cuenta y la contraseña se leen de las variables de entorno `GMAIL_USER` y `GMAIL_APP_PASSWORD`; nunca deben escribirse en el código. Si falta alguna, el endpoint responde 500.

Pendientes conocidos en `mailer.php`:

- Las imágenes se adjuntan con `addEmbeddedImage(..., 'img-banner')` etc., pero el HTML las referencia con rutas relativas (`src="assets/img/mailer/..."`) en vez de `cid:<id>`, así que no se muestran embebidas.
- El enlace de WhatsApp usa el número de relleno `54XXXXXXXXXXX`, y los enlaces a redes sociales y "Ver sitio web" (`index.html`) no son URLs definitivas.

## Despliegue

El sitio se publicará en un hosting privado todavía sin definir. Requisitos que impone el formulario: PHP con `assets/vendor/` presente, salida a `smtp.gmail.com:587` y las variables `GMAIL_USER` y `GMAIL_APP_PASSWORD` definidas en el servidor.

No hay `.gitignore`: `assets/vendor/` aparece como no versionado.
