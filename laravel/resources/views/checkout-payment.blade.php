<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Paiement sécurisé - OVANIE</title>
    <link rel="stylesheet" href="{{ asset('css/checkout-payment.css') }}">
</head>
<body>
@php
    $isPayDunyaTest = strtolower((string) config('paydunya.mode', 'test')) === 'test';
    $selectedOperator = old('online_operator', $defaultOperator ?: '');

    $operatorMeta = [
        'wave' => ['logo' => 'images/operators/wave.png', 'placeholder' => '07 00 00 00 00', 'requires_phone' => true],
        'orange' => ['logo' => 'images/operators/orange.png', 'placeholder' => '07 00 00 00 00', 'requires_phone' => true],
        'mtn' => ['logo' => 'images/operators/mtn.png', 'placeholder' => '05 00 00 00 00', 'requires_phone' => true],
        'moov' => ['logo' => 'images/operators/moov.png', 'placeholder' => '01 00 00 00 00', 'requires_phone' => true],
        'card' => ['logo' => null, 'placeholder' => '', 'requires_phone' => false],
    ];

    $operators = collect($onlineOperators ?? [])
        ->mapWithKeys(function ($item) use ($operatorMeta) {
            $code = (string) ($item['code'] ?? '');
            if ($code === '' || ! array_key_exists($code, $operatorMeta)) {
                return [];
            }
            return [$code => array_merge(
                ['label' => (string) ($item['label'] ?? $code)],
                $operatorMeta[$code]
            )];
        })
        ->all();

    if ($operators === []) {
        $operators = [
            'wave' => array_merge(['label' => 'Wave'], $operatorMeta['wave']),
            'orange' => array_merge(['label' => 'Orange Money'], $operatorMeta['orange']),
            'mtn' => array_merge(['label' => 'MTN MoMo'], $operatorMeta['mtn']),
            'moov' => array_merge(['label' => 'Moov Money'], $operatorMeta['moov']),
            'card' => array_merge(['label' => 'Carte bancaire'], $operatorMeta['card']),
        ];
    }


    $selectedOperatorData = $operators[$selectedOperator] ?? ['logo' => null, 'placeholder' => '', 'requires_phone' => false];

    $displayPhone = old('payment_phone', $defaultPhone);
@endphp

<header class="payment-topbar">
    <a href="{{ route('home') }}" class="payment-brand" aria-label="Accueil OVANIE">
        <img src="{{ asset('storage/logos/officiel site.png') }}" alt="OVANIE">
    </a>

    <div class="secure-label" aria-label="Paiement sécurisé">
        <span>Paiement sécurisé</span>
    </div>

    <form action="{{ route('checkout.payment.abandon', $order) }}" method="POST">
        @csrf
        <button type="submit" class="back-to-cart">Retour au panier</button>
    </form>
</header>

