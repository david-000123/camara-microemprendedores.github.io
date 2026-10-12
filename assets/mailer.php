<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Argentina/Buenos_Aires');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Método no permitido.']));
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

// ─── 1. RECIBIR Y SANITIZAR DATOS ────────────────────────────────────────────
// Los campos que no son texto (arrays) se tratan como vacíos.
$nombreRaw  = is_string($_POST['name']  ?? null) ? trim($_POST['name'])  : '';
$email      = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
$mensajeRaw = is_string($_POST['message'] ?? null) ? trim($_POST['message']) : '';
$privacidad = is_string($_POST['privacy'] ?? null) ? trim($_POST['privacy']) : '';

// ─── 2. VALIDAR CAMPOS ───────────────────────────────────────────────────────
// Mismas reglas que assets/js/ajax-form.js. Obligatorios: name, email y privacy (message es opcional).
// 'fields' indica qué campo falló y por qué: 'required' (vacío) o 'format' (email inválido).
$errores = [];
$titulos = [];

if ($nombreRaw === '') {
    $errores['name'] = 'required';
    $titulos[]       = 'Nombre';
}

if ($email === '') {
    $errores['email'] = 'required';
    $titulos[]        = 'Email';
} elseif (!preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]+$/', $email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errores['email'] = 'format';
}

// Consentimiento: la casilla de la Política de Privacidad es obligatoria.
if ($privacidad !== '1') {
    $errores['privacy'] = 'required';
    $titulos[]          = 'Aceptar la Política de Privacidad';
}

if ($errores) {
    $partes = [];
    if ($titulos) {
        $partes[] = (count($titulos) > 1 ? 'Campos obligatorios: ' : 'Campo obligatorio: ') . implode(', ', $titulos);
    }
    if (($errores['email'] ?? '') === 'format') {
        $partes[] = 'Formato de email inválido';
    }
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => implode('. ', $partes), 'fields' => $errores]));
}

$nombre       = htmlspecialchars($nombreRaw);
$mensaje      = htmlspecialchars($mensajeRaw);
$mensajeTexto = $mensaje !== '' ? $mensaje : '(sin mensaje)';
$emailHtml    = htmlspecialchars($email);
$fechaConsentimiento = date('d/m/Y H:i') . ' (hora de Argentina)';

// ─── 3. CONFIGURACIÓN SMTP ───────────────────────────────────────────────────
// Ni la cuenta ni la contraseña de aplicación de Gmail van en el código: se leen de
// las variables de entorno GMAIL_USER y GMAIL_APP_PASSWORD (16 caracteres, con o sin espacios).
function leerEntorno(string $nombre): string
{
    $valor = getenv($nombre);
    if ($valor === false || $valor === '') {
        $valor = $_SERVER[$nombre] ?? $_ENV[$nombre] ?? '';
    }
    return trim((string) $valor);
}

$GMAIL_USER = leerEntorno('GMAIL_USER');
$GMAIL_PASS = str_replace(' ', '', leerEntorno('GMAIL_APP_PASSWORD'));
$EMPRESA    = 'Cámara de Trabajadores Microemprendedores de la R.A.';

if ($GMAIL_USER === '' || $GMAIL_PASS === '') {
    error_log('mailer.php: faltan las variables de entorno GMAIL_USER y/o GMAIL_APP_PASSWORD.');
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'El servicio de correo no está configurado. Intentá más tarde.']));
}

function crearMailer(string $GMAIL_USER, string $GMAIL_PASS): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com'; // ← Cambiar si cambiamos tipo de correo
    $mail->SMTPAuth   = true;
    $mail->Username   = $GMAIL_USER;
    $mail->Password   = $GMAIL_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';
    return $mail;
}

