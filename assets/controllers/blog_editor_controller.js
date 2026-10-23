/* stimulusFetch: 'lazy' */
import { Controller } from '@hotwired/stimulus';
import { Editor, Node, mergeAttributes } from '@tiptap/core';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import Swal from 'sweetalert2';

// Encart "Astuce" : un <aside> contenant des paragraphes
const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'paragraph+',
    defining: true,

    parseHTML() {
        return [{ tag: 'aside' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['aside', mergeAttributes(HTMLAttributes), 0];
    },

    addCommands() {
        return {
            toggleCallout: () => ({ commands }) => commands.toggleWrap(this.name),
        };
    },
});

/*
 * Éditeur riche des articles du blog. Le HTML est recopié dans un textarea caché
 * envoyé avec le formulaire ; le serveur le nettoie avant enregistrement.
 */
export default class extends Controller {
    static targets = ['editor', 'input', 'toolbar', 'file', 'status'];
    static values = { uploadUrl: String };

    connect() {
        this.editor = new Editor({
            element: this.editorTarget,
            extensions: [
                StarterKit.configure({
                    heading: { levels: [2, 3] },
                    // target/rel sont posés côté serveur, uniquement sur les liens externes
                    link: { openOnClick: false, autolink: true, defaultProtocol: 'https', HTMLAttributes: { target: null, rel: null } },
                }),
                Image,
                Callout,
                Placeholder.configure({ placeholder: 'Commencez à écrire votre article…' }),
            ],
            content: this.inputTarget.value,
            editorProps: {
                attributes: { class: 'focus:outline-none' },
            },
            onUpdate: () => this.sync(),
            onTransaction: () => this.refreshToolbar(),
        });

        this.sync();
        this.refreshToolbar();
    }

    disconnect() {
        this.editor?.destroy();
    }

    sync() {
        this.inputTarget.value = this.editor.isEmpty ? '' : this.editor.getHTML();
    }

    run(event) {
        const command = event.currentTarget.dataset.command;
        const chain = this.editor.chain().focus();

        switch (command) {
            case 'h2': chain.toggleHeading({ level: 2 }).run(); break;
            case 'h3': chain.toggleHeading({ level: 3 }).run(); break;
            case 'bold': chain.toggleBold().run(); break;
            case 'italic': chain.toggleItalic().run(); break;
            case 'underline': chain.toggleUnderline().run(); break;
            case 'bulletList': chain.toggleBulletList().run(); break;
            case 'orderedList': chain.toggleOrderedList().run(); break;
            case 'blockquote': chain.toggleBlockquote().run(); break;
            case 'callout': chain.toggleCallout().run(); break;
            case 'codeBlock': chain.toggleCodeBlock().run(); break;
            case 'hr': chain.setHorizontalRule().run(); break;
            case 'undo': chain.undo().run(); break;
            case 'redo': chain.redo().run(); break;
            case 'link': this.editLink(); break;
            case 'image': this.fileTarget.click(); break;
        }
    }

    async editLink() {
        const previous = this.editor.getAttributes('link').href || '';
        const { value: url, isDismissed } = await Swal.fire({
            title: 'Lien',
            input: 'text',
            inputValue: previous,
            inputPlaceholder: 'https://… ou /contact',
            showCancelButton: true,
            confirmButtonText: 'Valider',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#3f6b56',
            footer: previous ? 'Laissez vide pour retirer le lien.' : '',
        });

        if (isDismissed) return;

        const chain = this.editor.chain().focus().extendMarkRange('link');
        if (!url) {
            chain.unsetLink().run();
        } else {
            chain.setLink({ href: url }).run();
        }
    }

    async upload() {
        const file = this.fileTarget.files[0];
        if (!file) return;

        const { value: alt, isDismissed } = await Swal.fire({
            title: 'Texte alternatif',
            text: 'Décrivez l\'image en une phrase (accessibilité et référencement).',
            input: 'text',
            showCancelButton: true,
            confirmButtonText: 'Insérer',
            cancelButtonText: 'Annuler',
            confirmButtonColor: '#3f6b56',
        });

        if (isDismissed) {
            this.fileTarget.value = '';
            return;
        }

        this.setStatus('Envoi de l\'image…');
        const data = new FormData();
        data.append('image', file);

        try {
            const response = await fetch(this.uploadUrlValue, { method: 'POST', body: data, credentials: 'same-origin' });
            const json = await response.json();
            if (!response.ok) throw new Error(json.error || 'Envoi impossible.');

            this.editor.chain().focus().setImage({ src: json.url, alt: alt || '' }).run();
            this.setStatus('');
        } catch (error) {
            this.setStatus('');
            Swal.fire({ icon: 'error', title: 'Image non envoyée', text: error.message, confirmButtonColor: '#3f6b56' });
        } finally {
            this.fileTarget.value = '';
        }
    }

    setStatus(text) {
        if (this.hasStatusTarget) this.statusTarget.textContent = text;
    }

    refreshToolbar() {
        if (!this.editor) return;

        const checks = {
            h2: () => this.editor.isActive('heading', { level: 2 }),
            h3: () => this.editor.isActive('heading', { level: 3 }),
            bold: () => this.editor.isActive('bold'),
            italic: () => this.editor.isActive('italic'),
            underline: () => this.editor.isActive('underline'),
            link: () => this.editor.isActive('link'),
            bulletList: () => this.editor.isActive('bulletList'),
            orderedList: () => this.editor.isActive('orderedList'),
            blockquote: () => this.editor.isActive('blockquote'),
            callout: () => this.editor.isActive('callout'),
            codeBlock: () => this.editor.isActive('codeBlock'),
        };

        this.toolbarTarget.querySelectorAll('[data-command]').forEach(button => {
            const check = checks[button.dataset.command];
            const active = check ? check() : false;
            button.classList.toggle('bg-sage-dark/10', active);
            button.classList.toggle('text-sage-dark', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }
}
