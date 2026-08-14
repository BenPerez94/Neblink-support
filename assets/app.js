// @ts-nocheck
import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * We recommend including the built version of this JavaScript file
 * (and its CSS file) in your base layout (base.html.twig).
 */

// any CSS you import will output into a single css file (app.css in this case)
// @ts-ignore
import './styles/app.css';
import Swal from 'sweetalert2';


import { createIcons, Wrench, Settings, Lightbulb, MapPin, Phone, Check, RefreshCw, ArrowRight, Star, User, MessageCircle, Receipt, Ban, Clock, ShieldCheck, Code, LayoutDashboard, ShoppingCart, Calendar, Store, Plus, Mail, LogOut, Eye, ExternalLink, Trash2, MoreVertical  } from 'lucide';

document.addEventListener('turbo:load', () => {
    createIcons({
        icons: {
            Wrench,
            Settings,
            Lightbulb,
            MapPin,
            Phone,
            Check,
            RefreshCw,
            ArrowRight,
            Star,
            User,
            MessageCircle,
            Receipt,
            Ban,
            Clock,
            ShieldCheck,
            Code,
            LayoutDashboard,
            ShoppingCart,
            Calendar,
            Store,
            Plus,
            Mail,
            LogOut,
            Eye,
            ExternalLink,
            Trash2,
            MoreVertical
        }
    });
});