try {
    // ─── 4. EMAIL A LA EMPRESA ────────────────────────────────────────────────
    $mailEmpresa = crearMailer($GMAIL_USER, $GMAIL_PASS);
    $mailEmpresa->setFrom($GMAIL_USER, $EMPRESA);
    $mailEmpresa->addAddress($GMAIL_USER);
    $mailEmpresa->addReplyTo($email, $nombre);
    $mailEmpresa->isHTML(true);
    $mailEmpresa->Subject = "Nueva solicitud de contacto - $nombre";
    $mailEmpresa->Body = "
        <div style='font-family:sans-serif; font-size:15px; color:#222;'>
            <h2 style='color:#1a1a2e;'>Nueva solicitud de contacto</h2>
            <table style='border-collapse:collapse; width:100%;'>
                <tr style='background:#f5f5f5;'>
                    <td style='padding:10px; font-weight:bold; width:160px;'>Nombre:</td>
                    <td style='padding:10px;'>$nombre</td>
                </tr>
                <tr>
                    <td style='padding:10px; font-weight:bold;'>Email:</td>
                    <td style='padding:10px;'>$emailHtml</td>
                </tr>
                <tr style='background:#f5f5f5;'>
                    <td style='padding:10px; font-weight:bold;'>Mensaje:</td>
                    <td style='padding:10px;'>$mensajeTexto</td>
                </tr>
                <tr>
                    <td style='padding:10px; font-weight:bold;'>Consentimiento:</td>
                    <td style='padding:10px;'>Aceptó la Política de Privacidad el $fechaConsentimiento</td>
                </tr>
            </table>
        </div>
    ";
    $mailEmpresa->AltBody = "Nombre: $nombre\nEmail: $email\nMensaje: $mensajeTexto\nConsentimiento: aceptó la Política de Privacidad el $fechaConsentimiento";
    $mailEmpresa->send();

    // ─── 5. EMAIL DE CONFIRMACIÓN AL SOLICITANTE ──────────────────────────────
    $mailConfirmacion = crearMailer($GMAIL_USER, $GMAIL_PASS);
    $mailConfirmacion->setFrom($GMAIL_USER, $EMPRESA);
    $mailConfirmacion->addAddress($email, $nombre);
    $mailConfirmacion->isHTML(true);
    $mailConfirmacion->Subject = "Hemos recibido tu solicitud";
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/72ad7d25de1930a551a217005a40fd10.png', 'img-banner');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/98c04f9a014230e3b90b47c68c4c0a57.png', 'icon-1');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/750fd78a81b49b11b9e4e218a1a20552.png', 'icon-2');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/c573ca6a545ae962a081485fa0e1de9d.png', 'icon-3');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/f66b746083d417f32e8f3745897f67c0.png', 'icon-4');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/82567416112a14ce81f7165d3b6258e7.png', 'icon-5');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/WhatsApp.png', 'btn-whatsapp');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/facebook1.png', 'social-fb');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/tumbler1.png', 'social-ig');
    $mailConfirmacion->addEmbeddedImage(__DIR__ . '/img/mailer/google-plus1.png', 'social-yt');

    $mailConfirmacion->Body = <<<CONFIRM_HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd"><html xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office"><head><meta name="viewport" content="width=device-width, initial-scale=1.0"><link rel="preload" as="image" href="assets/img/mailer/72ad7d25de1930a551a217005a40fd10.png"><link rel="preload" as="image" href="assets/img/mailer/98c04f9a014230e3b90b47c68c4c0a57.png"><link rel="preload" as="image" href="assets/img/mailer/750fd78a81b49b11b9e4e218a1a20552.png"><link rel="preload" as="image" href="assets/img/mailer/c573ca6a545ae962a081485fa0e1de9d.png"><link rel="preload" as="image" href="assets/img/mailer/f66b746083d417f32e8f3745897f67c0.png"><link rel="preload" as="image" href="assets/img/mailer/82567416112a14ce81f7165d3b6258e7.png"><link rel="preload" as="image" href="assets/img/mailer/WhatsApp.png"><link rel="preload" as="image" href="assets/img/mailer/facebook1.png"><link rel="preload" as="image" href="assets/img/mailer/tumbler1.png"><link rel="preload" as="image" href="assets/img/mailer/google-plus1.png"><meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="format-detection" content="telephone=no, date=no, address=no, email=no"><meta name="x-apple-disable-message-reformatting"><meta name="keywords" content="DAHVkSVzgns, BAFpHtTrmh4"><style>body{margin:0;padding:0}table{mso-table-lspace:0;mso-table-rspace:0}p,span,h1,h2,h3,h4,h5,h6{margin:0;padding:0}p{line-height:inherit}a[x-apple-data-detectors]{color:inherit!important;text-decoration:inherit!important}#MessageViewBody a{color:inherit;text-decoration:none}img+div{display:none}@media (max-width:599px){.ecw{width:100%!important;min-width:0!important}}</style><!--[if mso]><div>
                <noscript>
                  <xml>
                    <w:WordDocument xmlns:w="urn:schemas-microsoft-com:office:word">
                      <w:DontUseAdvancedTypographyReadingMail/>
                    </w:WordDocument>
                    <o:OfficeDocumentSettings>
                      <o:AllowPNG/>
                      <o:PixelsPerInch>96</o:PixelsPerInch>
                    </o:OfficeDocumentSettings>
                  </xml>
                </noscript></div><![endif]--><style>@media(max-width:550px){.ers-fs-240{font-size:20px!important}}</style>
              </head>
              <body style="width:100%;-webkit-text-size-adjust:100%;text-size-adjust:100%;background-color:#f0f1f5;margin:0;padding:0"><table width="100%" border="0" cellpadding="0" cellspacing="0" bgcolor="#f0f1f5" style="background-color:#f0f1f5"><tbody><tr><td style="background-color:#f0f1f5"><!--[if mso]><center>
                    <table align="center" border="0" cellpadding="0" cellspacing="0" width="600">
                      <tbody>
                        <tr>
                          <td><![endif]--><table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation" class="ecw" style="max-width:600px;min-height:600px;margin:0 auto;background-color:#ffffff;width:600px;min-width:600px"><tbody><tr><td style="vertical-align:top"></td></tr><tr><td style="vertical-align:top"><table cellpadding="0" cellspacing="0" border="0" style="width:100%"><tbody><tr><td align="center"><table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px"><tbody><tr><td style="width:100%"><img src="assets/img/mailer/72ad7d25de1930a551a217005a40fd10.png" width="600" height="409" style="display:block;width:600px;height:auto;max-width:100%"></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td style="vertical-align:top;padding:0px
           0px
           0px
           0px">
           <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation"><tbody><tr><td style="vertical-align:top">
            <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word">
              <tbody>
                <tr>
                  <td style="padding:24px 24px 16px"><table border="0" cellpadding="0" cellspacing="0" class="layout-0" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto">
                    <tbody><tr><td style="text-align:center">
                      <table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto">
                        <tbody>
                          <tr>
                            <td width="100.00%" style="width:100.00%;box-sizing:border-box;vertical-align:top;background-color:#c9e7fe;background:radial-gradient(circle at 50% 50%, #c9e7fe,#dbe3fe,#ffffff,#ffffff)">
                              <table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td style="padding:24px">
                                <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word">
                                  <tbody>
                                    <tr>
                                      <td dir="ltr" class="ers-fs-240" style="color:#0a1044;font-size:24px;font-weight:700;white-space:pre-wrap;text-align:center;line-height:1.4;mso-line-height-alt:33.6px">¡Gracias por contactarte  con nosotros!<br>$nombre</td>
                                    </tr>
                                  </tbody>
                                </table>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </td>
            </tr>
          </tbody>
        </table>
      </td>
    </tr>
    <tr>
      <td dir="ltr" style="color:#0a1044;font-size:16px;text-align:center;padding:0px 24px 16px;line-height:1.4;mso-line-height-alt:22.4px"><span style="font-weight:700;white-space:pre-wrap">Hemos recibido correctamente tu solicitud de asesoramiento</span><span style="white-space:pre-wrap"> a través de nuestro sitio web. Tu caso ya está siendo revisado por un asesor especializado para brindarte la mejor atención en las áreas clave que manejamos. </span><br></td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:16px;text-align:center;padding:0px 24px 16px;line-height:1.4;mso-line-height-alt:22.4px"><span style="white-space:pre-wrap">Para tu tranquilidad, </span><span style="font-weight:700;white-space:pre-wrap">un consultor técnico de la Cámara se comunicará contigo en un plazo máximo de <span style="color: #041dff;">24 a 48</span> horas hábiles</span><span style="white-space:pre-wrap"> con el fin de coordinar una entrevista personalizada y analizar tu situación particular.</span><br></td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:16px;white-space:pre-wrap;text-align:center;padding:0px 24px 16px;line-height:1.4;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr><tr><td style="padding:0px 24px 16px"><table border="0" cellpadding="0" cellspacing="0" class="layout-1" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto"><tbody><tr><td style="text-align:center"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto"><tbody><tr><td width="100.00%" style="width:100.00%;box-sizing:border-box;vertical-align:top;background-color:#c9e7fe;background:radial-gradient(circle at 50% 50%, #c9e7fe,#dbe3fe,#ffffff,#ffffff)"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td style="padding:24px"><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" class="ers-fs-240" style="color:#0a1044;font-size:24px;font-weight:700;white-space:pre-wrap;text-align:center;line-height:1.4;mso-line-height-alt:33.6px">¿Cuáles son los próximos pasos?<br></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td style="padding:0px 24px 16px"><table border="0" cellpadding="0" cellspacing="0" class="layout-2" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto"><tbody><tr><td style="text-align:center"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto"><tbody><tr><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td><table cellpadding="0" cellspacing="0" border="0" style="width:100%"><tbody><tr><td align="center"><!--[if mso]><table cellpadding="0" cellspacing="0" border="0" width="26" style="width:26px"><tbody><tr><td><![endif]--><table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:26px"><tbody><tr><td style="width:100%"><img src="assets/img/mailer/98c04f9a014230e3b90b47c68c4c0a57.png" width="26" height="26" style="display:block;width:26px;height:auto;max-width:100%"></td></tr></tbody></table><!--[if mso]></td></tr></tbody></table><![endif]--></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="62.55%" style="width:62.55%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="color:#0a1044;font-size:16px;white-space:pre-wrap;text-align:left;line-height:1.4;mso-line-height-alt:22.4px">Capacitaciones y Formación<br></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="10.50%" style="width:10.50%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td style="padding:0px 24px 16px"><table border="0" cellpadding="0" cellspacing="0" class="layout-3" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto"><tbody><tr><td style="text-align:center"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto"><tbody><tr><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td><table cellpadding="0" cellspacing="0" border="0" style="width:100%"><tbody><tr><td align="center"><!--[if mso]><table cellpadding="0" cellspacing="0" border="0" width="26" style="width:26px"><tbody><tr><td><![endif]--><table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:26px">
        <tbody>
          <tr>
            <td style="width:100%"><img src="assets/img/mailer/750fd78a81b49b11b9e4e218a1a20552.png" width="26" height="26" style="display:block;width:26px;height:auto;max-width:100%">
            </td>
          </tr>
        </tbody>
      </table>
      <!--[if mso]>
      </td>
    </tr>
  </tbody>
