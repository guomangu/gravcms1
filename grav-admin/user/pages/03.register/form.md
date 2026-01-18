---
title: Créer un compte
template: skn-register
body_classes: skn-page--register
cache_enable: false
cache_control: 'private, no-cache, must-revalidate'
login_redirect_here: false

form:
    name: registration
    action: /register
    
    fields:
        fullname:
            type: text
            label: Nom complet
            placeholder: Jean Dupont
            autocomplete: name
            validate:
                required: true
        
        username:
            type: text
            label: Pseudo
            placeholder: jean.dupont
            autocomplete: username
            help: "3-32 caractères : lettres minuscules, chiffres, tirets, points"
            validate:
                required: true
                message: PLUGIN_LOGIN.USERNAME_NOT_VALID
                config-pattern@: system.username_regex
        
        email:
            type: email
            label: Email
            placeholder: jean@example.com
            autocomplete: email
            validate:
                required: true
                message: PLUGIN_LOGIN.EMAIL_VALIDATION_MESSAGE
        
        password1:
            type: password
            label: Mot de passe
            placeholder: Min. 8 caractères
            autocomplete: new-password
            help: "Minimum 8 caractères"
            validate:
                required: true
                message: PLUGIN_LOGIN.PASSWORD_VALIDATION_MESSAGE
                config-pattern@: system.pwd_regex
        
        password2:
            type: password
            label: Confirmer le mot de passe
            placeholder: Répétez le mot de passe
            autocomplete: new-password
            validate:
                required: true
                message: PLUGIN_LOGIN.PASSWORD_VALIDATION_MESSAGE
                config-pattern@: system.pwd_regex
    
    buttons:
        - type: submit
          value: Créer mon compte
    
    process:
        register_user: true
        message: PLUGIN_LOGIN.REGISTRATION_THANK_YOU
        reset: true
---
