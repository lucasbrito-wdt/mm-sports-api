@props(['title' => 'MM Sports', 'preheader' => '', 'headerLabel' => ''])
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="x-apple-disable-message-reformatting">
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<title>{{ $title ?? 'MM Sports' }}</title>
<style>
@media (prefers-color-scheme: dark) {
  .mm-body { background: #0a0a0a !important; }
  .mm-card { background: #141414 !important; border-color: #262626 !important; }
  .mm-text { color: #f5f5f5 !important; }
  .mm-muted { color: #a3a3a3 !important; }
  .mm-divider { border-color: #262626 !important; }
  .mm-row { border-color: #262626 !important; }
  .mm-soft { background: #1a1a1a !important; }
}
@media only screen and (max-width: 600px) {
  .mm-container { width: 100% !important; }
  .mm-pad { padding: 24px !important; }
  .mm-h1 { font-size: 22px !important; }
  .mm-stack { display: block !important; width: 100% !important; }
}
</style>
</head>
<body class="mm-body" style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">

<div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
  {{ $preheader ?? '' }}
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f5;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <!-- Header -->
      <table role="presentation" class="mm-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;">
        <tr>
          <td style="padding:0 0 20px 0;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
              <tr>
                <td align="left" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:22px;font-weight:900;letter-spacing:0.2em;color:#0a0a0a;text-transform:uppercase;">
                  <span style="color:#E30613;">MM</span><span class="mm-text" style="color:#0a0a0a;">SPORTS</span>
                </td>
                <td align="right" class="mm-muted" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:#71717a;">
                  {{ $headerLabel ?? '' }}
                </td>
              </tr>
            </table>
          </td>
        </tr>
      </table>

      <!-- Card -->
      <table role="presentation" class="mm-container mm-card" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;background:#ffffff;border:1px solid #e4e4e7;border-radius:16px;overflow:hidden;">
        <tr>
          <td style="height:4px;background:#E30613;line-height:4px;font-size:0;">&nbsp;</td>
        </tr>
        <tr>
          <td class="mm-pad" style="padding:40px;">
            {{ $slot }}
          </td>
        </tr>
      </table>

      <!-- Footer -->
      <table role="presentation" class="mm-container" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px;width:100%;margin-top:24px;">
        <tr>
          <td align="center" class="mm-muted" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;color:#71717a;line-height:1.6;">
            Você recebeu este e-mail porque fez uma compra na MM Sports.<br>
            &copy; {{ date('Y') }} MM Sports. Todos os direitos reservados.
          </td>
        </tr>
      </table>

    </td>
  </tr>
</table>

</body>
</html>
