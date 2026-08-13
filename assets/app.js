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

window.Swal = Swal;

import { createIcons, Wrench, Settings, Lightbulb, MapPin, Phone, Check, RefreshCw, ArrowRight, Star, User, MessageCircle, Receipt, Ban, Clock, ShieldCheck, Code, LayoutDashboard, ShoppingCart, Calendar, Store, Plus  } from 'lucide';

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
        }
    });
});