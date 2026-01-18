---
title: Connexion
template: skn-login
body_classes: skn-page--login
cache_enable: false
login_redirect_here: false

form:
    name: login
    action: /login
    
    fields:
        username:
            type: text
            label: Nom d'utilisateur
            placeholder: votre.pseudo
            autofocus: true
            autocomplete: username
            validate:
                required: true
        
        password:
            type: password
            label: Mot de passe
            placeholder: ••••••••
            autocomplete: current-password
            validate:
                required: true
    
    buttons:
        - type: submit
          value: Se connecter
          task: login.login
---
