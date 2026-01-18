---
title: Créer une Room
template: skn-create-room
body_classes: skn-page--create-room
cache_enable: false
process:
    twig: true

access:
    site.login: true

form:
    name: create-room
    id: create-room-form
    
    fields:
        name:
            type: text
            label: Nom de la room
            placeholder: Ma super room
            autofocus: true
            classes: form-input
            validate:
                required: true
                min: 3
                max: 50
                message: "Le nom doit contenir entre 3 et 50 caractères"
        
        description:
            type: textarea
            label: Description
            placeholder: Décrivez votre room...
            rows: 3
            classes: form-input
        
        
        address_data:
            type: hidden
        
        location:
            type: address-autocomplete
            label: Localisation (optionnel)
            placeholder: Rechercher une adresse...
            classes: form-input
        

    
    buttons:
        submit:
            type: submit
            value: Créer la room
            classes: skn-btn skn-btn--primary skn-btn--block

    process:
        - create_room: true
    

---
