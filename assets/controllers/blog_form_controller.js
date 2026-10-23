import { Controller } from '@hotwired/stimulus';

/*
 * Petits plus du formulaire d'article : aperçu du slug, compteurs SEO et aperçu de la couverture.
 */
export default class extends Controller {
    static targets = ['titre', 'slug', 'metaTitre', 'metaTitreCount', 'metaDescription', 'metaDescriptionCount', 'coverPreview', 'coverPlaceholder'];

    connect() {
        this.updateSlugPlaceholder();
        this.updateCounters();
    }

    updateSlugPlaceholder() {
        const slug = this.titreTarget.value
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        this.slugTarget.placeholder = slug || 'genere-depuis-le-titre';
    }

    updateCounters() {
        this.count(this.metaTitreTarget, this.metaTitreCountTarget, 60);
        this.count(this.metaDescriptionTarget, this.metaDescriptionCountTarget, 155);
    }

    count(input, output, ideal) {
        const length = input.value.length;
        output.textContent = length ? `${length} / ${ideal}` : '';
        output.classList.toggle('text-amber-600', length > ideal);
    }

    previewCover(event) {
        const file = event.target.files[0];
        if (!file) return;

        this.coverPreviewTarget.src = URL.createObjectURL(file);
        this.coverPreviewTarget.classList.remove('hidden');
        this.coverPlaceholderTarget.classList.add('hidden');
    }
}
