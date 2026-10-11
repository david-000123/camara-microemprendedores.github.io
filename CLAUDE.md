# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Qué es este proyecto

Sitio web estático de la **Cámara de Trabajadores Microemprendedores de la R.A.**, construido sobre la plantilla HTML comercial *Adina – Business & Finance* (Theme_Dox). No hay framework, bundler ni `package.json`: son páginas HTML sueltas en la raíz que cargan CSS y JS desde `assets/`. La única parte con servidor es el formulario de contacto (PHP + PHPMailer).

Todo el contenido visible va en español (`lang="es"`).

## Comandos

No hay build, linter ni tests.

- **Ver el sitio:** Live Server de VS Code, puerto `5501` (`.vscode/settings.json`).
- **Probar el formulario de contacto:** Live Server no ejecuta PHP. Hace falta un servidor con PHP y la variable de entorno `GMAIL_APP_PASSWORD` definida, por ejemplo desde la raíz del repo:
  ```bash
  GMAIL_APP_PASSWORD="xxxx xxxx xxxx xxxx" php -S localhost:8000
  ```
- **Dependencias PHP:** `composer install` dentro de `assets/` (ahí viven `composer.json`, `composer.lock` y `vendor/`, no en la raíz).

## Estado de la migración de la plantilla

La plantilla trae 17 páginas; solo algunas están adaptadas. Para saber si una página ya fue trabajada, mirar su `<title>` y su `lang`:

- **Adaptadas** (`<title>Cámara de Trabajadores Microemprendedores - R.A.</title>`, `lang="es"`): `index.html`, `about.html`, `contact.html`, `service.html` y las seis páginas de detalle `servicio-*.html` (formacion, networking, beneficios, representacion, eventos, comercio-exterior). Las cinco primeras corresponden a las tarjetas de `service.html`; comercio-exterior no tiene tarjeta ahí. Comparten estructura, lateral "Nuestros Servicios" y navegación anterior/siguiente circular: un cambio en una hay que repetirlo en las seis.
- **En proceso o sin tocar** (título `Adina - ...`, `lang="zxx"`): el resto. `index-2.html` a `index-5.html` son variantes de home de la plantilla; `blog-details.html`, `portfolio.html` y `service-details.html` siguen con contenido de demostración.
- **Blog** (`blog.html` y `blog-2.html`): el contenido está adaptado, pero el header y el footer siguen siendo los de la plantilla. Son las páginas 1 y 2 de la misma lista, enlazadas por la paginación; comparten breadcrumb y lateral, así que un cambio ahí va en las dos. Cada nota es un resumen propio que enlaza a su fuente externa (no hay páginas de detalle) y lleva "Fuente" y fecha de consulta; no se publican montos ni alícuotas.

El menú y los enlaces internos todavía apuntan a páginas de demostración e incluso a una inexistente (`awards-details.html`).

## Arquitectura

### Header, offcanvas y footer están duplicados en cada HTML

No hay includes ni plantillas parciales. Cualquier cambio en el menú, el logo, el panel offcanvas o el footer hay que repetirlo en cada página. Las secciones están delimitadas con comentarios `<!-- nombre-area-start -->` / `<!-- nombre-area-end -->`, y los commits del proyecto se refieren a las secciones por esos nombres.

### CSS: se edita `main.css` directamente

`assets/scss/` contiene los fuentes de la plantilla (`main.scss` → `utils`, `theme`, `components`, `layout`) y `main.css.map` apunta a ellos, pero **no hay compilador de Sass configurado** y los cambios del proyecto se hicieron directo sobre `assets/css/main.css` (por ejemplo los estilos `.is-invalid` del formulario no existen en el SCSS). Recompilar el SCSS pisaría esos cambios. Mientras no se decida lo contrario, editar `main.css`.

- Las variables globales están en `:root` con prefijo `--td-` (`--td-<grupo>-<tono>`, p. ej. `--td-common-white`); en el SCSS se generan desde los mapas de `utils/_colors.scss` y `utils/_typography.scss`.
- Las clases de la plantilla usan prefijo `td-`. Los espaciados (`pt-120`, `mb-30`, `pb-50`…) son utilidades de `assets/css/spacing.css`.
- El resto de `assets/css/` son librerías de terceros (Bootstrap, Swiper, Slick, Magnific Popup, Font Awesome Pro, animate): no modificarlas.

### JS: jQuery + plugins, sin módulos

Cada página carga la misma lista de `<script>` al final del `<body>`, en un orden que importa: `vendor/jquery.js` primero, luego los plugins, y al final `ajax-form.js`, `slider-init.js` y `main.js`.

- `main.js`: inicializaciones generales de la plantilla (índice numerado en la cabecera del archivo). Las imágenes de fondo se asignan con el atributo `data-background`, no con CSS.
- `slider-init.js`: configuración de todos los carruseles Swiper/Slick.
- `ajax-form.js`: lógica propia del formulario de contacto (ver abajo).

### Formulario de contacto

Flujo que abarca tres archivos y que debe mantenerse sincronizado:

1. `contact.html` — `<form id="contact-form" action="assets/mailer.php" novalidate>` con campos `name`, `email` (obligatorios) y `message` (opcional). Cada campo obligatorio lleva `data-label`, que es el nombre que se muestra en el mensaje de error. Incluye el párrafo `.ajax-response` y el modal `#form-success-modal`. El formulario lateral de las seis `servicio-*.html` usa el mismo `id`, los mismos campos y el mismo modal, así que comparte todo este flujo; los estilos de error y del modal (`.form-modal`) están en `main.css`.
2. `assets/js/ajax-form.js` — valida en cliente, envía por AJAX, marca los campos con `.is-invalid` y abre el modal al tener éxito.
3. `assets/mailer.php` — vuelve a validar con **las mismas reglas y la misma regex de email** y responde JSON `{success, message, fields}`. `fields` es un mapa `campo → 'required' | 'format'` que el JS usa para marcar los campos. Si se cambia una regla o un campo, hay que cambiarlo en los tres lugares.

`mailer.php` envía dos correos por SMTP de Gmail: uno a la Cámara (con `Reply-To` del solicitante) y uno de confirmación al solicitante, cuyo HTML es un heredoc muy largo (líneas ~139-279) exportado de un editor de emails. La contraseña se lee de la variable de entorno `GMAIL_APP_PASSWORD`; nunca debe escribirse en el código.

Pendientes conocidos en `mailer.php`:

- Las imágenes se adjuntan con `addEmbeddedImage(..., 'img-banner')` etc., pero el HTML las referencia con rutas relativas (`src="assets/img/mailer/..."`) en vez de `cid:<id>`, así que no se muestran embebidas.
- El enlace de WhatsApp usa el número de relleno `54XXXXXXXXXXX`, y los enlaces a redes sociales y "Ver sitio web" (`index.html`) no son URLs definitivas.

`assets/mail.php` es el script original de la plantilla y no se usa.

## Despliegue

El sitio se publicará en un hosting privado todavía sin definir. Requisitos que impone el formulario: PHP con `assets/vendor/` presente, salida a `smtp.gmail.com:587` y la variable `GMAIL_APP_PASSWORD` definida en el servidor.

No hay `.gitignore`: `assets/vendor/` aparece como no versionado.
