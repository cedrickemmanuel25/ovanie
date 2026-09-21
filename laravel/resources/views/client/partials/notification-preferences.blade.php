@php
    $prefs = auth()->user()->notification_preferences ?? [];
    $rows = [
        'orders' => 'Commandes',
        'payments' => 'Paiements',
        'deliveries' => 'Livraisons',
        'returns' => 'Retours et remboursements',
        'promotions' => 'Promotions et offres',
        'account' => 'Compte',
        'support' => 'Support',
        'security' => 'Sécurité du compte',
        'newsletter' => 'Newsletter',
        'cart' => 'Rappels panier',
    ];
    $channels = ['email' => 'Email', 'sms' => 'SMS', 'in_app' => 'Dans l’application'];
@endphp

<div class="cs-table-wrap">
    <table class="cs-table prefs">
        <thead>
            <tr>
                <th>Type</th>
                @foreach($channels as $label)
                    <th>{{ $label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $key => $label)
                <tr>
                    <td>
                        {{ $label }}
                        @if($key === 'security')
                            <small style="display:block;color:#64748b;margin-top:4px;">Toujours actives pour protéger votre compte.</small>
                        @endif
                    </td>
                    @foreach($channels as $channel => $channelLabel)
                        <td>
                            <label class="cs-switch">
                                @if($key === 'security')
                                    <input type="checkbox" checked disabled>
                                    <input type="hidden" name="preferences[security][{{ $channel }}]" value="1">
                                @else
                                    <input
                                        type="checkbox"
                                        name="preferences[{{ $key }}][{{ $channel }}]"
                                        value="1"
                                        @checked(data_get(
                                            $prefs,
                                            "$key.$channel",
                                            $channel === 'in_app' ? data_get($prefs, "$key.push", true) : true
                                        ))
                                    >
                                @endif
                                <span></span>
                            </label>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
