import { Controller } from '@hotwired/stimulus';

const SCRIPT_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
let chargement = null;

// Charge le script Cloudflare une seule fois, même après plusieurs navigations Turbo
function chargerTurnstile() {
    if (window.turnstile) return Promise.resolve(window.turnstile);
    if (!chargement) {
        chargement = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = SCRIPT_URL;
            script.async = true;
            script.onload = () => resolve(window.turnstile);
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }
    return chargement;
}

/*
 * Captcha Cloudflare Turnstile du formulaire de contact. Le widget ajoute au formulaire
 * un champ caché « cf-turnstile-response », vérifié côté serveur.
 */
export default class extends Controller {
    static values = { sitekey: String };

    async connect() {
        try {
            const turnstile = await chargerTurnstile();
            if (!this.element.isConnected) return;

            this.widgetId = turnstile.render(this.element, {
                sitekey: this.sitekeyValue,
                language: 'fr',
                theme: 'light',
            });
        } catch {
            this.element.textContent = 'La vérification anti-robot n\'a pas pu se charger. Rechargez la page.';
        }
    }

    disconnect() {
        if (this.widgetId !== undefined && window.turnstile) {
            window.turnstile.remove(this.widgetId);
        }
    }
}
