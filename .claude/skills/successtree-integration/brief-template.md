# Gabarit de brief visuel SuccessTree

Copiez le bloc YAML ci-dessous, remplissez-le avec l'utilisateur (questionnaire du `SKILL.md`), puis convertissez-le
en payload JSON (SPEC §4) avec les règles de traduction de la phase 5 du skill.

Le bloc est **pré-rempli avec le besoin de référence** : 9 points principaux formant un cœur + 1 point central posé
sur le départ qui, une fois déployé, pousse les autres. Style « constellation map » : hubs lavande, branches en
points, fond bleu-violet.

```yaml
# ─── Identité ────────────────────────────────────────────────────────────────
tree:
  slug: coeur                      # identifiant d'URL (?tree=coeur), [a-z0-9-]
  name: "Cœur de l'entreprise"
  description: "Neuf domaines de développement autour d'une vision centrale."
  language: fr                     # langue des libellés (et i18n du front si besoin)

# ─── Forme globale ───────────────────────────────────────────────────────────
layout:
  shape: heart                     # heart | radial | ring | free | coords (coordonnées exactes fournies)
  scale_R: 420                     # rayon monde du preset (unités monde ; y vers le bas)
  hubs_count: 9                    # nombre de hubs principaux (heart = 9 conseillé)
  central_hub:
    enabled: true                  # 10e hub posé juste au-dessus de l'origin
    name: "Vision"
    offset_y: -72                  # décalage au-dessus de l'origin (négatif = vers le haut)
    push: 1.6                      # settings.expandPush : force de poussée des autres hubs au déploiement
    children_layout: circle        # ses enfants forment un cercle autour de lui
    children_radius: 72
  branches:
    per_hub: [2, 3]                # min / max de branches par hub
    depth: [2, 4]                  # min / max de points par branche
    direction: outward             # outward (repoussées depuis le centre) | fan | down (colonnes)
    spacing: [68, 46]              # distance hub→1er point, puis entre points (unités monde)
  extra_links:                     # ponts entre branches (st_link), illimités
    - { from: "Checklist qualité", to: "Automatisations", type: path }
    - { ring_between_hubs: true, type: visual }   # contour du cœur

# ─── Ambiance visuelle ───────────────────────────────────────────────────────
theme:
  references: ["constellation map", "Sphérier FFX"]   # jeux / images de référence
  background: "#2e2f6b"            # fond bleu-violet
  accent: "#c8b6ff"                # lavande : complétés, halos
  line: "rgba(255,255,255,.55)"
  font: "system-ui, sans-serif"
  hub_radius: 34                   # settings.hubRadius
  node_radius: 9                   # settings.nodeRadius
  labels: hover                    # settings.showLabels : hover | always | never
  hub_colors: per_hub              # per_hub (une couleur par domaine) | uniform (accent partout)

# ─── Contenu (ordre = ordre des hubs du preset : pointe basse puis paires G/D) ─
hubs:
  - { name: "Marketing",    icon: globe,    color: "#ff7a59", branches: [["Persona défini", "Ligne éditoriale", "10 articles publiés"], ["Site vitrine en ligne", "SEO de base"]] }
  - { name: "Ventes",       icon: chart,    color: "#ffb547", branches: [["Offre formalisée", "Premier devis signé"], ["Pipeline CRM", "10 clients signés"]] }
  - { name: "Opérations",   icon: layers,   color: "#5ad1a5", branches: [["Process cartographiés", "Automatisations"], ["Checklist qualité"]] }
  - { name: "Intelligence", icon: database, color: "#7aa7ff", branches: [["Tableau de bord", "KPIs hebdomadaires"], ["Segmentation", "Prévisions"]] }
  - { name: "Clients",      icon: users,    color: "#ff5c8a", branches: [["Onboarding client", "NPS ≥ 40"], ["FAQ publique"]] }
  - { name: "Finances",     icon: gem,      color: "#f2d45c", branches: [["Budget prévisionnel", "3 mois de réserve"], ["Abonnement réglé"]] }
  - { name: "Équipe",       icon: flag,     color: "#b18cff", branches: [["Rôles clarifiés", "Rituels d'équipe"], ["Premier recrutement"]] }
  - { name: "Produit",      icon: bolt,     color: "#4fd6e8", branches: [["Problème validé", "MVP livré", "10 utilisateurs actifs"]] }
  - { name: "Santé",        icon: leaf,     color: "#7ee07a", branches: [["Routine de sommeil", "Sport 3×/semaine"]] }
central:
  name: "Vision"
  icon: crown
  color: "#c8b6ff"
  children: ["Mission écrite", "Valeurs partagées", "Positionnement clair", "Ambition chiffrée"]

# ─── Règles de progression ───────────────────────────────────────────────────
progression:
  origin_name: "Départ"            # l'origin doit être validée pour ouvrir les hubs
  default_unlock: parent           # parent (implicite) | none
  hub_completion: click            # click (action complete) | all_of: [noms]  — JAMAIS children sur un hub :
                                   # ses enfants attendent qu'il soit complété (interblocage)
  central_completion: { type: all, nodes: children }   # Vision se complète seule quand ses facettes le sont
  central_children_unlock: origin  # ses enfants se débloquent dès le Départ (unlock node → origin)
  conditions:                      # conditions particulières, par nom de nœud
    "10 articles publiés":   { phase: complete, type: metric,   metric: posts_published, op: ">=", value: 10 }
    "10 clients signés":     { phase: complete, type: metric,   metric: deals_won,       op: ">=", value: 10 }
    "NPS ≥ 40":              { phase: complete, type: metric,   metric: nps,             op: ">=", value: 40 }
    "Automatisations":       { phase: unlock,   type: all,      nodes: ["Process cartographiés", "Checklist qualité"] }
    "Prévisions":            { phase: unlock,   type: any,      nodes: ["Segmentation", "Budget prévisionnel"], min: 1 }
    "Abonnement réglé":      { phase: complete, type: callback, name: has_paid }
    "Premier recrutement":   { phase: complete, type: manual }
  metrics_sources:                 # d'où viennent les métriques (code métier hôte)
    posts_published: "après INSERT d'un article (BlogController::publish)"
    deals_won:       "quand un devis passe en 'signé'"
    nps:             "import mensuel de l'outil d'enquête (mode set)"
  rewards: { hub_xp: 100, node_xp_per_depth: 10 }

# ─── Intégration ─────────────────────────────────────────────────────────────
integration:
  page_route: /arbre
  api_route: /arbre/api
  controller: controllers/SuccessTreeController.php
  db_access: forceRequest          # forceRequest (singleton) | pdo
  db_singleton: "Database::getInstance()"
  escape: "getConnection()->real_escape_string"
  subject: "$_SESSION['user']['id']"
  can_edit: "rôle admin"
  can_progress_client: false       # le navigateur ne se valide pas lui-même
  validators: "admin (étapes manual)"
  on_complete: "ajout d'XP + notification"
```

## Notes de remplissage

- **Coordonnées exactes** (`shape: coords`) : fournir `x`/`y` par hub en unités monde, origine (0,0), y vers le bas.
- **Pas de position** pour une branche : laisser `x`/`y` à `null` dans le payload, le JS fera l'auto-layout.
- Les **noms** servent de clés de référence dans `conditions` et `extra_links` : ils doivent être uniques dans le
  brief (l'IA les convertit en `uid`).
- Couleurs : hexadécimal ou `rgba()` ; privilégier un contraste suffisant sur le fond.
