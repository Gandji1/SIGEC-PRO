name: Feature Request
description: Proposer une nouvelle fonctionnalité
title: "[FEATURE] "
labels: ["enhancement"]
body:
  - type: markdown
    attributes:
      value: |
        Merci pour cette suggestion! Complétez le formulaire.

  - type: textarea
    id: description
    attributes:
      label: Description
      description: Décrivez la nouvelle fonctionnalité souhaitée
      placeholder: "J'aimerais avoir..."
    validations:
      required: true

  - type: textarea
    id: motivation
    attributes:
      label: Motivation
      description: Pourquoi est-ce important?
      placeholder: "Cela aiderait à..."
    validations:
      required: true

  - type: textarea
    id: solution
    attributes:
      label: Solution proposée
      description: Comment devrais-ce être implémenté?
      placeholder: "On pourrait..."

  - type: textarea
    id: alternatives
    attributes:
      label: Alternatives envisagées
      description: Y a-t-il d'autres approches?
      placeholder: "On pourrait aussi..."

  - type: dropdown
    id: priority
    attributes:
      label: Priorité
      options:
        - "🔴 Critical"
        - "🟠 High"
        - "🟡 Medium"
        - "🟢 Low"

  - type: checkboxes
    id: checks
    attributes:
      label: Checklist
      options:
        - label: J'ai recherché les issues existantes
        - label: Je peux contribuer à cette feature
