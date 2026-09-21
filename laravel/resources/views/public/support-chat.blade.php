<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Assistance OVANIE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,system-ui,sans-serif;background:linear-gradient(145deg,#eef5ff 0%,#f8fbff 52%,#fff5ed 100%);color:#10264f}.page{min-height:100vh;display:grid;place-items:center;padding:30px}.shell{width:min(1050px,100%);display:grid;grid-template-columns:360px minmax(0,1fr);background:#fff;border:1px solid #dfe7f1;border-radius:24px;overflow:hidden;box-shadow:0 30px 90px rgba(16,38,79,.16)}.side{padding:34px;background:#10264f;color:#fff}.logo{width:165px;max-height:66px;object-fit:contain;background:#fff;border-radius:12px;padding:8px;margin-bottom:28px}.side h1{font-size:28px;line-height:1.15;margin:0 0 12px}.side p{font-size:13px;line-height:1.7;color:#cbd7e8}.agents{display:grid;gap:12px;margin-top:28px}.agent{display:flex;gap:11px;padding:12px;border:1px solid rgba(255,255,255,.12);border-radius:12px;background:rgba(255,255,255,.06)}.avatar{width:38px;height:38px;border-radius:50%;display:grid;place-items:center;background:#fff;color:#10264f;font-weight:800}.agent strong{display:block;font-size:12px}.agent span{display:block;font-size:10px;color:#b8c6dc;margin-top:4px}.main{display:flex;flex-direction:column;min-height:680px}.top{padding:20px 24px;border-bottom:1px solid #e6ecf3;display:flex;justify-content:space-between;align-items:center}.top strong{font-size:14px}.status{font-size:10px;font-weight:800;color:#047857;background:#eafaf2;padding:7px 10px;border-radius:999px}.messages{flex:1;overflow:auto;padding:24px;background:#f8fafc;display:flex;flex-direction:column;gap:12px}.bubble{max-width:78%;padding:12px 14px;border-radius:14px;font-size:12px;line-height:1.6;white-space:pre-line}.bubble.ai{align-self:flex-start;background:#fff;border:1px solid #e1e8f0;border-bottom-left-radius:4px}.bubble.user{align-self:flex-end;background:#075ee8;color:#fff;border-bottom-right-radius:4px}.bubble.system{align-self:center;background:#fff7e6;color:#9a5a00;font-size:10px}.composer{padding:18px 22px;border-top:1px solid #e4eaf1;background:#fff}.identity{display:grid;grid-template-columns:repeat(3,1fr);gap:9px;margin-bottom:10px}.identity input,.composer textarea,.callback-form input{width:100%;border:1px solid #d8e1ec;border-radius:9px;padding:10px 11px;font:inherit;font-size:11px;outline:none}.composer textarea{min-height:74px;resize:vertical}.actions{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:10px}.btn{border:0;border-radius:9px;padding:11px 16px;font-size:11px;font-weight:800;cursor:pointer}.btn-primary{background:#075ee8;color:#fff}.btn-secondary{background:#eef3f8;color:#294364}.btn:disabled{opacity:.55;cursor:not-allowed}.callback{display:none;margin-top:12px;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc}.callback.open{display:block}.callback-form{display:grid;grid-template-columns:1fr 1fr;gap:9px}.callback-form .full{grid-column:1/-1}.notice{font-size:10px;color:#718096}.ticket{font-weight:800;color:#075ee8}@media(max-width:850px){.shell{grid-template-columns:1fr}.side{display:none}.main{min-height:100vh}.page{padding:0}.shell{border-radius:0}.identity{grid-template-columns:1fr}.bubble{max-width:90%}}
        .shell{grid-template-columns:minmax(0,1fr)}
    </style>
</head>
@php
    $service = in_array(request('service'), ['commercial', 'logistique', 'technique'], true)
        ? request('service')
        : 'client';
    $serviceAssistants = [
        'client' => ['title' => 'Service client 7j/7', 'name' => 'Miss N’Nan'],
        'commercial' => ['title' => 'Support commercial', 'name' => 'Miss Rita'],
        'logistique' => ['title' => 'Support logistique', 'name' => 'Assistante Logistique OVANIE'],
        'technique' => ['title' => 'Support technique', 'name' => 'Assistante Technique OVANIE'],
    ];
    $selectedAssistant = $serviceAssistants[$service];
@endphp
<body>
<div class="page">
    <div class="shell">
        <main class="main" data-support-service="{{ $service }}">
            <header class="top"><div><strong>{{ $selectedAssistant['title'] }}</strong><div class="notice">{{ $selectedAssistant['name'] }}</div></div><span class="status" id="connectionStatus">Disponible</span></header>
            <section class="messages" id="messages"><div class="bubble ai">Bonjour, je suis {{ $selectedAssistant['name'] }}. Comment puis-je vous aider aujourd’hui ?</div></section>
            <section class="composer">
                <div class="identity" id="identityFields"><input id="name" placeholder="Votre nom"><input id="email" type="email" placeholder="Votre e-mail"><input id="phone" placeholder="Votre téléphone"></div>
                <textarea id="messageInput" placeholder="Écrivez votre message ici..."></textarea>
                <div class="actions"><div><button class="btn btn-secondary" type="button" id="callbackToggle">Demander un rappel</button> <span class="notice" id="ticketInfo"></span></div><button class="btn btn-primary" type="button" id="sendButton">Envoyer</button></div>
                <div class="callback" id="callbackPanel">
                    <div class="callback-form"><input id="callbackPhone" placeholder="Téléphone à rappeler"><input id="callbackDate" type="datetime-local"><input class="full" id="callbackReason" placeholder="Motif du rappel"><button class="btn btn-primary full" id="callbackButton" type="button">Enregistrer la demande de rappel</button></div>
                </div>
            </section>
        </main>
    </div>
</div>
<script>
(() => {
    const messages = document.getElementById('messages');
    const input = document.getElementById('messageInput');
    const sendButton = document.getElementById('sendButton');
    const status = document.getElementById('connectionStatus');
    const identity = document.getElementById('identityFields');
    const ticketInfo = document.getElementById('ticketInfo');
    const callbackToggle = document.getElementById('callbackToggle');
    const callbackPanel = document.getElementById('callbackPanel');
    const callbackButton = document.getElementById('callbackButton');
    const service = document.querySelector('[data-support-service]').dataset.supportService;
    const storageKey = `ovanie_support_conversation_${service}`;
    let token = sessionStorage.getItem(storageKey);

    const add = (text, type = 'ai') => {
        const node = document.createElement('div');
        node.className = `bubble ${type}`;
        node.textContent = text;
        messages.appendChild(node);
        messages.scrollTop = messages.scrollHeight;
    };

    const json = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {'Content-Type': 'application/json', 'Accept': 'application/json', ...(options.headers || {})},
            ...options,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'Une erreur est survenue.');
        return payload;
    };

    const loadExisting = async () => {
        if (!token) return;
        try {
            const data = await json(`/api/support/chat/${token}`);
            messages.innerHTML = '';
            data.messages.forEach(item => add(item.body, item.sender === 'customer' ? 'user' : 'ai'));
            identity.style.display = 'none';
            if (data.ticket?.reference) ticketInfo.innerHTML = `Ticket <span class="ticket">${data.ticket.reference}</span>`;
        } catch (_) {
            sessionStorage.removeItem(storageKey);
            token = null;
        }
    };

    const send = async () => {
        const text = input.value.trim();
        if (!text) return;
        add(text, 'user');
        input.value = '';
        sendButton.disabled = true;
        status.textContent = 'Traitement...';
        try {
            let data;
            if (!token) {
                data = await json('/api/support/chat/start', {method: 'POST', body: JSON.stringify({name: document.getElementById('name').value, email: document.getElementById('email').value, phone: document.getElementById('phone').value, message: text, channel: 'chat', service})});
                token = data.conversation_token;
                sessionStorage.setItem(storageKey, token);
                identity.style.display = 'none';
                document.getElementById('callbackPhone').value = document.getElementById('phone').value;
            } else {
                data = await json(`/api/support/chat/${token}/messages`, {method: 'POST', body: JSON.stringify({message: text})});
            }
            add(data.reply, 'ai');
            if (data.requires_human) add('Votre dossier est en attente de prise en charge par un agent humain.', 'system');
            if (data.ticket_reference) ticketInfo.innerHTML = `Ticket <span class="ticket">${data.ticket_reference}</span>`;
            status.textContent = data.requires_human ? 'Transfert humain' : 'Disponible';
        } catch (error) {
            add(error.message, 'system');
            status.textContent = 'Erreur';
        } finally {
            sendButton.disabled = false;
        }
    };

    sendButton.addEventListener('click', send);
    input.addEventListener('keydown', event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); send(); } });
    callbackToggle.addEventListener('click', () => callbackPanel.classList.toggle('open'));
    callbackButton.addEventListener('click', async () => {
        if (!token) { add('Envoyez d’abord un message afin d’ouvrir votre dossier.', 'system'); return; }
        callbackButton.disabled = true;
        try {
            const data = await json(`/api/support/chat/${token}/callback`, {method: 'POST', body: JSON.stringify({phone: document.getElementById('callbackPhone').value, preferred_at: document.getElementById('callbackDate').value || null, reason: document.getElementById('callbackReason').value})});
            add(`Demande de rappel enregistrée : ${data.reference}`, 'system');
            callbackPanel.classList.remove('open');
        } catch (error) { add(error.message, 'system'); }
        finally { callbackButton.disabled = false; }
    });
    loadExisting();
})();
</script>
</body>
</html>