</table>
<![endif]-->
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
<td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="62.55%" style="width:62.55%;box-sizing:border-box;vertical-align:top">
  <table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed">
    <tbody>
      <tr>
        <td>
          <table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word">
            <tbody>
              <tr>
                <td dir="ltr" style="color:#0a1044;font-size:16px;white-space:pre-wrap;text-align:left;line-height:1.4;mso-line-height-alt:22.4px">Asesoramiento Jurídico y Administrativo<br></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="10.50%" style="width:10.50%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td style="padding:0px 24px 16px"><table border="0" cellpadding="0" cellspacing="0" class="layout-4" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto"><tbody><tr><td style="text-align:center"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto"><tbody><tr><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td><table cellpadding="0" cellspacing="0" border="0" style="width:100%"><tbody><tr><td align="center"><!--[if mso]><table cellpadding="0" cellspacing="0" border="0" width="26" style="width:26px"><tbody><tr><td><![endif]--><table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:26px"><tbody><tr><td style="width:100%"><img src="assets/img/mailer/c573ca6a545ae962a081485fa0e1de9d.png" width="26" height="26" style="display:block;width:26px;height:auto;max-width:100%"></td></tr></tbody></table><!--[if mso]></td></tr></tbody></table><![endif]--></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="62.55%" style="width:62.55%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="color:#0a1044;font-size:16px;white-space:pre-wrap;text-align:left;line-height:1.4;mso-line-height-alt:22.4px">Estrategia Financiera y Beneficios<br></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="10.50%" style="width:10.50%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td style="padding:0px 24px 16px"><table border="0" cellpadding="0" cellspacing="0" class="layout-5" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto"><tbody><tr><td style="text-align:center"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto"><tbody><tr><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td><table cellpadding="0" cellspacing="0" border="0" style="width:100%"><tbody><tr><td align="center"><!--[if mso]><table cellpadding="0" cellspacing="0" border="0" width="26" style="width:26px"><tbody><tr><td><![endif]--><table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:26px"><tbody><tr><td style="width:100%"><img src="assets/img/mailer/f66b746083d417f32e8f3745897f67c0.png" width="26" height="26" style="display:block;width:26px;height:auto;max-width:100%"></td></tr></tbody></table><!--[if mso]></td></tr></tbody></table><![endif]--></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="62.55%" style="width:62.55%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="color:#0a1044;font-size:16px;white-space:pre-wrap;text-align:left;line-height:1.4;mso-line-height-alt:22.4px">Comercio Exterior<br></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="10.50%" style="width:10.50%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td>
                </tr>
              </tbody>
            </table>
          </td>
        </tr>
      </tbody>
    </table>
  </td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
  <td style="padding:0px 24px 16px"><table border="0" cellpadding="0" cellspacing="0" class="layout-6" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto"><tbody><tr><td style="text-align:center"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto"><tbody><tr><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top">
    <table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="9.13%" style="width:9.13%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td><table cellpadding="0" cellspacing="0" border="0" style="width:100%"><tbody><tr><td align="center"><!--[if mso]><table cellpadding="0" cellspacing="0" border="0" width="26" style="width:26px"><tbody><tr><td><![endif]--><table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:26px"><tbody><tr><td style="width:100%"><img src="assets/img/mailer/82567416112a14ce81f7165d3b6258e7.png" width="26" height="26" style="display:block;width:26px;height:auto;max-width:100%"></td></tr></tbody></table><!--[if mso]></td></tr></tbody></table><![endif]--></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="62.55%" style="width:62.55%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="color:#0a1044;font-size:16px;white-space:pre-wrap;text-align:left;line-height:1.4;mso-line-height-alt:22.4px">Consultoría e Infraestructura IT<br></td></tr></tbody></table></td></tr></tbody></table></td><td width="16" style="width:16px;box-sizing:border-box;font-size:0">&nbsp;</td><td width="10.50%" style="width:10.50%;box-sizing:border-box;vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="font-size:14.7px;white-space:pre-wrap;text-align:left;line-height:22.4px;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr><tr><td dir="ltr" style="font-size:16px;white-space:pre-wrap;text-align:left;padding:0px 24px 16px;line-height:1.4;mso-line-height-alt:22.4px;text-decoration:none">&nbsp;</td></tr><tr><td style="padding:0px 24px 16px"><table cellpadding="0" cellspacing="0" border="0" style="width:100%"><tbody><tr><td align="center"><!--[if mso]><table cellpadding="0" cellspacing="0" border="0" width="266" style="width:266px"><tbody><tr><td><![endif]--><table cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:266px"><tbody><tr><td style="width:100%"><!--[if mso]><v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="#" style="height:41px;width:266px;v-text-anchor:middle;" arcsize="62%" fillcolor="#25d366"><v:stroke dashstyle="Solid" weight="0px" color="#25d366"/><w:anchorlock/><v:textbox inset="0px,0px,0px,0px"><center dir="false" style="color:#ffffff;font-family:sans-serif;font-size:16.2px;font-weight:700">WhatsApp</center></v:textbox></v:roundrect><![endif]--><!--[if !mso]><!--><table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="width:100%;max-width:100%;border-collapse:separate;border-spacing:0"><tbody><tr><td bgcolor="#25d366" style="background-color:#25d366;border-bottom:0px solid transparent;border-left:0px solid transparent;border-radius:25px;border-right:0px solid transparent;border-top:0px solid transparent;mso-border-alt:none">
    <a href="https://wa.me/54XXXXXXXXXXX/?text=%20Hola,%20Agradezco%20la%20respuesta%20a%20mi%20solicitud.%20Para%20poder%20continuar%20con%20el%20proceso,%20agradecería%20que%20me%20brindaran%20más%20información%20y%20detalles%20sobre%20los%20pasos%20a%20seguir.%20Saludos%20cordiales." target="_blank" rel="noopener" ses:no-track="" style="color:#ffffff;text-decoration:none;display:block;padding-top:9px;padding-bottom:9px;padding-left:8px;padding-right:8px;text-align:center;font-family:Arial, Helvetica, sans-serif;font-size:16.2px;font-weight:700;letter-spacing:0em;line-height:22.7px;box-sizing:border-box;mso-line-height-rule:exactly"><img editable label="image"  mc:edit="km-165" src="assets/img/mailer/WhatsApp.png" width="189" height="40" alt="" /></a>
  </td>
