<?php

declare(strict_types=1);

namespace SuccessTree\Seed;

use SuccessTree\Service\Layout;

/**
 * Demo tree "heart-demo": 1 origin, 9 hubs on the heart curve + 1 central hub (Cœur / Vision)
 * sitting on the origin. Each hub has 2–3 branches of depth 2–4 with varied conditions.
 *
 *   $st->handleArray('save', [], HeartDemo::payload());
 */
final class HeartDemo
{
    const TREE_UID = 't_heart_demo';
    const ORIGIN = 'origin';

    /**
     * Hub definitions. A step = [name, description, icon, completeCondition|null].
     * A completeCondition is [type, params] (phase "complete").
     */
    private static function hubs(): array
    {
        return [
            // k = 0 : bottom tip
            ['sante', 'Santé', 'Énergie, équilibre et longévité : le carburant de tout le reste.', 'leaf', '#5be3a4', 'Athlète', [
                'Corps' => [
                    ['Bouger 3 fois par semaine', 'Tenir 12 séances de sport sur le mois.', 'flame', ['metric', ['metric' => 'workouts', 'op' => '>=', 'value' => 12]]],
                    ['Sommeil de 7 h', 'Dormir au moins 7 heures, 5 nuits sur 7.', 'star', null],
                    ['Bilan de santé', 'Faire un check-up complet.', 'shield', ['manual', []]],
                ],
                'Esprit' => [
                    ['Méditation quotidienne', '30 jours de méditation (même 5 minutes).', 'leaf', ['metric', ['metric' => 'meditation_days', 'op' => '>=', 'value' => 30]]],
                    ['Journal de gratitude', 'Écrire trois gratitudes chaque soir.', 'book', null],
                ],
                'Équilibre' => [
                    ['Vacances planifiées', 'Bloquer ses congés dans l’agenda.', 'flag', ['manual', []]],
                    ['Week-ends déconnectés', 'Aucun e-mail pro le week-end pendant un mois.', 'lock', null],
                ],
            ]],
            // k = 1 : lower left/right pairs follow the curve
            ['finances', 'Finances', 'Trésorerie saine, marges solides, décisions sereines.', 'gem', '#ff8c5a', 'Trésorier', [
                'Trésorerie' => [
                    ['Budget prévisionnel', 'Construire le prévisionnel à 12 mois.', 'list', null],
                    ['3 mois de réserve', 'Avoir 3 mois de charges en trésorerie.', 'shield', ['metric', ['metric' => 'cash_months', 'op' => '>=', 'value' => 3]]],
                    ['6 mois de réserve', 'Le coussin de sécurité complet.', 'gem', ['metric', ['metric' => 'cash_months', 'op' => '>=', 'value' => 6]]],
                ],
                'Rentabilité' => [
                    ['Seuil de rentabilité', 'Connaître et dépasser son point mort.', 'target', ['manual', []]],
                    ['Marge de 30 %', 'Atteindre 30 % de marge nette.', 'chart', ['metric', ['metric' => 'margin', 'op' => '>=', 'value' => 30]]],
                ],
            ]],
            ['equipe', 'Équipe', 'Recruter, fédérer, déléguer : grandir à plusieurs.', 'shield', '#5c8aff', 'Leader', [
                'Recrutement' => [
                    ['Fiche de poste', 'Décrire précisément le premier rôle clé.', 'list', null],
                    ['Premier recrutement', 'Accueillir la première recrue.', 'users', ['manual', []]],
                    ['Équipe de 5', 'Atteindre cinq personnes dans l’équipe.', 'users', ['metric', ['metric' => 'team_size', 'op' => '>=', 'value' => 5]]],
                ],
                'Culture' => [
                    ['Valeurs écrites', 'Formaliser les valeurs de l’équipe.', 'book', null],
                    ['Rituels d’équipe', 'Point hebdo + rétrospective mensuelle.', 'flag', null],
                ],
                'Délégation' => [
                    ['10 tâches déléguées', 'Confier dix tâches récurrentes.', 'layers', ['metric', ['metric' => 'delegated', 'op' => '>=', 'value' => 10]]],
                    ['Manager autonome', 'Un manager pilote une activité sans vous.', 'crown', ['manual', []]],
                ],
            ]],
            ['clients', 'Clients', 'Écouter, satisfaire et fidéliser ceux qui vous font vivre.', 'users', '#3ad0ff', 'Ami des clients', [
                'Écoute' => [
                    ['Premier avis client', 'Recueillir un premier avis publié.', 'star', ['metric', ['metric' => 'reviews', 'op' => '>=', 'value' => 1]]],
                    ['NPS mesuré', 'Lancer la première enquête NPS.', 'chart', null],
                    ['NPS supérieur à 50', 'Des clients qui recommandent activement.', 'heart', ['metric', ['metric' => 'nps', 'op' => '>', 'value' => 50]]],
                ],
                'Fidélisation' => [
                    ['Programme de fidélité', 'Récompenser les clients réguliers.', 'gem', null],
                    ['Rétention de 80 %', '80 % des clients restent après un an.', 'shield', ['metric', ['metric' => 'retention', 'op' => '>=', 'value' => 80]]],
                ],
                'Communauté' => [
                    ['Groupe privé', 'Ouvrir un espace d’échange entre clients.', 'users', null],
                    ['100 membres', 'La communauté atteint cent membres.', 'crown', ['metric', ['metric' => 'members', 'op' => '>=', 'value' => 100]]],
                ],
            ]],
            ['marketing', 'Marketing', 'Se faire connaître et attirer les bonnes personnes.', 'globe', '#ff5c8a', 'Marketeur', [
                'Contenu' => [
                    ['Ligne éditoriale', 'Définir cibles, messages et formats.', 'book', null],
                    ['Premier article', 'Publier le premier contenu de fond.', 'flag', ['metric', ['metric' => 'posts_published', 'op' => '>=', 'value' => 1]]],
                    ['10 articles publiés', 'Tenir le rythme sur la durée.', 'layers', ['metric', ['metric' => 'posts_published', 'op' => '>=', 'value' => 10]]],
                    ['Newsletter lancée', 'Premier envoi à la liste d’abonnés.', 'bolt', null],
                ],
                'Réseaux sociaux' => [
                    ['Profil optimisé', 'Bio, visuels et lien d’appel à l’action.', 'check', null],
                    ['100 abonnés', 'Première audience engagée.', 'users', ['metric', ['metric' => 'followers', 'op' => '>=', 'value' => 100]]],
                    ['1 000 abonnés', 'Une audience qui compte.', 'star', ['metric', ['metric' => 'followers', 'op' => '>=', 'value' => 1000]]],
                ],
                'Publicité' => [
                    ['Budget test', 'Lancer une première campagne de 100 €.', 'target', ['manual', []]],
                    ['Campagne rentable', 'ROAS supérieur ou égal à 2.', 'chart', ['metric', ['metric' => 'roas', 'op' => '>=', 'value' => 2]]],
                ],
            ]],
            ['ventes', 'Ventes', 'Transformer l’intérêt en chiffre d’affaires.', 'chart', '#ffc857', 'Vendeur étoile', [
                'Prospection' => [
                    ['50 prospects qualifiés', 'Construire une liste de 50 prospects.', 'filter', ['metric', ['metric' => 'prospects', 'op' => '>=', 'value' => 50]]],
                    ['20 appels passés', 'Décrocher son téléphone vingt fois.', 'bolt', ['metric', ['metric' => 'calls', 'op' => '>=', 'value' => 20]]],
                    ['Premier rendez-vous', 'Obtenir un rendez-vous découverte.', 'flag', null],
                ],
                'Closing' => [
                    ['Script de vente', 'Écrire et répéter son argumentaire.', 'book', null],
                    ['Première vente', 'Signer le premier client.', 'check', ['metric', ['metric' => 'sales', 'op' => '>=', 'value' => 1]]],
                    ['10 ventes', 'Dix clients signés.', 'crown', ['metric', ['metric' => 'sales', 'op' => '>=', 'value' => 10]]],
                ],
                'Partenariats' => [
                    ['Premier partenaire', 'Nouer un partenariat apporteur d’affaires.', 'users', ['manual', []]],
                    ['Programme d’affiliation', 'Ouvrir l’affiliation à ses clients.', 'layers', null],
                ],
            ]],
            ['intelligence', 'Intelligence', 'Apprendre vite, décider juste.', 'book', '#9b7bff', 'Stratège', [
                'Apprentissage' => [
                    ['Un livre par mois', 'Lire un premier livre business.', 'book', ['metric', ['metric' => 'books_read', 'op' => '>=', 'value' => 1]]],
                    ['12 livres', 'Une année de lectures.', 'star', ['metric', ['metric' => 'books_read', 'op' => '>=', 'value' => 12]]],
                ],
                'Veille' => [
                    ['Sources de veille', 'Sélectionner dix sources de qualité.', 'filter', null],
                    ['Rapport mensuel', 'Synthèse mensuelle des tendances.', 'list', null],
                ],
                'Données' => [
                    ['Tableau de bord', 'Centraliser les chiffres clés.', 'database', null],
                    ['5 KPI suivis', 'Suivre cinq indicateurs chaque semaine.', 'chart', ['metric', ['metric' => 'kpis_tracked', 'op' => '>=', 'value' => 5]]],
                    ['Décisions pilotées par la donnée', 'Chaque décision majeure s’appuie sur un chiffre.', 'target', ['manual', []]],
                ],
            ]],
            ['operations', 'Opérations', 'Des process fluides qui tournent sans vous.', 'layers', '#ff7ad9', 'Architecte', [
                'Processus' => [
                    ['Cartographier', 'Lister tous les processus de l’entreprise.', 'list', null],
                    ['Documenter', 'Rédiger un mode opératoire par processus.', 'book', null],
                    ['Automatiser', 'Automatiser trois tâches répétitives.', 'bolt', ['metric', ['metric' => 'automations', 'op' => '>=', 'value' => 3]]],
                ],
                'Outils' => [
                    ['CRM en place', 'Toutes les opportunités dans un seul outil.', 'database', ['manual', []]],
                    ['Facturation automatisée', 'Devis et factures générés en un clic.', 'check', null],
                ],
                'Qualité' => [
                    ['Checklists', 'Une checklist par livrable.', 'check', null],
                    ['Zéro retard', 'Aucun retard de livraison ce trimestre.', 'shield', ['metric', ['metric' => 'late_deliveries', 'op' => '==', 'value' => 0]]],
                ],
            ]],
            ['produit', 'Produit', 'Construire ce que les clients adorent.', 'bolt', '#b6ff5c', 'Créateur', [
                'Conception' => [
                    ['Problème validé', 'Dix entretiens confirment le problème.', 'target', null],
                    ['Prototype', 'Une première version testable.', 'layers', null],
                    ['MVP lancé', 'La version minimale est en ligne.', 'flag', ['manual', []]],
                ],
                'Itération' => [
                    ['20 retours utilisateurs', 'Collecter vingt retours détaillés.', 'users', ['metric', ['metric' => 'feedbacks', 'op' => '>=', 'value' => 20]]],
                    ['Version 2', 'Livrer la version améliorée.', 'star', null],
                ],
                'Innovation' => [
                    ['Idée de rupture', 'Explorer une idée qui change la donne.', 'flame', null],
                    ['Différenciation', 'Un avantage que les concurrents n’ont pas.', 'gem', ['manual', []]],
                ],
            ]],
        ];
    }

