@php
  /** @var \App\Domains\Commerce\Models\Order $order */
  $fmt = fn ($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
  $addr = $order->shipping_address_snapshot ?? [];
  $shipping = $order->shipping_quote_json ?? [];
@endphp

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;">
  @foreach ($order->items as $item)
    <tr>
      <td class="mm-row" style="border-top:1px solid #e4e4e7;padding:14px 0;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td class="mm-text" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:#0a0a0a;line-height:1.4;">
              <strong>{{ $item->product_title_snapshot }}</strong>
              @if ($item->variant_label_snapshot)
                <br>
                <span class="mm-muted" style="font-size:12px;color:#71717a;">{{ $item->variant_label_snapshot }}</span>
              @endif
              <br>
              <span class="mm-muted" style="font-size:12px;color:#71717a;">Qtd: {{ $item->quantity }}</span>
            </td>
            <td align="right" class="mm-text" valign="top" style="font-family:'SF Mono',Menlo,Consolas,monospace;font-size:14px;font-weight:700;color:#0a0a0a;white-space:nowrap;padding-left:16px;">
              {{ $fmt($item->unit_price * $item->quantity) }}
            </td>
          </tr>
        </table>
      </td>
    </tr>
  @endforeach
</table>

<!-- Totals -->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:8px;border-top:1px solid #e4e4e7;" class="mm-divider">
  <tr>
    <td class="mm-muted" style="padding:10px 0 4px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#71717a;">Subtotal</td>
    <td align="right" class="mm-text" style="padding:10px 0 4px;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:13px;color:#0a0a0a;">{{ $fmt($order->subtotal) }}</td>
  </tr>
  @if ((float) $order->discount_total > 0)
    <tr>
      <td class="mm-muted" style="padding:4px 0;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#71717a;">
        Desconto
        @if ($order->coupon_code)
          <span style="color:#16a34a;font-weight:700;">({{ $order->coupon_code }})</span>
        @endif
      </td>
      <td align="right" style="padding:4px 0;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:13px;color:#16a34a;">−{{ $fmt($order->discount_total) }}</td>
    </tr>
  @endif
  <tr>
    <td class="mm-muted" style="padding:4px 0 12px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:13px;color:#71717a;">
      Frete
      @if (! empty($shipping['service_name']))
        <span style="color:#a1a1aa;">· {{ $shipping['service_name'] }}</span>
      @endif
    </td>
    <td align="right" class="mm-text" style="padding:4px 0 12px;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:13px;color:#0a0a0a;">{{ $fmt($order->shipping_total) }}</td>
  </tr>
  <tr>
    <td class="mm-text" style="padding:14px 0 0;border-top:1px solid #e4e4e7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:12px;font-weight:900;letter-spacing:0.18em;text-transform:uppercase;color:#0a0a0a;" class="mm-divider">Total</td>
    <td align="right" style="padding:14px 0 0;border-top:1px solid #e4e4e7;font-family:'SF Mono',Menlo,Consolas,monospace;font-size:22px;font-weight:900;color:#E30613;" class="mm-divider">{{ $fmt($order->grand_total) }}</td>
  </tr>
</table>

@if (! empty($addr))
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:28px;">
    <tr>
      <td class="mm-soft" style="background:#fafafa;border-radius:12px;padding:18px 20px;">
        <div class="mm-muted" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:11px;font-weight:900;letter-spacing:0.18em;text-transform:uppercase;color:#71717a;margin-bottom:8px;">
          Endereço de entrega
        </div>
        <div class="mm-text" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;font-size:14px;color:#0a0a0a;line-height:1.6;">
          {{ $addr['recipient_name'] ?? '' }}<br>
          {{ $addr['street'] ?? '' }}, {{ $addr['number'] ?? '' }}{{ ! empty($addr['complement']) ? ' — '.$addr['complement'] : '' }}<br>
          {{ ! empty($addr['district']) ? $addr['district'].' · ' : '' }}{{ $addr['city'] ?? '' }}/{{ $addr['state'] ?? '' }}<br>
          CEP {{ $addr['postal_code'] ?? '' }}
        </div>
      </td>
    </tr>
  </table>
@endif
