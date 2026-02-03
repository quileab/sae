<div>
    <a
        href="{{ $preferenceId ? 'https://www.mercadopago.com.ar/checkout/v1/redirect?preference-id=' . $preferenceId : '#' }}"
        @if(!$preferenceId) disabled @endif
        class="btn btn-primary"
    >
        <x-icon name="o-currency-dollar" class="w-5 h-5" />
        {{ __('Pagar con Mercado Pago') }}
    </a>
    @if($error)
        <p class="text-red-500">{{ $error }}</p>
    @endif
</div>