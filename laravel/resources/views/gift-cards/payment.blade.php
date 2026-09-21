@extends('layouts.guest')

@section('title', 'Paiement - '.$presentation['title'].' - OVANIE')
@section('meta_description', 'Paiement sécurisé de votre carte OVANIE.')

@push('styles')
<style>
    :root{--orange:var(--ov-orange,#ff6a00);--orange-2:#ff7b21;--navy:var(--ov-night,#020b1c)}
    .ov-page .ov-container,.gift-pay-page .ov-container{width:min(100% - 48px,1460px);margin:0 auto}
    .ov-breadcrumb{display:flex;align-items:center;gap:9px;flex-wrap:wrap;padding:22px 0 12px;color:#64748b;font-size:13px}
    .ov-breadcrumb a{color:#64748b;text-decoration:none}.ov-breadcrumb a:hover{color:var(--orange)}.ov-breadcrumb strong{color:var(--orange)}
    .gift-pay-page{padding:28px 0 64px;background:#f6f8fb;min-height:70vh}
    .gift-pay-grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(420px,1.1fr);gap:28px;align-items:start}
    .gift-summary,.gift-payment-box{background:#fff;border:1px solid #e3e8ef;border-radius:22px;box-shadow:0 16px 40px rgba(6,26,58,.08)}
    .gift-summary{overflow:hidden;position:sticky;top:18px}
    .gift-summary-media{padding:18px;background:#f8fafc;border-bottom:1px solid #e9edf3}
    .gift-summary-media img{display:block;width:100%;aspect-ratio:16/10;object-fit:contain;border-radius:16px;background:#fff}
    .gift-summary-body{padding:24px}
    .gift-type{display:inline-flex;padding:7px 11px;border-radius:999px;background:#fff3eb;color:#ff5a00;border:1px solid #ffd5be;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.5px}
    .gift-summary h1{margin:14px 0 8px;color:#07152d;font-size:28px;line-height:1.2}
    .gift-summary p{margin:0;color:#69768b;line-height:1.65}
    .gift-price{margin:18px 0 16px;font-size:34px;font-weight:950;color:#ff5a00}
    .gift-facts{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}
    .gift-fact{padding:13px;border:1px solid #e8edf3;border-radius:13px;background:#f9fbfd}
    .gift-fact span{display:block;color:#7b8799;font-size:11px;margin-bottom:4px}.gift-fact strong{color:#101b33;font-size:13px}

    .gift-payment-box{padding:28px}
    .pay-heading{border-bottom:1px solid #e6ebf1;padding-bottom:20px;margin-bottom:22px}
    .pay-heading h2{margin:0 0 8px;color:#07152d;font-size:30px}.pay-heading p{margin:0;color:#6b778a;line-height:1.6}
    .test-note{margin:0 0 20px;padding:13px 15px;border:1px solid #ffe0bf;background:#fff8ef;color:#9a4a00;border-radius:12px;font-size:13px;line-height:1.5}
    .section-label{display:block;margin-bottom:10px;color:#16233c;font-size:13px;font-weight:900}
    .recipient-choice{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:20px}
    .recipient-choice label{position:relative;display:flex;align-items:center;justify-content:center;gap:8px;min-height:54px;border:1px solid #dce3eb;border-radius:12px;background:#fff;cursor:pointer;font-weight:850;color:#17233b}
    .recipient-choice input{accent-color:#ff5a00}.recipient-choice label:has(input:checked){border-color:#ff5a00;background:#fff6f0;box-shadow:0 0 0 1px rgba(255,90,0,.06)}
    .recipient-fields{display:grid;grid-template-columns:repeat(2,1fr);gap:12px;padding:16px;border:1px solid #e4eaf1;border-radius:14px;background:#fafbfd;margin-bottom:20px}
    .recipient-fields[hidden]{display:none}
    .full{grid-column:1/-1}
    .field{display:flex;flex-direction:column;gap:7px}.field label{font-size:12px;font-weight:850;color:#26334a}.field input,.field textarea{width:100%;border:1px solid #dbe2ea;border-radius:11px;padding:13px 14px;font:inherit;color:#111827;background:#fff;outline:none}.field input:focus,.field textarea:focus{border-color:#ff5a00;box-shadow:0 0 0 3px rgba(255,90,0,.10)}.field textarea{min-height:96px;resize:vertical}

    .payment-section{margin-top:22px}.payment-section h3{margin:0 0 6px;color:#07152d;font-size:20px}.payment-section>p{margin:0 0 14px;color:#758196;font-size:13px}
    .operators{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
    .operator{position:relative;border:1px solid #dfe5ec;border-radius:14px;background:#fff;padding:12px 8px;min-height:86px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;cursor:pointer;transition:.18s ease}.operator:hover{border-color:#ff9b63}.operator:has(input:checked){border-color:#ff5a00;background:#fff7f2;box-shadow:0 0 0 2px rgba(255,90,0,.08)}.operator input{position:absolute;top:8px;right:8px;accent-color:#ff5a00}.operator img{height:30px;max-width:78px;object-fit:contain}.operator span{font-size:12px;font-weight:850;color:#253148;text-align:center}
    .phone-shell{display:flex;border:1px solid #dbe2ea;border-radius:11px;background:#fff;overflow:hidden}.phone-prefix{display:flex;align-items:center;padding:0 13px;background:#f5f7fa;color:#445067;font-weight:800;border-right:1px solid #e1e6ed}.phone-shell input{border:0!important;box-shadow:none!important;border-radius:0!important}
    .orange-otp[hidden]{display:none}
    .pay-total{margin:22px 0 14px;padding:16px 18px;background:#07152d;color:#fff;border-radius:14px;display:flex;align-items:center;justify-content:space-between;gap:16px}.pay-total span{font-size:13px;color:#cad4e3}.pay-total strong{font-size:23px}
    .pay-submit{width:100%;border:0;border-radius:13px;background:linear-gradient(135deg,#ff5a00,#ff7300);color:#fff;padding:16px 18px;font-weight:950;font-size:16px;cursor:pointer;box-shadow:0 12px 24px rgba(255,90,0,.24)}.pay-submit:hover{filter:brightness(.97)}.pay-submit:disabled{opacity:.6;cursor:not-allowed}
    .secure-line{display:flex;align-items:center;justify-content:center;gap:7px;color:#657187;font-size:12px;margin:13px 0 0}.secure-dot{width:8px;height:8px;background:#11a36a;border-radius:50%}
    .errors{margin-bottom:18px;padding:14px 16px;background:#fff0f0;border:1px solid #ffcaca;color:#a51d1d;border-radius:12px;font-size:13px}.errors p{margin:4px 0}
    .back-link{display:inline-flex;margin-bottom:16px;color:#5c687a;text-decoration:none;font-size:13px;font-weight:800}.back-link:hover{color:#ff5a00}
    @media(max-width:980px){.gift-pay-grid{grid-template-columns:1fr}.gift-summary{position:static}.operators{grid-template-columns:repeat(2,1fr)}}
    @media(max-width:600px){.gift-pay-page{padding-top:16px}.gift-payment-box,.gift-summary-body{padding:18px}.recipient-fields{grid-template-columns:1fr}.full{grid-column:auto}.gift-facts{grid-template-columns:1fr}.recipient-choice{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $selectedOperator = old('online_operator', $defaultOperator ?: 'wave');
    $operators = [
        'wave' => ['label' => 'Wave', 'logo' => 'images/operators/wave.png', 'placeholder' => '07 00 00 00 00'],
        'orange' => ['label' => 'Orange Money', 'logo' => 'images/operators/orange.png', 'placeholder' => '07 00 00 00 00'],
        'mtn' => ['label' => 'MTN MoMo', 'logo' => 'images/operators/mtn.png', 'placeholder' => '05 00 00 00 00'],
        'moov' => ['label' => 'Moov Money', 'logo' => 'images/operators/moov.png', 'placeholder' => '01 00 00 00 00'],
    ];
    $forMe = $product->is_rechargeable ? true : (string) old('for_me', '1') !== '0';
@endphp

<section class="gift-pay-page">
    <div class="ov-container">
        <a class="back-link" href="{{ route('gift-cards.category', $presentation['group']) }}">← Retour aux {{ mb_strtolower($category['title'] ?? 'cartes') }}</a>

        <div class="gift-pay-grid">
            <section class="gift-summary">
                <div class="gift-summary-media">
                    <img src="{{ asset($product->image_path) }}" alt="{{ $presentation['title'] }}">
                </div>
                <div class="gift-summary-body">
                    <span class="gift-type">{{ $presentation['type'] }}</span>
                    <h1>{{ $presentation['title'] }}</h1>
                    <p>{{ $presentation['summary'] }}</p>
                    <div class="gift-price">{{ number_format((float)$product->activation_price,0,',',' ') }} FCFA</div>

                    <div class="gift-facts">
                        <div class="gift-fact">
                            <span>Validité</span>
                            <strong>{{ $product->validity_days ? $product->validity_days.' jours' : $product->validity_months.' mois' }}</strong>
                        </div>
                        <div class="gift-fact">
                            <span>Format</span>
                            <strong>Carte digitale OVANIE</strong>
                        </div>
                        <div class="gift-fact">
                            <span>Utilisation</span>
                            <strong>Une ou plusieurs fois</strong>
                        </div>
                        <div class="gift-fact">
                            <span>Activation</span>
                            <strong>Après paiement confirmé</strong>
                        </div>
                    </div>
                </div>
            </section>

            <section class="gift-payment-box">
                <div class="pay-heading">
                    <h2>Paiement de votre carte</h2>
                    <p>Choisissez votre moyen de paiement. OVANIE traite la transaction de manière sécurisée en arrière-plan.</p>
                </div>

                @if($localPaymentSimulation)
                    <div class="test-note">
                        <strong>Mode local de test :</strong> le paiement sera simulé après validation du formulaire afin de tester tout le parcours OVANIE sans ouvrir une page externe.
                    </div>
                @endif

                @if(session('error'))
                    <div class="errors"><p>{{ session('error') }}</p></div>
                @endif

                @if($errors->any())
                    <div class="errors">
                        @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('gift-cards.payment.start', $product) }}" id="giftPaymentForm">
                    @csrf

                    @if($product->is_rechargeable)
                        <input type="hidden" name="for_me" value="1">
                    @else
                        <span class="section-label">Cette carte est</span>
                        <div class="recipient-choice">
                            <label><input type="radio" name="for_me" value="1" @checked($forMe)> Pour moi</label>
                            <label><input type="radio" name="for_me" value="0" @checked(! $forMe)> Pour offrir</label>
                        </div>
                    @endif

                    @if(! $product->is_rechargeable)
                        <div class="recipient-fields" id="recipientFields" @if($forMe) hidden @endif>
                            <div class="field">
                                <label for="recipient_name">Nom du bénéficiaire</label>
                                <input id="recipient_name" type="text" name="recipient_name" value="{{ old('recipient_name') }}" maxlength="150">
                            </div>
                            <div class="field">
                                <label for="recipient_phone">Téléphone</label>
                                <input id="recipient_phone" type="tel" name="recipient_phone" value="{{ old('recipient_phone') }}" maxlength="40">
                            </div>
                            <div class="field full">
                                <label for="recipient_email">E-mail du bénéficiaire</label>
                                <input id="recipient_email" type="email" name="recipient_email" value="{{ old('recipient_email') }}" maxlength="190">
                            </div>
                            <div class="field full">
                                <label for="personal_message">Message personnalisé (facultatif)</label>
                                <textarea id="personal_message" name="personal_message" maxlength="500" placeholder="Exemple : Profite bien de ta carte OVANIE.">{{ old('personal_message') }}</textarea>
                            </div>
                        </div>
                    @endif

                    <div class="payment-section">
                        <h3>Choisissez votre moyen de paiement</h3>
                        <p>Le numéro saisi doit correspondre au compte Mobile Money à débiter.</p>

                        <div class="operators">
                            @foreach($operators as $code => $operator)
                                <label class="operator">
                                    <input type="radio" name="online_operator" value="{{ $code }}" @checked($selectedOperator === $code) required>
                                    <img src="{{ asset($operator['logo']) }}" alt="{{ $operator['label'] }}">
                                    <span>{{ $operator['label'] }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="field">
                            <label for="paymentPhone">Numéro Mobile Money</label>
                            <div class="phone-shell">
                                <span class="phone-prefix">+225</span>
                                <input
                                    id="paymentPhone"
                                    type="tel"
                                    name="payment_phone"
                                    value="{{ old('payment_phone', $defaultPhone) }}"
                                    inputmode="tel"
                                    autocomplete="tel"
                                    maxlength="20"
                                    placeholder="07 00 00 00 00"
                                    required
                                >
                            </div>
                        </div>

                        <div class="field orange-otp" id="orangeOtpWrap" hidden style="margin-top:12px">
                            <label for="orangeOtp">Code de paiement Orange Money</label>
                            <input id="orangeOtp" type="text" name="orange_otp" value="{{ old('orange_otp') }}" maxlength="20" autocomplete="one-time-code">
                        </div>
                    </div>

                    <div class="pay-total">
                        <span>Total à payer</span>
                        <strong>{{ number_format((float)$product->activation_price,0,',',' ') }} FCFA</strong>
                    </div>

                    <button class="pay-submit" type="submit" id="payButton">
                        {{ $localPaymentSimulation ? 'Payer en mode test' : 'Payer maintenant' }}
                    </button>

                    <p class="secure-line"><span class="secure-dot"></span> Paiement sécurisé OVANIE</p>
                </form>
            </section>
        </div>
    </div>
</section>

<script>
(() => {
    const form = document.getElementById('giftPaymentForm');
    const recipientFields = document.getElementById('recipientFields');
    const giftRadios = document.querySelectorAll('input[name="for_me"]');
    const operatorRadios = document.querySelectorAll('input[name="online_operator"]');
    const phoneInput = document.getElementById('paymentPhone');
    const orangeOtpWrap = document.getElementById('orangeOtpWrap');
    const orangeOtp = document.getElementById('orangeOtp');
    const payButton = document.getElementById('payButton');
    const liveMode = @js(! $localPaymentSimulation && strtolower((string) config('paydunya.mode', 'test')) !== 'test');

    const placeholders = {
        wave: '07 00 00 00 00',
        orange: '07 00 00 00 00',
        mtn: '05 00 00 00 00',
        moov: '01 00 00 00 00'
    };

    function refreshRecipient() {
        if (!recipientFields) return;
        const selected = document.querySelector('input[name="for_me"]:checked');
        recipientFields.hidden = !selected || selected.value !== '0';
    }

    function refreshOperator() {
        const selected = document.querySelector('input[name="online_operator"]:checked');
        if (!selected) return;
        phoneInput.placeholder = placeholders[selected.value] || '07 00 00 00 00';
        const needsOtp = liveMode && selected.value === 'orange';
        orangeOtpWrap.hidden = !needsOtp;
        orangeOtp.required = needsOtp;
        if (!needsOtp) orangeOtp.value = '';
    }

    giftRadios.forEach(radio => radio.addEventListener('change', refreshRecipient));
    operatorRadios.forEach(radio => radio.addEventListener('change', refreshOperator));

    let submitting = false;
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
        payButton.disabled = true;
        payButton.textContent = 'Paiement en cours…';
    });

    window.addEventListener('pageshow', () => {
        submitting = false;
        payButton.disabled = false;
        payButton.textContent = @js($localPaymentSimulation ? 'Payer en mode test' : 'Payer maintenant');
        refreshRecipient();
        refreshOperator();
    });

    refreshRecipient();
    refreshOperator();
})();
</script>
@endsection