    public static function payload(): array
    {
        $heart = Layout::heart(420.0);
        $nodes = [];
        $sort = 0;

        $nodes[] = [
            'uid' => self::ORIGIN,
            'parent' => null,
            'kind' => 'origin',
            'slug' => 'depart',
            'name' => 'Départ',
            'description' => 'Le point de départ de votre ascension. Validez-le pour ouvrir les neuf domaines.',
            'icon' => 'target',
            'x' => 0.0,
            'y' => 0.0,
            'theme' => ['color' => '#ffffff', 'glow' => '#ff5c8a', 'size' => 1.1],
            'reward' => ['xp' => 10],
            'sort' => $sort++,
            'meta' => [],
            'conditions' => [],
        ];

        // Central hub (Cœur / Vision) posed on the origin; its children deploy around it.
        $central = [
            'uid' => 'h_coeur',
            'parent' => self::ORIGIN,
            'kind' => 'hub',
            'slug' => 'coeur-vision',
            'name' => 'Cœur / Vision',
            'description' => 'Pourquoi vous faites tout cela : mission, vision et valeurs.',
            'icon' => 'heart',
            'x' => $heart['center']['x'],
            'y' => $heart['center']['y'],
            'theme' => ['color' => '#ff5c8a', 'glow' => '#ff9ab8', 'size' => 1.3, 'label' => 'always'],
            'reward' => ['xp' => 150, 'badge' => 'Visionnaire'],
            'sort' => $sort++,
            'meta' => ['central' => true],
            'conditions' => [
                ['uid' => 'c_coeur_done', 'phase' => 'complete', 'type' => 'children', 'params' => ['min' => null]],
            ],
        ];
        $nodes[] = $central;
        self::addBranches($nodes, $sort, 'coeur', 'h_coeur', '#ff5c8a', [
            'Mission' => [
                ['Raison d’être', 'Écrire sa mission en une phrase.', 'heart', null],
                ['Pitch de 30 secondes', 'Savoir raconter pourquoi l’entreprise existe.', 'flag', null],
            ],
            'Vision' => [
                ['Vision à 5 ans', 'Décrire l’entreprise idéale dans cinq ans.', 'globe', null],
                ['Objectifs annuels', 'Trois objectifs chiffrés pour l’année.', 'target', null],
                ['OKR trimestriels', 'Décliner les objectifs chaque trimestre.', 'list', ['metric', ['metric' => 'okr_quarters', 'op' => '>=', 'value' => 1]]],
            ],
            'Valeurs' => [
                ['Trois valeurs clés', 'Choisir les valeurs non négociables.', 'gem', null],
                ['Charte partagée', 'La charte est signée par toute l’équipe.', 'book', ['manual', []]],
            ],
        ]);

        foreach (self::hubs() as $k => $h) {
            list($key, $name, $desc, $icon, $color, $badge, $branches) = $h;
            $hubUid = 'h_' . $key;
            $p = $heart['points'][$k];
            $complete = ($k % 3 === 2)
                ? ['uid' => 'c_' . $key . '_done', 'phase' => 'complete', 'type' => 'manual', 'params' => []]
                : ['uid' => 'c_' . $key . '_done', 'phase' => 'complete', 'type' => 'children', 'params' => ['min' => 2]];
            $nodes[] = [
                'uid' => $hubUid,
                'parent' => self::ORIGIN,
                'kind' => 'hub',
                'slug' => $key,
                'name' => $name,
                'description' => $desc,
                'icon' => $icon,
                'x' => $p['x'],
                'y' => $p['y'],
                'theme' => ['color' => $color, 'glow' => $color, 'size' => 1],
                'reward' => ['xp' => 100, 'badge' => $badge],
                'sort' => $sort++,
                'meta' => ['domain' => $key],
                'conditions' => [$complete],
            ];
            self::addBranches($nodes, $sort, $key, $hubUid, $color, $branches);
        }

        // A few cross-branch conditions ("any" / "node").
        foreach ($nodes as $i => $n) {
            if ($n['uid'] === 'n_ventes_3_1') { // Partenariats: unlocked by the 1st meeting OR the 1st sale
                $nodes[$i]['conditions'][] = ['uid' => 'c_partenariats_unlock', 'phase' => 'unlock', 'type' => 'any',
                    'params' => ['nodes' => ['n_ventes_1_3', 'n_ventes_2_2'], 'min' => 1]];
            }
            if ($n['uid'] === 'n_finances_2_2') { // Marge 30 %: needs the 1st sale too
                array_unshift($nodes[$i]['conditions'], ['uid' => 'c_marge_unlock_parent', 'phase' => 'unlock', 'type' => 'parent', 'params' => []]);
                $nodes[$i]['conditions'][] = ['uid' => 'c_marge_unlock_sale', 'phase' => 'unlock', 'type' => 'node',
                    'params' => ['node' => 'n_ventes_2_2']];
            }
            if ($n['uid'] === 'n_marketing_1_4') { // Newsletter: needs 1 article AND the social profile
                $nodes[$i]['conditions'][] = ['uid' => 'c_newsletter_unlock', 'phase' => 'unlock', 'type' => 'all',
                    'params' => ['nodes' => ['n_marketing_1_3', 'n_marketing_2_1']]];
            }
        }

        $links = [
            // Newsletter -> "100 membres" (bridge = extra prerequisite)
            ['uid' => 'l_newsletter_communaute', 'from' => 'n_marketing_1_4', 'to' => 'n_clients_3_2', 'type' => 'path'],
            // Purely visual bridges
            ['uid' => 'l_dashboard_budget', 'from' => 'n_intelligence_3_1', 'to' => 'n_finances_1_1', 'type' => 'visual'],
            ['uid' => 'l_retours_nps', 'from' => 'n_produit_2_1', 'to' => 'n_clients_1_2', 'type' => 'visual'],
            ['uid' => 'l_vision_objectifs', 'from' => 'n_coeur_2_2', 'to' => 'n_ventes_2_3', 'type' => 'visual'],
        ];

        return [
            'tree' => [
                'uid' => self::TREE_UID,
                'slug' => 'heart-demo',
                'name' => 'Arbre du succès',
                'description' => 'Neuf domaines disposés en cœur autour de votre vision : développez votre entreprise et vous-même, étoile après étoile.',
                'layout' => 'heart',
                'theme' => [
                    'background' => '#1b1d45',
                    'accent' => '#ff5c8a',
                    'line' => 'rgba(255,255,255,.55)',
                    'font' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                ],
                'settings' => ['expandPush' => 1.6, 'hubRadius' => 34, 'nodeRadius' => 9, 'showLabels' => 'hover', 'heartRadius' => 420],
            ],
            'nodes' => $nodes,
            'links' => $links,
        ];
    }

