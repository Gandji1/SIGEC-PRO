name: Bug Report
description: Rapporter un bug dans SIGEC
title: "[BUG] "
labels: ["bug"]
assignees: []
body:
  - type: markdown
    attributes:
      value: |
        Merci de rapporter ce bug! Remplissez le formulaire ci-dessous pour aider.

  - type: input
    id: version
    attributes:
      label: Version SIGEC
      description: Quelle version utilisez-vous?
      placeholder: "1.0.0-beta.1"
    validations:
      required: true

  - type: dropdown
    id: component
    attributes:
      label: Composant affecté
      options:
        - Backend (Laravel)
        - Frontend (React)
        - Database
        - Infrastructure
        - Documentation
        - Autre
    validations:
      required: true

  - type: textarea
    id: description
    attributes:
      label: Description du bug
      description: Décrivez le problème clairement
      placeholder: "Quand j'essaie de..., il se passe..."
    validations:
      required: true

  - type: textarea
    id: reproduction
    attributes:
      label: Étapes de reproduction
      description: Étapes pour reproduire le problème
      placeholder: |
        1. Aller à...
        2. Cliquer sur...
        3. Observer...
    validations:
      required: true

  - type: textarea
    id: expected
    attributes:
      label: Comportement attendu
      description: Qu'est-ce qui devrait se passer?
      placeholder: "Le système devrait..."
    validations:
      required: true

  - type: textarea
    id: actual
    attributes:
      label: Comportement observé
      description: Qu'est-ce qui se passe réellement?
      placeholder: "Au lieu de cela, il..."
    validations:
      required: true

  - type: textarea
    id: logs
    attributes:
      label: Logs/Erreurs
      description: Copiez-collez les messages d'erreur pertinents
      render: bash

  - type: textarea
    id: screenshots
    attributes:
      label: Screenshots
      description: Ajouter des screenshots si utile

  - type: textarea
    id: environment
    attributes:
      label: Environnement
      description: Décrivez votre environnement
      placeholder: |
        - OS: Windows 11 / macOS / Ubuntu 22.04
        - Docker: 24.0.0
        - Browser: Chrome 120
        - Version SIGEC: 1.0.0-beta.1

  - type: dropdown
    id: severity
    attributes:
      label: Sévérité
      options:
        - "🔴 Critical (App non utilisable)"
        - "🟠 High (Feature ne fonctionne pas)"
        - "🟡 Medium (Feature partiellement)"
        - "🟢 Low (Minor cosmetic issue)"
    validations:
      required: true

  - type: checkboxes
    id: checks
    attributes:
      label: Vérifications
      options:
        - label: J'ai cherché les issues existantes
          required: true
        - label: C'est un bug, pas une question
          required: true
        - label: Je suis prêt à aider à la correction
          required: false