function initNavScript() {
    const header = document.getElementById('site-header');
    const nav = document.getElementById('site-nav');
    const logoText = document.getElementById('nav-logo-text');
    const logoBox = document.getElementById('nav-logo-box');
    const navLinks = document.querySelectorAll('.nav-link');
    const contactBtn = document.getElementById('nav-contact-btn');
    const burgerBtn = document.getElementById('burger-btn');
    const hero = document.querySelector('.hero-section');
    const panel = document.getElementById('mobile-panel');
    const iconOpen = document.getElementById('icon-open');
    const iconClose = document.getElementById('icon-close');
    let menuOpen = false;

    if (burgerBtn && panel) {
        burgerBtn.addEventListener('click', () => {
            menuOpen = !menuOpen;
            panel.classList.toggle('opacity-0', !menuOpen);
            panel.classList.toggle('-translate-y-2', !menuOpen);
            panel.classList.toggle('pointer-events-none', !menuOpen);
            iconOpen.classList.toggle('hidden', menuOpen);
            iconClose.classList.toggle('hidden', !menuOpen);
            burgerBtn.setAttribute('aria-expanded', menuOpen);
        });
    }

    function updateNavStyle() {
        if (!hero) return;
        const heroBottom = hero.getBoundingClientRect().bottom;
        const isOverLightSection = heroBottom < 80;

        if (nav) {
            nav.classList.toggle('bg-white/70', isOverLightSection);
            nav.classList.toggle('border-slate-900/10', isOverLightSection);
            nav.classList.toggle('shadow-lg', isOverLightSection);
            nav.classList.toggle('shadow-slate-900/5', isOverLightSection);
            nav.classList.toggle('bg-white/15', !isOverLightSection);
            nav.classList.toggle('border-white/30', !isOverLightSection);
        }
        if (logoText) {
            logoText.classList.toggle('text-slate-800', isOverLightSection);
            logoText.classList.toggle('text-white', !isOverLightSection);
        }
        if (logoBox) {
            logoBox.classList.toggle('bg-sage-dark', isOverLightSection);
            logoBox.classList.toggle('text-white', isOverLightSection);
            logoBox.classList.toggle('bg-white', !isOverLightSection);
            logoBox.classList.toggle('text-sage-dark', !isOverLightSection);
        }
        navLinks.forEach(link => {
            link.classList.toggle('text-slate-600', isOverLightSection);
            link.classList.toggle('hover:text-slate-900', isOverLightSection);
            link.classList.toggle('text-white/80', !isOverLightSection);
            link.classList.toggle('hover:text-white', !isOverLightSection);
        });
        if (contactBtn) {
            contactBtn.classList.toggle('bg-sage-dark', isOverLightSection);
            contactBtn.classList.toggle('text-white', isOverLightSection);
            contactBtn.classList.toggle('bg-white', !isOverLightSection);
            contactBtn.classList.toggle('text-sage-dark', !isOverLightSection);
        }
        if (burgerBtn) {
            burgerBtn.classList.toggle('text-slate-800', isOverLightSection);
            burgerBtn.classList.toggle('text-white', !isOverLightSection);
        }
    }

    

    window.addEventListener('scroll', updateNavStyle);
    updateNavStyle();
}
function initScrollAnimations() {
    const elements = document.querySelectorAll('[data-animate]');
    if (!elements.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.15 });

    elements.forEach(el => observer.observe(el));
}
function initFlashToasts() {
    document.querySelectorAll('[data-flash-message]').forEach(el => {
        const type = el.dataset.flashType;
        const isSuccess = type !== 'error';

        Swal.fire({
            toast: true,
            position: 'bottom-end',
            icon: isSuccess ? 'success' : 'error',
            title: el.dataset.flashMessage,
            showConfirmButton: false,
            timer: 6000,
            timerProgressBar: true,
            background: '#ffffff',
            color: '#1f2b26',
            iconColor: isSuccess ? '#3f6b56' : '#c86b6b',
            customClass: {
                popup: 'neblink-toast',
            },
        });
    });
}
function initAdminMessagesPanel() {
    const items = document.querySelectorAll('[data-message-item]');
    if (!items.length) return;

    items.forEach(item => {
        item.addEventListener('click', () => {
            // --- Comportement desktop (colonnes) : inchangé ---
            items.forEach(i => {
                i.classList.remove('is-active', 'bg-teal-500/10', 'border-l-teal-500', 'shadow-[inset_0_0_0_1px_rgba(20,184,166,0.2)]', 'bg-sage-dark/5', 'border-l-transparent', 'border-l-sage-dark');
                if (i.dataset.statut === 'nouveau') {
                    i.classList.add('border-l-sage-dark', 'bg-sage-dark/5');
                } else {
                    i.classList.add('border-l-transparent');
                }
            });

            item.classList.remove('border-l-transparent', 'bg-sage-dark/5', 'border-l-sage-dark');
            item.classList.add('is-active', 'bg-teal-500/10', 'border-l-teal-500', 'shadow-[inset_0_0_0_1px_rgba(20,184,166,0.2)]');

            document.querySelectorAll('[data-message-detail]').forEach(detail => {
                detail.classList.add('hidden');
            });
            const target = document.getElementById(item.dataset.target);
            if (target) target.classList.remove('hidden');

            // --- Comportement mobile (accordéon) ---
            const mobileTarget = document.getElementById(item.dataset.mobileTarget);
            if (mobileTarget) {
                const wasOpen = !mobileTarget.classList.contains('hidden');

                document.querySelectorAll('[data-message-detail-mobile]').forEach(d => {
                    d.classList.add('hidden');
                });

                if (!wasOpen) {
                    mobileTarget.classList.remove('hidden');

                    requestAnimationFrame(() => {
                        item.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                }
            }
        });
    });
}

function initDeleteConfirm() {
    document.querySelectorAll('[data-confirm-delete]').forEach(form => {
        form.addEventListener('submit', (e) => {
            if (form.dataset.confirmed) return;
            e.preventDefault();

            Swal.fire({
                title: 'Supprimer ce message ?',
                text: 'Cette action est définitive.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Supprimer',
                cancelButtonText: 'Annuler',
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#94a3b8',
            }).then((result) => {
                if (result.isConfirmed) {
                    form.dataset.confirmed = 'true';
                    form.submit();
                }
            });
        });
    });
}

function initAdminMoreMenu() {
    const btn = document.getElementById('admin-more-btn');
    const panel = document.getElementById('admin-more-panel');
    if (!btn || !panel) return;

    let open = false;

    const positionPanel = () => {
        const rect = btn.getBoundingClientRect();
        panel.style.top = `${rect.bottom + 8}px`;
        panel.style.left = `${Math.max(8, rect.right - 192)}px`;
    };

    const togglePanel = (show) => {
        open = show;
        if (show) positionPanel();
        panel.classList.toggle('opacity-0', !show);
        panel.classList.toggle('-translate-y-2', !show);
        panel.classList.toggle('pointer-events-none', !show);
        btn.setAttribute('aria-expanded', show);
    };

    btn.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePanel(!open);
    });

    document.addEventListener('click', (e) => {
        if (open && !panel.contains(e.target) && e.target !== btn) {
            togglePanel(false);
        }
    });

    window.addEventListener('scroll', () => {
        if (open) togglePanel(false);
    });
}



document.addEventListener('turbo:load', initNavScript);
document.addEventListener('turbo:load', initScrollAnimations);
document.addEventListener('turbo:load', initFlashToasts);
document.addEventListener('turbo:load', initAdminMessagesPanel);
document.addEventListener('turbo:load', initDeleteConfirm);
document.addEventListener('turbo:load', initAdminMoreMenu);
document.addEventListener('turbo:before-cache', () => {
    document.querySelectorAll('[data-flash-message]').forEach(el => el.remove());
});