    /**
     * Branch roots unlock with the origin (like their hub) so that the hub can be completed
     * by its children ("children" condition) without a deadlock.
     */
    private static function addBranches(array &$nodes, int &$sort, string $key, string $hubUid, string $color, array $branches): void
    {
        $b = 0;
        foreach ($branches as $branchName => $steps) {
            $b++;
            $parent = $hubUid;
            foreach ($steps as $d => $step) {
                list($name, $desc, $icon, $cond) = $step;
                $uid = 'n_' . $key . '_' . $b . '_' . ($d + 1);
                $conditions = [];
                if ($d === 0) {
                    $conditions[] = ['uid' => 'c_' . $key . '_' . $b . '_unlock', 'phase' => 'unlock', 'type' => 'node', 'params' => ['node' => self::ORIGIN]];
                }
                if ($cond !== null) {
                    $conditions[] = ['uid' => 'c_' . $key . '_' . $b . '_' . ($d + 1), 'phase' => 'complete', 'type' => $cond[0], 'params' => $cond[1]];
                }
                $nodes[] = [
                    'uid' => $uid,
                    'parent' => $parent,
                    'kind' => 'node',
                    'slug' => null,
                    'name' => $name,
                    'description' => $desc,
                    'icon' => $icon,
                    'x' => null,
                    'y' => null,
                    'theme' => ['color' => $color],
                    'reward' => ['xp' => 10 * ($d + 1)],
                    'sort' => $sort++,
                    'meta' => ['branch' => $branchName, 'depth' => $d + 1],
                    'conditions' => $conditions,
                ];
                $parent = $uid;
            }
        }
    }
}