</tr>
</tbody>
</table><!--<![endif]--></td>
</tr>
</tbody>
</table>
<!--[if mso]></td></tr></tbody></table><![endif]--></td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
</tbody>
</table>
</td>
</tr>
<tr>
  <td height="100%" style="height:100%;font-size:0;line-height:0" aria-hidden="true">&nbsp;</td></tr><tr><td style="vertical-align:top"><table border="0" cellpadding="0" cellspacing="0" class="layout-7" align="center" style="display:table;border-spacing:0px;border-collapse:separate;width:100%;max-width:100%;table-layout:fixed;margin:0 auto;background-color:#ffffff;background:linear-gradient(180deg, #ffffff,#dbe3fe,#c9e7fe)"><tbody><tr><td style="text-align:center;padding:19.5px 24px"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;max-width:552px;table-layout:fixed;margin:0 auto"><tbody><tr><td width="100.00%" style="width:100.00%;box-sizing:border-box;vertical-align:top;border-top-left-radius:0;border-top-right-radius:0;border-bottom-left-radius:0;border-bottom-right-radius:0"><table border="0" cellpadding="0" cellspacing="0" style="border-spacing:0px;border-collapse:separate;width:100%;table-layout:fixed"><tbody><tr><td style="padding:13px"><table align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="color:#000;font-style:normal;font-weight:normal;font-size:16px;line-height:1.4;letter-spacing:0;text-align:left;direction:ltr;border-collapse:collapse;font-family:Arial, Helvetica, sans-serif;white-space:normal;word-wrap:break-word;word-break:break-word"><tbody><tr><td dir="ltr" style="color:#0a1044;font-size:14.7px;letter-spacing:-0.0025em;white-space:pre-wrap;text-align:center;padding:0px 0px 16px;line-height:20.8px;mso-line-height-alt:20.8px"><a style="color: blueviolet;" target="_blank" href="https://tramitesadistancia.gob.ar/">Trámites a Distancia</a> | <a style="color: blueviolet;" target="_blank" href="index.html">Ver sitio web</a> | <a style="color: blueviolet;" target="_blank" href="https://www.argentina.gob.ar/economia/pymes-emprendedores-y-economia-del-conocimiento/centro-de-ayuda-pyme">Programas de apoyo</a><br></td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:14.7px;letter-spacing:-0.0025em;white-space:pre-wrap;text-align:center;padding:0px 0px 16px;line-height:20.8px;mso-line-height-alt:20.8px;text-decoration:none">&nbsp;</td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:16.4px;letter-spacing:-0.0025em;white-space:pre-wrap;text-align:center;padding:0px 0px 16px;line-height:20.8px;mso-line-height-alt:20.8px">Síguenos para no perderte nada<br></td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:14.7px;letter-spacing:-0.0025em;white-space:pre-wrap;text-align:center;padding:0px 0px 16px;line-height:20.8px;mso-line-height-alt:20.8px"><a target="_blank" style="padding: 20px;" href="https://www.facebook.com/login/?locale=es_ES"><img editable label="image"  mc:edit="km-210" src="assets/img/mailer/facebook1.png" width="64" height="64" alt="icon-facebook" /></a><a target="_blank" href="https://www.instagram.com/?hl=es-la"><img editable label="image"  mc:edit="km-210" src="./assets/img/mailer/tumbler1.png" width="64" height="64" alt="icon-Instagram" /></a><a target="_blank" style="padding: 20px;" href="https://www.youtube.com/playlist?list=PLjI-__EnCXRyurt-UirldA4FEvgLlqSmr"><img editable label="image"  mc:edit="km-210" src="./assets/img/mailer/google-plus1.png" width="64" height="64" alt="icon-YouTube" /></a><br></td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:14.7px;letter-spacing:-0.0025em;white-space:pre-wrap;text-align:center;padding:0px 0px 16px;line-height:20.8px;mso-line-height-alt:20.8px;text-decoration:none">&nbsp;</td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:13.3px;letter-spacing:-0.0025em;white-space:pre-wrap;text-align:center;padding:0px 0px 16px;line-height:20.8px;mso-line-height-alt:20.8px">Copyright ©2026 Cámara de Microemprendedores de la República Argentina. Todos los derechos reservado<br></td></tr><tr><td dir="ltr" style="color:#0a1044;font-size:13.3px;letter-spacing:-0.0025em;white-space:pre-wrap;text-align:center;line-height:20.8px;mso-line-height-alt:20.8px">Recibiste este correo porque completaste nuestro formulario de contacto web.<br></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table></td></tr></tbody></table><!--[if mso]></td>
                </tr>
              </tbody>
            </table>
          </center><![endif]--></td></tr></tbody></table></body></html>

CONFIRM_HTML;

    $mailConfirmacion->AltBody = "Estimado/a $nombre:\n\nHemos recibido correctamente su solicitud de asesoría.\n\nDatos recibidos:\nCorreo electrónico: $email\n\nEn breve uno de nuestros asesores se comunicará con usted.\n\nCámara de Microemprendedores de la República Argentina";
    $mailConfirmacion->send();

    echo json_encode(['success' => true, 'message' => '¡Mensaje enviado correctamente!']);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al enviar: ' . $e->getMessage()]);
}
