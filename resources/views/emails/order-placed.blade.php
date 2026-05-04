@php
  $shortId = '#' . strtoupper(substr((string) $order->id, -8));
  $methodLabels = [
    'pix' => 'PIX',
    'credit_card' => 'Cartão de crédito',
    'boleto' => 'Boleto bancário',
  ];
  $method = $methodLabels[$order->payment_method] ?? strtoupper((string) $order->payment_method);
  $appUrl = rtrim((string) config('app.url'), '/');
  $firstName = $order->user?->name ? explode(' ', trim($order->user->name))[0] : 'atleta';
@endphp

<x-mm-mail-layout
  :title="'Pedido recebido ' . $shortId"
  preheader="Recebemos seu pedido e estamos aguardando a confirmação do pagamento."
  :headerLabel="'Pedido ' . $shortId"
>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td>
      <div style="display:inline-block;padding:6px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:999px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:900;letter-spacing:0.16em;text-transform:uppercase;color:#E30613;">
        Aguardando pagamento
      </div>
    </td>
  </tr>
  <tr>
    <td class="mm-h1 mm-text" style="padding-top:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:28px;font-weight:900;line-height:1.2;color:#0a0a0a;letter-spacing:-0.01em;">
      Recebemos seu pedido,<br>{{ $firstName }}!
    </td>
  </tr>
  <tr>
    <td class="mm-muted" style="padding-top:12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#52525b;">
      Estamos aguardando a confirmação do pagamento via <strong class="mm-text" style="color:#0a0a0a;">{{ $method }}</strong>. Assim que cair, você recebe um novo e-mail e o pedido entra em separação.
    </td>
  </tr>
</table>

@if ($order->payment_method === 'pix' && $order->asaas_pix_copy_paste)
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
    <tr>
      <td class="mm-soft mm-divider" style="background:#fafafa;border:1px solid #e4e4e7;border-radius:12px;padding:18px 20px;">
        <div class="mm-muted" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:900;letter-spacing:0.18em;text-transform:uppercase;color:#71717a;margin-bottom:10px;">
          PIX Copia e Cola
        </div>
        <div class="mm-text" style="font-family:'SF Mono',Menlo,Consolas,monospace;font-size:11px;line-height:1.5;color:#0a0a0a;word-break:break-all;background:#ffffff;border:1px solid #e4e4e7;border-radius:8px;padding:12px;">
          {{ $order->asaas_pix_copy_paste }}
        </div>
      </td>
    </tr>
  </table>
@elseif ($order->payment_method === 'boleto' && $order->asaas_boleto_url)
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:24px;">
    <tr>
      <td align="center">
        <a href="{{ $order->asaas_boleto_url }}" target="_blank" style="display:inline-block;background:#0a0a0a;color:#ffffff;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:900;letter-spacing:0.14em;text-transform:uppercase;padding:14px 28px;border-radius:10px;">
          Visualizar boleto
        </a>
      </td>
    </tr>
  </table>
@endif

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:32px;">
  <tr>
    <td class="mm-text" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:900;letter-spacing:0.18em;text-transform:uppercase;color:#0a0a0a;">
      Resumo do pedido
    </td>
  </tr>
</table>

@include('emails._partials.items', ['order' => $order])

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:32px;">
  <tr>
    <td align="center">
      <a href="{{ $appUrl }}/checkout/confirmacao/{{ $order->id }}?method={{ $order->payment_method }}" target="_blank" style="display:inline-block;background:#E30613;color:#ffffff;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:900;letter-spacing:0.14em;text-transform:uppercase;padding:14px 32px;border-radius:10px;box-shadow:0 4px 14px rgba(227,6,19,0.25);">
        Acompanhar pedido
      </a>
    </td>
  </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:32px;">
  <tr>
    <td class="mm-muted" align="center" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;line-height:1.6;color:#71717a;">
      Dúvidas? Responda este e-mail ou fale com a gente no WhatsApp.
    </td>
  </tr>
</table>

</x-mm-mail-layout>
