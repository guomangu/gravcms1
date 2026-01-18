/**
 * SKN Auth - Gestion des formulaires d'authentification
 * Détection des erreurs et feedback utilisateur
 */
(function() {
    'use strict';

    // Messages d'erreur traduits
    const ERROR_MESSAGES = {
        'PLUGIN_LOGIN.LOGIN_FAILED': 'Identifiants incorrects. Vérifiez votre nom d\'utilisateur et mot de passe.',
        'PLUGIN_LOGIN.USER_ACCOUNT_DISABLED': 'Ce compte a été désactivé.',
        'PLUGIN_LOGIN.USERNAME_NOT_VALID': 'Nom d\'utilisateur invalide (3-32 caractères: lettres minuscules, chiffres, tirets, points)',
        'PLUGIN_LOGIN.PASSWORD_VALIDATION_MESSAGE': 'Mot de passe invalide (minimum 8 caractères)',
        'PLUGIN_LOGIN.EMAIL_VALIDATION_MESSAGE': 'Adresse email invalide',
        'PLUGIN_LOGIN.USER_EXISTS': 'Ce nom d\'utilisateur existe déjà',
        'PLUGIN_LOGIN.EMAIL_EXISTS': 'Cette adresse email est déjà utilisée',
        'default': 'Une erreur est survenue. Veuillez réessayer.'
    };

    // Vérifie les erreurs dans l'URL
    function checkUrlErrors() {
        const params = new URLSearchParams(window.location.search);
        const errors = [];
        
        if (params.get('login') === 'failed') {
            errors.push(ERROR_MESSAGES['PLUGIN_LOGIN.LOGIN_FAILED']);
        }
        
        if (params.get('error')) {
            const errorKey = params.get('error');
            errors.push(ERROR_MESSAGES[errorKey] || ERROR_MESSAGES['default']);
        }
        
        return errors;
    }

    // Vérifie les messages flash dans le DOM
    function checkFlashMessages() {
        const flashErrors = document.querySelectorAll('.skn-flash--error');
        const errors = [];
        
        flashErrors.forEach(flash => {
            const text = flash.querySelector('.skn-flash__text');
            if (text) {
                errors.push(text.textContent.trim());
            }
        });
        
        return errors;
    }

    // Affiche les erreurs dans la zone dédiée
    function showErrors(containerSelector, errors) {
        const container = document.querySelector(containerSelector);
        if (!container || errors.length === 0) return;
        
        container.innerHTML = '<i class="la la-exclamation-circle"></i> ' + errors.join('<br>');
        container.classList.add('skn-login-errors--show', 'skn-register-errors--show');
        
        // Scroll vers les erreurs
        container.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Initialisation au chargement
    document.addEventListener('DOMContentLoaded', function() {
        // Détection automatique des erreurs
        const urlErrors = checkUrlErrors();
        const flashErrors = checkFlashMessages();
        const allErrors = [...urlErrors, ...flashErrors];
        
        // Affichage des erreurs si présentes
        if (allErrors.length > 0) {
            showErrors('#login-errors', allErrors);
            showErrors('#register-errors', allErrors);
            showErrors('#forgot-errors', allErrors);
            
            console.warn('[SKN Auth] Erreurs détectées:', allErrors);
        }
        
        // Log d'état
        console.log('[SKN Auth] Module initialisé');
        console.log('[SKN Auth] URL:', window.location.href);
        console.log('[SKN Auth] Params:', Object.fromEntries(new URLSearchParams(window.location.search)));
        
        // Nettoyage de l'URL après affichage des erreurs (optionnel)
        if (urlErrors.length > 0 && window.history.replaceState) {
            const cleanUrl = window.location.pathname;
            // Décommenter pour activer le nettoyage:
            // window.history.replaceState({}, document.title, cleanUrl);
        }
    });

    // Validation en temps réel des champs
    document.addEventListener('input', function(e) {
        if (e.target.matches('input[name="username"], input[name="data[username]"]')) {
            validateUsername(e.target);
        }
        if (e.target.matches('input[name="email"], input[name="data[email]"]')) {
            validateEmail(e.target);
        }
        if (e.target.matches('input[name="password1"], input[name="data[password1]"]')) {
            validatePassword(e.target);
        }
        if (e.target.matches('input[name="password2"], input[name="data[password2]"]')) {
            validatePasswordMatch(e.target);
        }
    });

    function validateUsername(input) {
        const value = input.value;
        const pattern = /^[a-z0-9_\-\.]{3,32}$/;
        
        if (value && !pattern.test(value)) {
            input.classList.add('skn-field-error');
            showFieldHint(input, 'Lettres minuscules, chiffres, tirets, points (3-32 car.)');
        } else {
            input.classList.remove('skn-field-error');
            hideFieldHint(input);
        }
    }

    function validateEmail(input) {
        const value = input.value;
        const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        
        if (value && !pattern.test(value)) {
            input.classList.add('skn-field-error');
        } else {
            input.classList.remove('skn-field-error');
        }
    }

    function validatePassword(input) {
        const value = input.value;
        
        if (value && value.length < 8) {
            input.classList.add('skn-field-error');
            showFieldHint(input, 'Minimum 8 caractères');
        } else {
            input.classList.remove('skn-field-error');
            hideFieldHint(input);
        }
    }

    function validatePasswordMatch(input) {
        const password1 = document.querySelector('input[name="password1"], input[name="data[password1]"]');
        
        if (password1 && input.value && password1.value !== input.value) {
            input.classList.add('skn-field-error');
            showFieldHint(input, 'Les mots de passe ne correspondent pas');
        } else {
            input.classList.remove('skn-field-error');
            hideFieldHint(input);
        }
    }

    function showFieldHint(input, message) {
        let hint = input.parentElement.querySelector('.skn-field-hint');
        if (!hint) {
            hint = document.createElement('span');
            hint.className = 'skn-field-hint';
            input.parentElement.appendChild(hint);
        }
        hint.textContent = message;
        hint.classList.add('skn-field-hint--show');
    }

    function hideFieldHint(input) {
        const hint = input.parentElement.querySelector('.skn-field-hint');
        if (hint) {
            hint.classList.remove('skn-field-hint--show');
        }
    }
})();
