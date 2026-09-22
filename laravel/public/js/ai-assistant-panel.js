document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('ovaiPanel');
    const overlay = document.getElementById('ovaiOverlay');
    if (!panel || !overlay) return; // Non connecté : le partial n'est pas rendu.

    const triggers = document.querySelectorAll('[data-ai-panel-trigger]');
    const closeButton = document.getElementById('ovaiClose');
    const restartButton = document.getElementById('ovaiRestart');
    const body = document.getElementById('ovaiBody');
    const resumeChoice = document.getElementById('ovaiResumeChoice');
    const resumePreview = document.getElementById('ovaiResumePreview');
    const resumeContinue = document.getElementById('ovaiResumeContinue');
    const resumeNew = document.getElementById('ovaiResumeNew');
    const welcome = document.getElementById('ovaiWelcome');
    const suggestions = document.getElementById('ovaiSuggestions');
    const messages = document.getElementById('ovaiMessages');
    const input = document.getElementById('ovaiInput');
    const sendButton = document.getElementById('ovaiSend');
    const status = document.getElementById('ovaiStatus');
    const callbackToggle = document.getElementById('ovaiCallbackToggle');
    const callbackPanel = document.getElementById('ovaiCallback');
    const callbackSubmit = document.getElementById('ovaiCallbackSubmit');
    const callbackCancel = document.getElementById('ovaiCallbackCancel');

    const storageKey = 'ovanie_ai_assistant_conversation';
    let token = sessionStorage.getItem(storageKey);
    let sending = false;
    let initialStateResolved = false;
    let pendingResumeToken = null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

    const json = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrf(),
                ...(options.headers || {}),
            },
            credentials: 'same-origin',
            ...options,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(payload.message || Object.values(payload.errors || {}).flat()[0] || 'Une erreur est survenue.');
        }
        return payload;
    };

    const scrollToBottom = () => { body.scrollTop = body.scrollHeight; };

    const addBubble = (text, type = 'ai') => {
        const node = document.createElement('div');
        node.className = `ovai-bubble ${type}`;
        node.textContent = text;
        messages.appendChild(node);
        scrollToBottom();
    };

    const showTyping = () => {
        const node = document.createElement('div');
        node.className = 'ovai-typing';
        node.id = 'ovaiTypingIndicator';
        node.innerHTML = '<span></span><span></span><span></span>';
        messages.appendChild(node);
        scrollToBottom();
    };

    const hideTyping = () => { document.getElementById('ovaiTypingIndicator')?.remove(); };

    const setStatus = (text) => {
        status.textContent = text || '';
        status.hidden = !text;
    };

    const autosizeInput = () => {
        input.style.height = 'auto';
        input.style.height = `${Math.min(110, input.scrollHeight)}px`;
    };

    const openPanel = () => {
        panel.classList.add('is-open');
        overlay.classList.add('is-active');
        panel.setAttribute('aria-hidden', 'false');
        document.body.classList.add('menu-open');
        resolveInitialState();
        setTimeout(() => input?.focus(), 260);
    };

    const closePanel = () => {
        panel.classList.remove('is-open');
        overlay.classList.remove('is-active');
        panel.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('menu-open');
    };

    const showWelcome = () => {
        resumeChoice.hidden = true;
        welcome.hidden = false;
    };

    /**
     * Repart à zéro : l'ancienne conversation reste intacte côté serveur
     * (récupérable la prochaine fois via "Reprendre la conversation"), mais
     * cet onglet oublie son jeton et un nouveau dossier sera créé au
     * prochain message envoyé.
     */
    const startNewConversation = () => {
        sessionStorage.removeItem(storageKey);
        token = null;
        pendingResumeToken = null;
        messages.innerHTML = '';
        if (callbackPanel) callbackPanel.hidden = true;
        callbackToggle?.classList.remove('is-active');
        setStatus('');
        showWelcome();
    };

    const loadExisting = async () => {
        if (!token || messages.childElementCount) return;
        try {
            const data = await json(`/api/support/chat/${token}`);
            welcome.hidden = true;
            resumeChoice.hidden = true;
            if (data.messages?.length) {
                data.messages.forEach((item) => addBubble(item.body, item.sender === 'customer' ? 'user' : 'ai'));
            }
        } catch (_) {
            sessionStorage.removeItem(storageKey);
            token = null;
            showWelcome();
        }
    };

    /**
     * Demande utilisateur : à l'ouverture du panneau, proposer un choix
     * explicite entre reprendre la dernière conversation et en démarrer
     * une nouvelle - plutôt que de toujours repartir de zéro ou de
     * reprendre automatiquement en silence. Ne s'exécute qu'une fois par
     * chargement de page ; si une conversation est déjà active dans cet
     * onglet (token en sessionStorage), elle reprend directement sans
     * redemander.
     */
    const resolveInitialState = async () => {
        if (initialStateResolved) return;
        initialStateResolved = true;

        if (token) {
            welcome.hidden = true;
            resumeChoice.hidden = true;
            await loadExisting();
            return;
        }

        try {
            const data = await json('/api/support/chat/latest');
            if (data.exists) {
                pendingResumeToken = data.conversation_token;
                resumePreview.textContent = data.preview || '';
                welcome.hidden = true;
                resumeChoice.hidden = false;
                return;
            }
        } catch (_) {
            // Si la vérification échoue, on retombe simplement sur l'accueil normal.
        }

        showWelcome();
    };

    const sendMessage = async (text) => {
        const message = text.trim();
        if (!message || sending) return;

        welcome.hidden = true;
        resumeChoice.hidden = true;
        addBubble(message, 'user');
        input.value = '';
        autosizeInput();
        sending = true;
        sendButton.disabled = true;
        showTyping();
        setStatus('N’Nan rédige une réponse…');

        try {
            let data;
            if (!token) {
                data = await json('/api/support/chat/start', {
                    method: 'POST',
                    body: JSON.stringify({ message, channel: 'chat', service: 'client' }),
                });
                token = data.conversation_token;
                sessionStorage.setItem(storageKey, token);
            } else {
                data = await json(`/api/support/chat/${token}/messages`, {
                    method: 'POST',
                    body: JSON.stringify({ message }),
                });
            }

            hideTyping();
            addBubble(data.reply, 'ai');
            if (data.requires_human) {
                addBubble('Votre dossier est en attente de prise en charge par un conseiller.', 'system');
            }
            setStatus(data.requires_human ? 'Transfert vers un conseiller' : '');
        } catch (error) {
            hideTyping();
            addBubble(error.message, 'system');
            setStatus('');
        } finally {
            sending = false;
            sendButton.disabled = false;
        }
    };

    triggers.forEach((trigger) => trigger.addEventListener('click', (event) => {
        event.preventDefault();
        openPanel();
    }));

    closeButton?.addEventListener('click', closePanel);
    restartButton?.addEventListener('click', startNewConversation);
    overlay.addEventListener('click', closePanel);
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && panel.classList.contains('is-open')) closePanel();
    });

    suggestions?.addEventListener('click', (event) => {
        const button = event.target.closest('[data-ovai-suggestion]');
        if (!button) return;
        sendMessage(button.dataset.ovaiSuggestion);
    });

    resumeContinue?.addEventListener('click', async () => {
        if (!pendingResumeToken) return;
        token = pendingResumeToken;
        pendingResumeToken = null;
        sessionStorage.setItem(storageKey, token);
        resumeChoice.hidden = true;
        welcome.hidden = true;
        await loadExisting();
    });

    resumeNew?.addEventListener('click', startNewConversation);

    sendButton?.addEventListener('click', () => sendMessage(input.value));
    input?.addEventListener('input', autosizeInput);
    input?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            sendMessage(input.value);
        }
    });

    callbackToggle?.addEventListener('click', () => {
        const opening = callbackPanel.hidden;
        callbackPanel.hidden = !opening;
        callbackToggle.classList.toggle('is-active', opening);
    });
    callbackCancel?.addEventListener('click', () => {
        callbackPanel.hidden = true;
        callbackToggle.classList.remove('is-active');
    });
    callbackSubmit?.addEventListener('click', async () => {
        const phone = document.getElementById('ovaiCallbackPhone').value.trim();
        if (!phone) {
            addBubble('Indiquez un numéro de téléphone pour le rappel.', 'system');
            return;
        }
        if (!token) {
            addBubble('Envoyez d’abord un message pour ouvrir votre dossier.', 'system');
            return;
        }

        callbackSubmit.disabled = true;
        try {
            const data = await json(`/api/support/chat/${token}/callback`, {
                method: 'POST',
                body: JSON.stringify({
                    phone,
                    preferred_at: document.getElementById('ovaiCallbackDate').value || null,
                    reason: document.getElementById('ovaiCallbackReason').value || null,
                }),
            });
            welcome.hidden = true;
            addBubble(`Demande de rappel enregistrée : ${data.reference}`, 'system');
            callbackPanel.hidden = true;
            callbackToggle.classList.remove('is-active');
        } catch (error) {
            addBubble(error.message, 'system');
        } finally {
            callbackSubmit.disabled = false;
        }
    });

    if (new URLSearchParams(window.location.search).get('ai') === '1') {
        openPanel();
    }
});
