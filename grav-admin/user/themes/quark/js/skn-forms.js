/**
 * SKN Forms - Validation et UX améliorée
 */
(function() {
    'use strict';

    // Configuration des messages d'erreur
    const messages = {
        required: 'Ce champ est requis',
        email: 'Veuillez entrer une adresse email valide',
        minlength: 'Ce champ doit contenir au moins {min} caractères',
        maxlength: 'Ce champ ne doit pas dépasser {max} caractères',
        pattern: 'Le format n\'est pas valide',
        passwordMatch: 'Les mots de passe ne correspondent pas',
        passwordStrength: 'Le mot de passe doit contenir au moins 8 caractères',
        username: 'Le pseudo doit contenir 3-32 caractères (lettres minuscules, chiffres, tirets, points)',
        loginFailed: 'Identifiants incorrects. Vérifiez votre nom d\'utilisateur et mot de passe.'
    };

    // Initialisation au chargement du DOM
    document.addEventListener('DOMContentLoaded', function() {
        initFormValidation();
        initPasswordToggle();
        initFormFeedback();
        initAutoSlug();
    });

    /**
     * Initialise la validation des formulaires
     */
    function initFormValidation() {
        const forms = document.querySelectorAll('form.skn-form, .skn-form-wrapper form');
        
        forms.forEach(form => {
            // Validation en temps réel
            form.querySelectorAll('input, textarea, select').forEach(field => {
                field.addEventListener('blur', () => validateField(field));
                field.addEventListener('input', () => clearError(field));
            });

            // Validation à la soumission
            form.addEventListener('submit', function(e) {
                let isValid = true;
                
                form.querySelectorAll('input, textarea, select').forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                    }
                });

                // Vérification spéciale pour les mots de passe
                const pwd1 = form.querySelector('input[name="password1"], input[name*="password1"]');
                const pwd2 = form.querySelector('input[name="password2"], input[name*="password2"]');
                if (pwd1 && pwd2 && pwd1.value !== pwd2.value) {
                    showError(pwd2, messages.passwordMatch);
                    isValid = false;
                }

                if (!isValid) {
                    e.preventDefault();
                    showNotification('Veuillez corriger les erreurs du formulaire', 'error');
                    
                    // Scroll vers la première erreur
                    const firstError = form.querySelector('.skn-field-error');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                } else {
                    // Désactiver le bouton pour éviter les doubles soumissions
                    const submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<i class="la la-spinner la-spin"></i> Chargement...';
                    }
                }
            });
        });
    }

    /**
     * Valide un champ
     */
    function validateField(field) {
        clearError(field);
        
        const value = field.value.trim();
        const type = field.type;
        const name = field.name;
        
        // Champ requis
        if (field.hasAttribute('required') && !value) {
            showError(field, messages.required);
            return false;
        }

        if (!value) return true; // Les champs vides non requis sont valides

        // Validation email
        if (type === 'email') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showError(field, messages.email);
                return false;
            }
        }

        // Longueur minimale
        const minLength = field.getAttribute('minlength');
        if (minLength && value.length < parseInt(minLength)) {
            showError(field, messages.minlength.replace('{min}', minLength));
            return false;
        }

        // Longueur maximale
        const maxLength = field.getAttribute('maxlength');
        if (maxLength && value.length > parseInt(maxLength)) {
            showError(field, messages.maxlength.replace('{max}', maxLength));
            return false;
        }

        // Pattern personnalisé
        const pattern = field.getAttribute('pattern');
        if (pattern) {
            const regex = new RegExp('^' + pattern + '$');
            if (!regex.test(value)) {
                // Message spécifique pour le username
                if (name.includes('username')) {
                    showError(field, messages.username);
                } else {
                    showError(field, messages.pattern);
                }
                return false;
            }
        }

        // Validation mot de passe
        if (type === 'password' && name.includes('password1')) {
            if (value.length < 8) {
                showError(field, messages.passwordStrength);
                return false;
            }
        }

        // Marquer comme valide
        field.classList.add('skn-field-valid');
        return true;
    }

    /**
     * Affiche une erreur sous un champ
     */
    function showError(field, message) {
        field.classList.add('skn-field-error');
        field.classList.remove('skn-field-valid');
        
        // Supprimer l'ancien message d'erreur
        const existingError = field.parentNode.querySelector('.skn-error-message');
        if (existingError) existingError.remove();
        
        // Créer le nouveau message
        const errorDiv = document.createElement('div');
        errorDiv.className = 'skn-error-message';
        errorDiv.innerHTML = '<i class="la la-exclamation-circle"></i> ' + message;
        
        field.parentNode.appendChild(errorDiv);
    }

    /**
     * Supprime l'erreur d'un champ
     */
    function clearError(field) {
        field.classList.remove('skn-field-error');
        const errorMsg = field.parentNode.querySelector('.skn-error-message');
        if (errorMsg) errorMsg.remove();
    }

    /**
     * Affiche une notification
     */
    function showNotification(message, type = 'info') {
        // Supprimer les anciennes notifications
        document.querySelectorAll('.skn-notification').forEach(n => n.remove());
        
        const notification = document.createElement('div');
        notification.className = 'skn-notification skn-notification--' + type;
        
        const icons = {
            error: 'la-exclamation-circle',
            success: 'la-check-circle',
            warning: 'la-exclamation-triangle',
            info: 'la-info-circle'
        };
        
        notification.innerHTML = `
            <i class="la ${icons[type] || icons.info}"></i>
            <span>${message}</span>
            <button class="skn-notification__close" onclick="this.parentElement.remove()">
                <i class="la la-times"></i>
            </button>
        `;
        
        document.body.appendChild(notification);
        
        // Animation d'entrée
        setTimeout(() => notification.classList.add('skn-notification--show'), 10);
        
        // Auto-suppression après 5 secondes
        setTimeout(() => {
            notification.classList.remove('skn-notification--show');
            setTimeout(() => notification.remove(), 300);
        }, 5000);
    }

    /**
     * Toggle visibilité mot de passe
     */
    function initPasswordToggle() {
        document.querySelectorAll('input[type="password"]').forEach(field => {
            const wrapper = field.parentNode;
            if (wrapper.querySelector('.skn-password-toggle')) return;
            
            const toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'skn-password-toggle';
            toggle.innerHTML = '<i class="la la-eye"></i>';
            toggle.title = 'Afficher le mot de passe';
            
            toggle.addEventListener('click', function() {
                if (field.type === 'password') {
                    field.type = 'text';
                    toggle.innerHTML = '<i class="la la-eye-slash"></i>';
                    toggle.title = 'Masquer le mot de passe';
                } else {
                    field.type = 'password';
                    toggle.innerHTML = '<i class="la la-eye"></i>';
                    toggle.title = 'Afficher le mot de passe';
                }
            });
            
            wrapper.style.position = 'relative';
            wrapper.appendChild(toggle);
        });
    }

    /**
     * Feedback visuel pour les formulaires
     */
    function initFormFeedback() {
        // Indicateur de chargement pour les liens importants
        document.querySelectorAll('a.skn-btn--primary').forEach(link => {
            link.addEventListener('click', function() {
                if (!this.classList.contains('skn-no-loading')) {
                    this.classList.add('skn-btn--loading');
                }
            });
        });
    }

    /**
     * Auto-génération de slug
     */
    function initAutoSlug() {
        const nameField = document.querySelector('input[name="name"]');
        const slugField = document.querySelector('input[name="slug"]');
        
        if (nameField && slugField) {
            nameField.addEventListener('input', function() {
                if (!slugField.dataset.manual) {
                    slugField.value = slugify(this.value);
                }
            });
            
            slugField.addEventListener('input', function() {
                this.dataset.manual = 'true';
            });
        }
    }

    /**
     * Convertit une chaîne en slug
     */
    function slugify(text) {
        return text
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim()
            .replace(/\s+/g, '-')
            .replace(/[^\w\-]+/g, '')
            .replace(/\-\-+/g, '-')
            .replace(/^-+/, '')
            .replace(/-+$/, '');
    }

    // Exposer certaines fonctions globalement
    window.SKN = {
        showNotification: showNotification,
        validateField: validateField
    };
})();
