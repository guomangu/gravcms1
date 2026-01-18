---
title: Mot de passe oublié
template: skn-forgot
body_classes: skn-page--forgot
cache_enable: false
cache_control: 'private, no-cache, must-revalidate'
login_redirect_here: false

form:
    name: forgot-password
    action: /forgot
    
    fields:
        email:
            type: email
            label: Email
            placeholder: votre@email.com
            autocomplete: email
            validate:
                required: true
    
    buttons:
        - type: submit
          value: Envoyer le lien de réinitialisation
    
    process:
        reset_password: true
        message: "Si un compte existe avec cette adresse email, vous recevrez un lien de réinitialisation."
---
