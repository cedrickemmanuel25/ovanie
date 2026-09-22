{{-- Assistant IA OVANIE ("N’Nan") : panneau coulissant depuis la gauche,
     sur le modèle de "Alexa for shopping" chez Amazon. Réservé aux clients
     connectés (voir la condition @auth qui inclut ce partial). --}}
<div class="ovai-overlay" id="ovaiOverlay" data-ovai-overlay></div>

<aside class="ovai-panel" id="ovaiPanel" aria-hidden="true" aria-label="Assistant N’Nan">
    <header class="ovai-panel__head">
        <div class="ovai-panel__identity">
            <img src="{{ asset('images/ai-assistant/n-nan.webp') }}" alt="N’Nan" class="ovai-panel__avatar">
            <div>
                <strong>N’Nan</strong>
                <span>Assistante OVANIE</span>
            </div>
        </div>
        <button type="button" class="ovai-panel__close" id="ovaiClose" aria-label="Fermer l’assistant">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"></path></svg>
        </button>
    </header>

    <div class="ovai-panel__body" id="ovaiBody">
        <div class="ovai-resume-choice" id="ovaiResumeChoice" hidden>
            <p>Vous avez déjà une conversation avec N’Nan.</p>
            <p class="ovai-resume-choice__preview" id="ovaiResumePreview"></p>
            <div class="ovai-resume-choice__actions">
                <button type="button" class="ovai-btn ovai-btn--primary" id="ovaiResumeContinue">Reprendre la conversation</button>
                <button type="button" class="ovai-btn ovai-btn--ghost" id="ovaiResumeNew">Nouvelle conversation</button>
            </div>
        </div>

        <div class="ovai-welcome" id="ovaiWelcome" hidden>
            <p>Bonjour {{ auth()->check() ? explode(' ', trim(auth()->user()->name))[0] : '' }}, je suis N’Nan. Comment puis-je vous aider aujourd’hui ?</p>

            <div class="ovai-suggestions" id="ovaiSuggestions">
                <button type="button" class="ovai-suggestion" data-ovai-suggestion="Où en est ma commande ?">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7l9-4 9 4-9 4-9-4z"></path><path d="M3 7v10l9 4 9-4V7"></path><path d="M12 11v10"></path></svg>
                    Suivre ma commande
                </button>
                <button type="button" class="ovai-suggestion" data-ovai-suggestion="J’ai une question sur un produit du catalogue.">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4.3-4.3"></path></svg>
                    Question sur un produit
                </button>
                <button type="button" class="ovai-suggestion" data-ovai-suggestion="J’ai un souci avec un paiement.">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"></rect><path d="M2 10h20"></path></svg>
                    Problème de paiement
                </button>
                <button type="button" class="ovai-suggestion" data-ovai-suggestion="Je souhaite parler à un conseiller humain.">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    Parler à un humain
                </button>
            </div>
        </div>

        <div class="ovai-messages" id="ovaiMessages"></div>
    </div>

    <div class="ovai-callback" id="ovaiCallback" hidden>
        <div class="ovai-callback__grid">
            <input type="tel" id="ovaiCallbackPhone" placeholder="Téléphone à rappeler">
            <input type="datetime-local" id="ovaiCallbackDate">
        </div>
        <input type="text" id="ovaiCallbackReason" placeholder="Motif du rappel (facultatif)">
        <div class="ovai-callback__actions">
            <button type="button" class="ovai-btn ovai-btn--ghost" id="ovaiCallbackCancel">Annuler</button>
            <button type="button" class="ovai-btn ovai-btn--primary" id="ovaiCallbackSubmit">Enregistrer</button>
        </div>
    </div>

    <footer class="ovai-panel__foot">
        <p class="ovai-panel__status" id="ovaiStatus" hidden></p>
        <div class="ovai-composer">
            <button type="button" class="ovai-composer__extra" id="ovaiCallbackToggle" aria-label="Demander un rappel">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6 19.8 19.8 0 0 1-3.1-8.7A2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .3 2 .7 2.9a2 2 0 0 1-.4 2.1L8 10a16 16 0 0 0 6 6l1.3-1.4a2 2 0 0 1 2.1-.4c.9.4 1.9.6 2.9.7a2 2 0 0 1 1.7 2.1z"></path></svg>
            </button>
            <textarea id="ovaiInput" rows="1" placeholder="Posez votre question à N’Nan…"></textarea>
            <button type="button" class="ovai-composer__send" id="ovaiSend" aria-label="Envoyer">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M22 2 11 13"></path><path d="M22 2 15 22l-4-9-9-4 20-7z"></path></svg>
            </button>
        </div>
    </footer>
</aside>