<main class="payment-main">
    <section class="payment-card" aria-labelledby="paymentTitle">
        <div class="payment-heading">
            <h1 id="paymentTitle">Paiement en ligne</h1>
            <p>Choisissez Mobile Money ou carte bancaire.</p>
        </div>

        @if(session('error'))
            <div class="payment-alert" role="alert">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="payment-alert" role="alert">
                @foreach($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <div class="payment-summary">
            <div>
                <span>Commande</span>
                <strong>{{ $order->order_number ?: '#' . $order->id }}</strong>
            </div>
            @if((float)($order->gift_card_amount ?? 0) > 0)
                <div>
                    <span>Carte cadeau utilisée</span>
                    <strong>-{{ number_format((float)$order->gift_card_amount, 0, ',', ' ') }} FCFA</strong>
                </div>
            @endif
            <div class="summary-total">
                <span>Reste à payer</span>
                <strong>{{ number_format((float) $amountDue, 0, ',', ' ') }} FCFA</strong>
            </div>
        </div>

        <form action="{{ route('checkout.payment.start', $order) }}" method="POST" id="onlinePaymentForm" class="payment-form">
            @csrf

            <label class="field-group" for="onlineOperator">
                <span class="field-label">Opérateur</span>
                <div class="operator-select-shell">
                    <img
                        src="{{ ! empty($selectedOperatorData['logo']) ? asset($selectedOperatorData['logo']) : '' }}"
                        alt=""
                        id="selectedOperatorLogo"
                        class="selected-operator-logo"
                        @if(empty($selectedOperatorData['logo'])) style="display:none" @endif
                    >
                    <select name="online_operator" id="onlineOperator" required>
                        <option value="" disabled @selected($selectedOperator === '')>Choisir un moyen de paiement</option>
                        @foreach($operators as $code => $operator)
                            <option
                                value="{{ $code }}"
                                data-logo="{{ ! empty($operator['logo']) ? asset($operator['logo']) : '' }}"
                                data-placeholder="{{ $operator['placeholder'] }}"
                                data-requires-phone="{{ ! empty($operator['requires_phone']) ? '1' : '0' }}"
                                @selected($selectedOperator === $code)
                            >
                                {{ $operator['label'] }}
                            </option>
                        @endforeach
                    </select>
                    <svg class="select-chevron" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="m7 10 5 5 5-5"/>
                    </svg>
                </div>
            </label>

            <label class="field-group" id="paymentPhoneField" for="paymentPhoneInput" @if(empty($selectedOperatorData['requires_phone'])) hidden @endif>
                <span class="field-label">Numéro Mobile Money</span>
                <div class="phone-input-shell">
                    <span class="phone-prefix">+225</span>
                    <input
                        type="tel"
                        name="payment_phone"
                        id="paymentPhoneInput"
                        value="{{ $displayPhone }}"
                        autocomplete="tel"
                        inputmode="tel"
                        placeholder="{{ $selectedOperatorData['placeholder'] }}"
                        maxlength="20"
                        pattern="[0-9 +()\-]{8,20}"
                        @if(! empty($selectedOperatorData['requires_phone'])) required @endif
                    >
                </div>
            </label>

            <label class="field-group orange-otp-field" id="orangeOtpField" for="orangeOtpInput" hidden>
                <span class="field-label">OTP Orange Money</span>
                <input
                    type="text"
                    name="orange_otp"
                    id="orangeOtpInput"
                    value="{{ old('orange_otp') }}"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    placeholder="Code à usage unique"
                    maxlength="20"
                >
                <small>Utilisez uniquement le code de paiement généré par Orange Money.</small>
            </label>

            <button type="submit" class="pay-button" id="payButton">
                <span>Payer maintenant</span>
                <strong>{{ number_format((float) $amountDue, 0, ',', ' ') }} FCFA</strong>
            </button>

            <p class="security-text">
                <svg viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 3 5 6v5c0 4.8 2.8 8.1 7 10 4.2-1.9 7-5.2 7-10V6l-7-3Z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                Transaction sécurisée. Ne communiquez jamais votre code secret.
            </p>

        </form>

        <div class="accepted-methods" aria-label="Moyens de paiement acceptés">
            <span>Moyens acceptés</span>
            <div class="accepted-logos">
                @foreach($operators as $operator)
                    @if(! empty($operator['logo']))
                        <img src="{{ asset($operator['logo']) }}" alt="{{ $operator['label'] }}">
                    @else
                        <span style="font-weight:800;color:#0f172a;font-size:12px;">{{ $operator['label'] }}</span>
                    @endif
                @endforeach
            </div>
        </div>
    </section>
</main>

<script>
    const form = document.getElementById('onlinePaymentForm');
    const operatorSelect = document.getElementById('onlineOperator');
    const selectedOperatorLogo = document.getElementById('selectedOperatorLogo');
    const phoneInput = document.getElementById('paymentPhoneInput');
    const paymentPhoneField = document.getElementById('paymentPhoneField');
    const orangeOtpField = document.getElementById('orangeOtpField');
    const orangeOtpInput = document.getElementById('orangeOtpInput');
    const payButton = document.getElementById('payButton');
    const liveMode = @js(! $isPayDunyaTest);

    let submitting = false;

    function refreshOperator() {
        const option = operatorSelect.options[operatorSelect.selectedIndex];
        const operator = operatorSelect.value;

        const logo = option.dataset.logo || '';
        selectedOperatorLogo.src = logo;
        selectedOperatorLogo.style.display = logo ? '' : 'none';

        const requiresPhone = operator !== '' && option.dataset.requiresPhone !== '0';
        paymentPhoneField.hidden = !requiresPhone;
        phoneInput.required = requiresPhone;
        phoneInput.placeholder = option.dataset.placeholder || '07 00 00 00 00';
        if (!requiresPhone) phoneInput.value = '';

        const needsOrangeOtp = liveMode && operator === 'orange';
        orangeOtpField.hidden = !needsOrangeOtp;
        orangeOtpInput.required = needsOrangeOtp;

        if (!needsOrangeOtp) {
            orangeOtpInput.value = '';
        }
    }

    operatorSelect.addEventListener('change', refreshOperator);

    form.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
            return;
        }

        submitting = true;
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = true;
        });

        const label = payButton.querySelector('span');

        if (label) {
            label.textContent = 'Paiement en cours…';
        }
    });

    window.addEventListener('pageshow', () => {
        submitting = false;
        form.querySelectorAll('button[type="submit"]').forEach((button) => {
            button.disabled = false;
        });

        const label = payButton.querySelector('span');
        if (label) {
            label.textContent = 'Payer maintenant';
        }

        refreshOperator();
    });

    refreshOperator();
</script>
</body>
</html>
