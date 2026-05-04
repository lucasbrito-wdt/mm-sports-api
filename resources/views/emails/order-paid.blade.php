@php
  $shortId = '#' . strtoupper(substr((string) $order->id, -8));
  $appUrl = rtrim((string) config('app.url'), '/');
  $firstName = $order->user?->name ? explode(' ', trim($order->user->name))[0] : 'atleta';
@endphp

<x-mm-mail-layout
  :title="'Pagamento confirmado ' . $shortId"
  preheader="Seu pagamento caiu. Já estamos preparando seu pedido para envio."
  :headerLabel="'Pedido ' . $shortId"
>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td>
      <div style="display:inline-block;padding:6px 12px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:999px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:900;letter-spacing:0.16em;text-transform:uppercase;color:#16a34a;">
        ✓ Pagamento confirmado
      </div>
    </td>
  </tr>
  <tr>
    <td class="mm-h1 mm-text" style="padding-top:20px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:28px;font-weight:900;line-height:1.2;color:#0a0a0a;letter-spacing:-0.01em;">
      Tudo certo, {{ $firstName }}.<br>Seu pedido está confirmado.
    </td>
  </tr>
  <tr>
    <td class="mm-muted" style="padding-top:12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#52525b;">
      Já estamos separando seus itens. Quando o pedido for despachado você recebe o código de rastreio por aqui.
    </td>
  </tr>
</table>

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
      <a href="{{ $appUrl }}/minha-conta/pedidos/{{ $order->id }}" target="_blank" style="display:inline-block;background:#E30613;color:#ffffff;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;font-weight:900;letter-spacing:0.14em;text-transform:uppercase;padding:14px 32px;border-radius:10px;box-shadow:0 4px 14px rgba(227,6,19,0.25);">
        Ver detalhes do pedido
      </a>
    </td>
  </tr>
</table>

</x-mm-mail-layout>
