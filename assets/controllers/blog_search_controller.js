import { Controller } from '@hotwired/stimulus';

/*
 * Recherche en direct du blog : pendant la frappe, seul le cadre des résultats
 * (<turbo-frame>) est rechargé. Sans JavaScript, le formulaire reste un GET classique.
 */
export default class extends Controller {
    static targets = ['input', 'frame', 'pill'];
    static values = {
        url: String,
        delay: { type: Number, default: 300 },
    };

    connect() {
        this.derniereUrl = window.location.pathname + window.location.search;
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    queue() {
        clearTimeout(this.timer);

        // Une seule lettre ne lance pas de recherche, on attend la suivante
        if (this.inputTarget.value.trim().length === 1) return;

        this.timer = setTimeout(() => this.search(), this.delayValue);
    }

    submit(event) {
        event.preventDefault();
        clearTimeout(this.timer);
        this.search();
    }

    clear() {
        this.inputTarget.value = '';
        this.inputTarget.focus();
        this.search();
    }

    search() {
        const terme = this.inputTarget.value.trim();
        const url = this.urlAvecRecherche(this.urlValue, terme);
        if (url === this.derniereUrl) return;

        this.derniereUrl = url;
        this.frameTarget.src = url;
        history.replaceState(history.state, '', url);

        // Les pastilles de catégorie gardent la recherche en cours
        this.pillTargets.forEach(pill => {
            pill.href = this.urlAvecRecherche(pill.dataset.baseUrl, terme);
        });
    }

    // Les résultats rechargés apparaissent en fondu, sans l'animation d'entrée au scroll
    beforeRender(event) {
        event.detail.newFrame.querySelectorAll('[data-animate]').forEach(el => el.removeAttribute('data-animate'));
        this.frameTarget.classList.remove('blog-fade-in');
        void this.frameTarget.offsetWidth;
        this.frameTarget.classList.add('blog-fade-in');
    }

    urlAvecRecherche(base, terme) {
        const url = new URL(base, window.location.origin);
        if (terme.length >= 2) url.searchParams.set('q', terme);
        return url.pathname + url.search;
    }
}
