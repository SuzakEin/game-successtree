# Exemples : brief visuel → payload JSON

Trois briefs complets et le payload SuccessTree correspondant (SPEC §4 / §5). Chaque payload est **valide** : `uid` uniques (nœuds, conditions, liens) **et préfixés par arbre** (`cz_`, `ffx_`, `civ_`) car les uid sont des clés
primaires globales à toutes les tables, exactement un `origin`, parents et références existants, liens entre
nœuds existants. Les `status` sont omis (calculés). Tous ont été
enregistrés via l'action `save` puis joués jusqu'au bout (aucun nœud bloqué à vie).

Pièges de conception vérifiés :
- une condition `unlock` explicite **remplace** le parent implicite : incluez le parent dans un `all` si le nœud doit
  aussi l'attendre ;
- ne mettez pas `children` en condition `complete` d'un nœud dont les enfants n'ont que le parent implicite :
  interblocage (les enfants attendent le parent, le parent attend les enfants) ; `children` sur un nœud sans
  enfant n'est jamais vrai.

Envoi : `POST <route>?action=save` (admin) ou `$st->handleArray('save', ['tree' => $slug], $payload)`.

Vérification rapide d'un payload (Node, sans dépendance) :

```bash
node -e 'const p=JSON.parse(require("fs").readFileSync(0,"utf8"));const u=new Set(),e=[];
p.nodes.forEach(n=>{u.has(n.uid)&&e.push("dup "+n.uid);u.add(n.uid)});
p.nodes.filter(n=>n.kind==="origin").length!==1&&e.push("origin");
p.nodes.forEach(n=>{n.parent!=null&&!u.has(n.parent)&&e.push("parent "+n.uid)});
(p.links||[]).forEach(l=>{(!u.has(l.from)||!u.has(l.to))&&e.push("lien "+l.uid)});
console.log(e.length?e:"OK")' < payload.json
```

---

## 1. Cœur 9 + 1 (constellation)

Brief :

```yaml
tree: { slug: mon-coeur, name: "Mon cœur" }
layout: { shape: heart, scale_R: 420, hubs_count: 9, central_hub: { enabled: true, name: "Cœur", offset_y: -72, push: 1.6 } }
branches: { per_hub: 2, depth: 2, direction: outward, spacing: [68, 46] }
theme: { references: ["constellation map"], background: "#2e2f6b", accent: "#c8b6ff", labels: hover, hub_colors: per_hub }
hubs: [Amour, Amis, Travail, Savoir, Créativité, Argent, Santé, Voyages, Sérénité]
extra_links: [{ ring_between_hubs: true, type: visual }]
conditions:
  "Travail 1.2": { phase: complete, type: metric, metric: missions_done, op: ">=", value: 5 }
  "Amour 2.2":   { phase: complete, type: manual }
  "Cœur":        { phase: complete, type: all, nodes: "les 9 hubs" }   # hub central acquis seul quand les 9 domaines le sont
```

Traduction : positions des hubs = preset cœur (t = π + 2πk/9, courbe divisée par 16 puis × 420, arrondi 0,1 — identique à `Layout::heart()`) ;
hub central en (0, −72) avec `meta.central = true` ; branches poussées dans la direction origin → hub (écartées de
±0,45 rad pour les deux hubs du creux, trop proches l'un de l'autre) ; anneau de liens `visual` entre hubs voisins.

```json
{
  "tree": {"uid":"t_coeur","slug":"mon-coeur","name":"Mon cœur","layout":"heart","theme":{"background":"#2e2f6b","accent":"#c8b6ff","line":"rgba(255,255,255,.55)"},"settings":{"expandPush":1.6,"hubRadius":34,"nodeRadius":9,"showLabels":"hover"}},
  "nodes": [
    {"uid":"cz_origin","parent":null,"kind":"origin","name":"Départ","icon":"target","x":0,"y":0,"theme":{"color":"#ffffff"},"conditions":[]},
    {"uid":"cz_h_centre","parent":"cz_origin","kind":"hub","name":"Cœur","icon":"heart","x":0,"y":-72,"theme":{"color":"#c8b6ff","size":1.2},"conditions":[{"uid":"cz_c3","phase":"complete","type":"all","params":{"nodes":["cz_h_a","cz_h_b","cz_h_c","cz_h_d","cz_h_e","cz_h_f","cz_h_g","cz_h_h","cz_h_i"]}}],"meta":{"central":true}},
    {"uid":"cz_h_a","parent":"cz_origin","kind":"hub","name":"Amour","icon":"heart","x":0,"y":446.3,"theme":{"color":"#ff5c8a"},"conditions":[]},
    {"uid":"cz_n_a11","parent":"cz_h_a","kind":"node","name":"Amour 1.1","icon":null,"x":23.3,"y":510.1,"theme":{},"conditions":[]},
    {"uid":"cz_n_a12","parent":"cz_n_a11","kind":"node","name":"Amour 1.2","icon":null,"x":44.3,"y":551.1,"theme":{},"conditions":[]},
    {"uid":"cz_n_a21","parent":"cz_h_a","kind":"node","name":"Amour 2.1","icon":null,"x":-23.3,"y":510.1,"theme":{},"conditions":[]},
    {"uid":"cz_n_a22","parent":"cz_n_a21","kind":"node","name":"Amour 2.2","icon":null,"x":-44.3,"y":551.1,"theme":{},"conditions":[{"uid":"cz_c2","phase":"complete","type":"manual","params":{}}]},
    {"uid":"cz_h_b","parent":"cz_origin","kind":"hub","name":"Amis","icon":"users","x":-111.5,"y":285.8,"theme":{"color":"#ffb547"},"conditions":[]},
    {"uid":"cz_n_b11","parent":"cz_h_b","kind":"node","name":"Amis 1.1","icon":null,"x":-113,"y":353.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_b12","parent":"cz_n_b11","kind":"node","name":"Amis 1.2","icon":null,"x":-108.4,"y":399.5,"theme":{},"conditions":[]},
    {"uid":"cz_n_b21","parent":"cz_h_b","kind":"node","name":"Amis 2.1","icon":null,"x":-156.5,"y":336.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_b22","parent":"cz_n_b21","kind":"node","name":"Amis 2.2","icon":null,"x":-190.9,"y":367.4,"theme":{},"conditions":[]},
    {"uid":"cz_h_c","parent":"cz_origin","kind":"hub","name":"Travail","icon":"building","x":-401.1,"y":-17.7,"theme":{"color":"#5ad1a5"},"conditions":[]},
    {"uid":"cz_n_c11","parent":"cz_h_c","kind":"node","name":"Travail 1.1","icon":null,"x":-466,"y":2.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_c12","parent":"cz_n_c11","kind":"node","name":"Travail 1.2","icon":null,"x":-507.8,"y":21.9,"theme":{},"conditions":[{"uid":"cz_c1","phase":"complete","type":"metric","params":{"metric":"missions_done","op":">=","value":5}}]},
    {"uid":"cz_n_c21","parent":"cz_h_c","kind":"node","name":"Travail 2.1","icon":null,"x":-463.9,"y":-43.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_c22","parent":"cz_n_c21","kind":"node","name":"Travail 2.2","icon":null,"x":-503.9,"y":-66.6,"theme":{},"conditions":[]},
    {"uid":"cz_h_d","parent":"cz_origin","kind":"hub","name":"Savoir","icon":"book","x":-272.8,"y":-301.9,"theme":{"color":"#7aa7ff"},"conditions":[]},
    {"uid":"cz_n_d11","parent":"cz_h_d","kind":"node","name":"Savoir 1.1","icon":null,"x":-332.9,"y":-333.6,"theme":{},"conditions":[]},
    {"uid":"cz_n_d12","parent":"cz_n_d11","kind":"node","name":"Savoir 1.2","icon":null,"x":-375.9,"y":-350,"theme":{},"conditions":[]},
    {"uid":"cz_n_d21","parent":"cz_h_d","kind":"node","name":"Savoir 2.1","icon":null,"x":-298.3,"y":-364.9,"theme":{},"conditions":[]},
    {"uid":"cz_n_d22","parent":"cz_n_d21","kind":"node","name":"Savoir 2.2","icon":null,"x":-310.3,"y":-409.3,"theme":{},"conditions":[]},
    {"uid":"cz_h_e","parent":"cz_origin","kind":"hub","name":"Créativité","icon":"star","x":-16.8,"y":-189.3,"theme":{"color":"#b18cff"},"conditions":[]},
    {"uid":"cz_n_e11","parent":"cz_h_e","kind":"node","name":"Créativité 1.1","icon":null,"x":-69.6,"y":-232.2,"theme":{},"conditions":[]},
    {"uid":"cz_n_e12","parent":"cz_n_e11","kind":"node","name":"Créativité 1.2","icon":null,"x":-108.6,"y":-256.6,"theme":{},"conditions":[]},
    {"uid":"cz_n_e21","parent":"cz_h_e","kind":"node","name":"Créativité 2.1","icon":null,"x":-29.5,"y":-256.1,"theme":{},"conditions":[]},
    {"uid":"cz_n_e22","parent":"cz_n_e21","kind":"node","name":"Créativité 2.2","icon":null,"x":-32.6,"y":-302,"theme":{},"conditions":[]},
    {"uid":"cz_h_f","parent":"cz_origin","kind":"hub","name":"Argent","icon":"gem","x":16.8,"y":-189.3,"theme":{"color":"#f2d45c"},"conditions":[]},
    {"uid":"cz_n_f11","parent":"cz_h_f","kind":"node","name":"Argent 1.1","icon":null,"x":29.5,"y":-256.1,"theme":{},"conditions":[]},
    {"uid":"cz_n_f12","parent":"cz_n_f11","kind":"node","name":"Argent 1.2","icon":null,"x":32.6,"y":-302,"theme":{},"conditions":[]},
    {"uid":"cz_n_f21","parent":"cz_h_f","kind":"node","name":"Argent 2.1","icon":null,"x":69.6,"y":-232.2,"theme":{},"conditions":[]},
    {"uid":"cz_n_f22","parent":"cz_n_f21","kind":"node","name":"Argent 2.2","icon":null,"x":108.6,"y":-256.6,"theme":{},"conditions":[]},
    {"uid":"cz_h_g","parent":"cz_origin","kind":"hub","name":"Santé","icon":"leaf","x":272.8,"y":-301.9,"theme":{"color":"#7ee07a"},"conditions":[]},
    {"uid":"cz_n_g11","parent":"cz_h_g","kind":"node","name":"Santé 1.1","icon":null,"x":298.3,"y":-364.9,"theme":{},"conditions":[]},
    {"uid":"cz_n_g12","parent":"cz_n_g11","kind":"node","name":"Santé 1.2","icon":null,"x":310.3,"y":-409.3,"theme":{},"conditions":[]},
    {"uid":"cz_n_g21","parent":"cz_h_g","kind":"node","name":"Santé 2.1","icon":null,"x":332.9,"y":-333.6,"theme":{},"conditions":[]},
    {"uid":"cz_n_g22","parent":"cz_n_g21","kind":"node","name":"Santé 2.2","icon":null,"x":375.9,"y":-350,"theme":{},"conditions":[]},
    {"uid":"cz_h_h","parent":"cz_origin","kind":"hub","name":"Voyages","icon":"globe","x":401.1,"y":-17.7,"theme":{"color":"#4fd6e8"},"conditions":[]},
    {"uid":"cz_n_h11","parent":"cz_h_h","kind":"node","name":"Voyages 1.1","icon":null,"x":463.9,"y":-43.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_h12","parent":"cz_n_h11","kind":"node","name":"Voyages 1.2","icon":null,"x":503.9,"y":-66.6,"theme":{},"conditions":[]},
    {"uid":"cz_n_h21","parent":"cz_h_h","kind":"node","name":"Voyages 2.1","icon":null,"x":466,"y":2.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_h22","parent":"cz_n_h21","kind":"node","name":"Voyages 2.2","icon":null,"x":507.8,"y":21.9,"theme":{},"conditions":[]},
    {"uid":"cz_h_i","parent":"cz_origin","kind":"hub","name":"Sérénité","icon":"shield","x":111.5,"y":285.8,"theme":{"color":"#ff7a59"},"conditions":[]},
    {"uid":"cz_n_i11","parent":"cz_h_i","kind":"node","name":"Sérénité 1.1","icon":null,"x":156.5,"y":336.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_i12","parent":"cz_n_i11","kind":"node","name":"Sérénité 1.2","icon":null,"x":190.9,"y":367.4,"theme":{},"conditions":[]},
    {"uid":"cz_n_i21","parent":"cz_h_i","kind":"node","name":"Sérénité 2.1","icon":null,"x":113,"y":353.8,"theme":{},"conditions":[]},
    {"uid":"cz_n_i22","parent":"cz_n_i21","kind":"node","name":"Sérénité 2.2","icon":null,"x":108.4,"y":399.5,"theme":{},"conditions":[]}
  ],
  "links": [
    {"uid":"cz_l0","from":"cz_h_a","to":"cz_h_b","type":"visual"},
    {"uid":"cz_l1","from":"cz_h_b","to":"cz_h_c","type":"visual"},
    {"uid":"cz_l2","from":"cz_h_c","to":"cz_h_d","type":"visual"},
    {"uid":"cz_l3","from":"cz_h_d","to":"cz_h_e","type":"visual"},
    {"uid":"cz_l4","from":"cz_h_e","to":"cz_h_f","type":"visual"},
    {"uid":"cz_l5","from":"cz_h_f","to":"cz_h_g","type":"visual"},
    {"uid":"cz_l6","from":"cz_h_g","to":"cz_h_h","type":"visual"},
    {"uid":"cz_l7","from":"cz_h_h","to":"cz_h_i","type":"visual"},
    {"uid":"cz_l8","from":"cz_h_i","to":"cz_h_a","type":"visual"}
  ],
  "metrics": {"missions_done":2},
  "permissions": {"edit":true,"progress":false}
}
```

---

## 2. Sphérier FFX (réseau libre en grille)

Brief :

```yaml
tree: { slug: spherier, name: "Sphérier" }
layout: { shape: free, grid: hex, step: 70 }
theme: { references: ["Sphérier FFX"], background: "#0d1330", accent: "#9fd3ff", line: "rgba(160,200,255,.45)", hub_radius: 26, node_radius: 11, labels: hover }
hubs:                         # points de départ des personnages, à 3 pas du centre, à -90°, 30°, 150°
  - { name: Guerrier, icon: shield, color: "#ff6b6b", path: ["Force +2", "PV +200", "Défense +1", "Force +3", "Coup puissant"] }
  - { name: Mage,     icon: star,   color: "#6bb8ff", path: ["Magie +2", "PM +40", "Soin", "Magie +3", "Brasier"] }
  - { name: Voleur,   icon: bolt,   color: "#7ee07a", path: ["Agilité +2", "Chance +1", "Vol", "Agilité +3", "Esquive"] }
locks:                        # verrous entre zones voisines, ouverts par le niveau de sphère
  between_neighbours: true
  condition: { phase: complete, type: metric, metric: sphere_level, op: ">=", value: "1..3" }
loops: ["Défense +1 ↔ Magie +2", "Soin ↔ Agilité +2", "Vol ↔ Force +2"]   # liens visual transverses
final: { name: Ultima, icon: crown, unlock: { type: all, nodes: ["Coup puissant", "Brasier", "Esquive"] }, complete: manual }
```

Traduction : chemins en zigzag (±0,28 rad) vers l'extérieur ; verrous = nœuds `node` sans parent avec
`unlock any` (un des deux hubs voisins) + `complete metric` ; lien `path` hub → verrou et `visual` verrou → hub
voisin ; boucles en liens `visual` ; nœud final sans parent débloqué par `all`.

```json
{
  "tree": {"uid":"t_spherier","slug":"spherier","name":"Sphérier","layout":"free","theme":{"background":"#0d1330","accent":"#9fd3ff","line":"rgba(160,200,255,.45)"},"settings":{"expandPush":1.2,"hubRadius":26,"nodeRadius":11,"showLabels":"hover"}},
  "nodes": [
    {"uid":"ffx_origin","parent":null,"kind":"origin","name":"Centre du réseau","icon":"gem","x":0,"y":0,"theme":{"color":"#e8f0ff"},"conditions":[]},
    {"uid":"ffx_h_guerrier","parent":"ffx_origin","kind":"hub","name":"Guerrier","icon":"shield","x":0,"y":-210,"theme":{"color":"#ff6b6b"},"conditions":[]},
    {"uid":"ffx_n_guerrier_1","parent":"ffx_h_guerrier","kind":"node","name":"Force +2","icon":null,"x":-77.4,"y":-269.1,"theme":{"color":"#ff6b6b"},"conditions":[]},
    {"uid":"ffx_n_guerrier_2","parent":"ffx_n_guerrier_1","kind":"node","name":"PV +200","icon":null,"x":96.7,"y":-336.4,"theme":{"color":"#ff6b6b"},"conditions":[]},
    {"uid":"ffx_n_guerrier_3","parent":"ffx_n_guerrier_2","kind":"node","name":"Défense +1","icon":null,"x":-116.1,"y":-403.6,"theme":{"color":"#ff6b6b"},"conditions":[]},
    {"uid":"ffx_n_guerrier_4","parent":"ffx_n_guerrier_3","kind":"node","name":"Force +3","icon":null,"x":135.4,"y":-470.9,"theme":{"color":"#ff6b6b"},"conditions":[]},
    {"uid":"ffx_n_guerrier_5","parent":"ffx_n_guerrier_4","kind":"node","name":"Coup puissant","icon":null,"x":-154.8,"y":-538.2,"theme":{"color":"#ff6b6b"},"conditions":[]},
    {"uid":"ffx_h_mage","parent":"ffx_origin","kind":"hub","name":"Mage","icon":"star","x":181.9,"y":105,"theme":{"color":"#6bb8ff"},"conditions":[]},
    {"uid":"ffx_n_mage_1","parent":"ffx_h_mage","kind":"node","name":"Magie +2","icon":null,"x":271.7,"y":67.5,"theme":{"color":"#6bb8ff"},"conditions":[]},
    {"uid":"ffx_n_mage_2","parent":"ffx_n_mage_1","kind":"node","name":"PM +40","icon":null,"x":242.9,"y":252,"theme":{"color":"#6bb8ff"},"conditions":[]},
    {"uid":"ffx_n_mage_3","parent":"ffx_n_mage_2","kind":"node","name":"Soin","icon":null,"x":407.6,"y":101.3,"theme":{"color":"#6bb8ff"},"conditions":[]},
    {"uid":"ffx_n_mage_4","parent":"ffx_n_mage_3","kind":"node","name":"Magie +3","icon":null,"x":340.1,"y":352.7,"theme":{"color":"#6bb8ff"},"conditions":[]},
    {"uid":"ffx_n_mage_5","parent":"ffx_n_mage_4","kind":"node","name":"Brasier","icon":null,"x":543.5,"y":135.1,"theme":{"color":"#6bb8ff"},"conditions":[]},
    {"uid":"ffx_h_voleur","parent":"ffx_origin","kind":"hub","name":"Voleur","icon":"bolt","x":-181.9,"y":105,"theme":{"color":"#7ee07a"},"conditions":[]},
    {"uid":"ffx_n_voleur_1","parent":"ffx_h_voleur","kind":"node","name":"Agilité +2","icon":null,"x":-194.4,"y":201.6,"theme":{"color":"#7ee07a"},"conditions":[]},
    {"uid":"ffx_n_voleur_2","parent":"ffx_n_voleur_1","kind":"node","name":"Chance +1","icon":null,"x":-339.7,"y":84.4,"theme":{"color":"#7ee07a"},"conditions":[]},
    {"uid":"ffx_n_voleur_3","parent":"ffx_n_voleur_2","kind":"node","name":"Vol","icon":null,"x":-291.5,"y":302.3,"theme":{"color":"#7ee07a"},"conditions":[]},
    {"uid":"ffx_n_voleur_4","parent":"ffx_n_voleur_3","kind":"node","name":"Agilité +3","icon":null,"x":-475.5,"y":118.2,"theme":{"color":"#7ee07a"},"conditions":[]},
    {"uid":"ffx_n_voleur_5","parent":"ffx_n_voleur_4","kind":"node","name":"Esquive","icon":null,"x":-388.7,"y":403.1,"theme":{"color":"#7ee07a"},"conditions":[]},
    {"uid":"ffx_lock_guerrier_mage","parent":null,"kind":"node","name":"Verrou niv. 1","icon":"lock","x":181.9,"y":-105,"theme":{"color":"#9aa3c7","shape":"diamond"},"conditions":[{"uid":"ffx_c1","phase":"unlock","type":"any","params":{"nodes":["ffx_h_guerrier","ffx_h_mage"],"min":1}},{"uid":"ffx_c2","phase":"complete","type":"metric","params":{"metric":"sphere_level","op":">=","value":1}}]},
    {"uid":"ffx_lock_mage_voleur","parent":null,"kind":"node","name":"Verrou niv. 2","icon":"lock","x":0,"y":210,"theme":{"color":"#9aa3c7","shape":"diamond"},"conditions":[{"uid":"ffx_c3","phase":"unlock","type":"any","params":{"nodes":["ffx_h_mage","ffx_h_voleur"],"min":1}},{"uid":"ffx_c4","phase":"complete","type":"metric","params":{"metric":"sphere_level","op":">=","value":2}}]},
    {"uid":"ffx_lock_voleur_guerrier","parent":null,"kind":"node","name":"Verrou niv. 3","icon":"lock","x":-181.9,"y":-105,"theme":{"color":"#9aa3c7","shape":"diamond"},"conditions":[{"uid":"ffx_c5","phase":"unlock","type":"any","params":{"nodes":["ffx_h_voleur","ffx_h_guerrier"],"min":1}},{"uid":"ffx_c6","phase":"complete","type":"metric","params":{"metric":"sphere_level","op":">=","value":3}}]},
    {"uid":"ffx_n_ultime","parent":null,"kind":"node","name":"Ultima","icon":"crown","x":0,"y":-455,"theme":{"color":"#ffd86b","size":1.6},"conditions":[{"uid":"ffx_c7","phase":"unlock","type":"all","params":{"nodes":["ffx_n_guerrier_5","ffx_n_mage_5","ffx_n_voleur_5"]}},{"uid":"ffx_c8","phase":"complete","type":"manual","params":{}}]}
  ],
  "links": [
    {"uid":"ffx_l_lock_guerrier_mage_a","from":"ffx_h_guerrier","to":"ffx_lock_guerrier_mage","type":"path"},
    {"uid":"ffx_l_lock_guerrier_mage_b","from":"ffx_lock_guerrier_mage","to":"ffx_h_mage","type":"visual"},
    {"uid":"ffx_l_lock_mage_voleur_a","from":"ffx_h_mage","to":"ffx_lock_mage_voleur","type":"path"},
    {"uid":"ffx_l_lock_mage_voleur_b","from":"ffx_lock_mage_voleur","to":"ffx_h_voleur","type":"visual"},
    {"uid":"ffx_l_lock_voleur_guerrier_a","from":"ffx_h_voleur","to":"ffx_lock_voleur_guerrier","type":"path"},
    {"uid":"ffx_l_lock_voleur_guerrier_b","from":"ffx_lock_voleur_guerrier","to":"ffx_h_guerrier","type":"visual"},
    {"uid":"ffx_l_x1","from":"ffx_n_guerrier_3","to":"ffx_n_mage_1","type":"visual"},
    {"uid":"ffx_l_x2","from":"ffx_n_mage_3","to":"ffx_n_voleur_1","type":"visual"},
    {"uid":"ffx_l_x3","from":"ffx_n_voleur_3","to":"ffx_n_guerrier_1","type":"visual"},
    {"uid":"ffx_l_ult_guerrier","from":"ffx_n_guerrier_5","to":"ffx_n_ultime","type":"path"},
    {"uid":"ffx_l_ult_mage","from":"ffx_n_mage_5","to":"ffx_n_ultime","type":"path"},
    {"uid":"ffx_l_ult_voleur","from":"ffx_n_voleur_5","to":"ffx_n_ultime","type":"path"}
  ],
  "metrics": {"sphere_level":1},
  "permissions": {"edit":true,"progress":false}
}
```

---

## 3. Arbre des politiques Civ V (colonnes verticales, conditions any / all)

Brief :

```yaml
tree: { slug: politiques, name: "Politiques sociales" }
layout: { shape: coords, columns: [-360, 0, 360], hub_row_y: 140, row_step: 110, direction: down }
theme: { references: ["Civilization V — politiques sociales"], background: "#1c1a14", accent: "#f4c95d", font: "Georgia, serif", hub_radius: 30, node_radius: 14, labels: always }
branches:
  Tradition: { color: "#e0b04a", icon: crown,  policies: { Aristocratie: [], Oligarchie: [], Légalisme: [], Monarchie: [Aristocratie, Oligarchie] } }
  Liberté:   { color: "#5fa8e8", icon: users,  policies: { Collectivisme: [], Citoyenneté: [], Représentation: [Citoyenneté], Méritocratie: [Collectivisme, Représentation] } }
  Honneur:   { color: "#d9534f", icon: shield, policies: { Esprit guerrier: [], Discipline: [], Caste militaire: [Discipline], Code professionnel: [Esprit guerrier, Caste militaire] } }
finisher: { name: "Accomplissement <branche>", unlock: { type: all, nodes: "toutes les politiques de la colonne" } }
cross_rules:
  Légalisme: { unlock: [ { type: node, node: Tradition }, { type: any, nodes: [Aristocratie, Citoyenneté], min: 1 } ] }   # pont inter-colonnes
ideology: { name: Idéologie, unlock: [ { type: any, nodes: "les 3 accomplissements", min: 2 }, { type: metric, metric: era, op: ">=", value: 4 } ] }
```

Traduction : une colonne par branche (x fixe), rang 1 à ±70 de l'axe, descente de 110 par rang (y vers le bas) ;
plusieurs prérequis ⇒ `parent` = premier prérequis + `unlock all` + liens `path` depuis les autres ; accomplissement
enfant de la dernière politique avec `unlock all` ; Idéologie = hub sans parent (dépend de l'origin) combinant
`any` (min 2) et `metric`.

```json
{
  "tree": {"uid":"t_politiques","slug":"politiques","name":"Politiques sociales","layout":"free","theme":{"background":"#1c1a14","accent":"#f4c95d","line":"rgba(244,227,181,.5)","font":"Georgia, serif"},"settings":{"expandPush":1,"hubRadius":30,"nodeRadius":14,"showLabels":"always"}},
  "nodes": [
    {"uid":"civ_origin","parent":null,"kind":"origin","name":"Fondation","icon":"flag","x":0,"y":0,"theme":{"color":"#f4e3b5"},"conditions":[]},
    {"uid":"civ_h_tradition","parent":"civ_origin","kind":"hub","name":"Tradition","icon":"crown","x":-360,"y":140,"theme":{"color":"#e0b04a"},"conditions":[]},
    {"uid":"civ_p_tradition_1","parent":"civ_h_tradition","kind":"node","name":"Aristocratie","icon":null,"x":-430,"y":250,"theme":{"color":"#e0b04a"},"conditions":[]},
    {"uid":"civ_p_tradition_2","parent":"civ_h_tradition","kind":"node","name":"Oligarchie","icon":null,"x":-290,"y":250,"theme":{"color":"#e0b04a"},"conditions":[]},
    {"uid":"civ_p_tradition_3","parent":"civ_h_tradition","kind":"node","name":"Légalisme","icon":null,"x":-360,"y":360,"theme":{"color":"#e0b04a"},"conditions":[{"uid":"civ_c7","phase":"unlock","type":"node","params":{"node":"civ_h_tradition"}},{"uid":"civ_c8","phase":"unlock","type":"any","params":{"nodes":["civ_p_tradition_1","civ_p_liberte_2"],"min":1}}]},
    {"uid":"civ_p_tradition_4","parent":"civ_p_tradition_1","kind":"node","name":"Monarchie","icon":null,"x":-360,"y":470,"theme":{"color":"#e0b04a"},"conditions":[{"uid":"civ_c1","phase":"unlock","type":"all","params":{"nodes":["civ_p_tradition_1","civ_p_tradition_2"]}}]},
    {"uid":"civ_fin_tradition","parent":"civ_p_tradition_4","kind":"node","name":"Accomplissement Tradition","icon":"star","x":-360,"y":580,"theme":{"color":"#e0b04a","size":1.4},"conditions":[{"uid":"civ_c2","phase":"unlock","type":"all","params":{"nodes":["civ_p_tradition_1","civ_p_tradition_2","civ_p_tradition_3","civ_p_tradition_4"]}}]},
    {"uid":"civ_h_liberte","parent":"civ_origin","kind":"hub","name":"Liberté","icon":"users","x":0,"y":140,"theme":{"color":"#5fa8e8"},"conditions":[]},
    {"uid":"civ_p_liberte_1","parent":"civ_h_liberte","kind":"node","name":"Collectivisme","icon":null,"x":-70,"y":250,"theme":{"color":"#5fa8e8"},"conditions":[]},
    {"uid":"civ_p_liberte_2","parent":"civ_h_liberte","kind":"node","name":"Citoyenneté","icon":null,"x":70,"y":250,"theme":{"color":"#5fa8e8"},"conditions":[]},
    {"uid":"civ_p_liberte_3","parent":"civ_p_liberte_2","kind":"node","name":"Représentation","icon":null,"x":70,"y":360,"theme":{"color":"#5fa8e8"},"conditions":[]},
    {"uid":"civ_p_liberte_4","parent":"civ_p_liberte_1","kind":"node","name":"Méritocratie","icon":null,"x":0,"y":470,"theme":{"color":"#5fa8e8"},"conditions":[{"uid":"civ_c3","phase":"unlock","type":"all","params":{"nodes":["civ_p_liberte_1","civ_p_liberte_3"]}}]},
    {"uid":"civ_fin_liberte","parent":"civ_p_liberte_4","kind":"node","name":"Accomplissement Liberté","icon":"star","x":0,"y":580,"theme":{"color":"#5fa8e8","size":1.4},"conditions":[{"uid":"civ_c4","phase":"unlock","type":"all","params":{"nodes":["civ_p_liberte_1","civ_p_liberte_2","civ_p_liberte_3","civ_p_liberte_4"]}}]},
    {"uid":"civ_h_honneur","parent":"civ_origin","kind":"hub","name":"Honneur","icon":"shield","x":360,"y":140,"theme":{"color":"#d9534f"},"conditions":[]},
    {"uid":"civ_p_honneur_1","parent":"civ_h_honneur","kind":"node","name":"Esprit guerrier","icon":null,"x":290,"y":250,"theme":{"color":"#d9534f"},"conditions":[]},
    {"uid":"civ_p_honneur_2","parent":"civ_h_honneur","kind":"node","name":"Discipline","icon":null,"x":430,"y":250,"theme":{"color":"#d9534f"},"conditions":[]},
    {"uid":"civ_p_honneur_3","parent":"civ_p_honneur_2","kind":"node","name":"Caste militaire","icon":null,"x":430,"y":360,"theme":{"color":"#d9534f"},"conditions":[]},
    {"uid":"civ_p_honneur_4","parent":"civ_p_honneur_1","kind":"node","name":"Code professionnel","icon":null,"x":360,"y":470,"theme":{"color":"#d9534f"},"conditions":[{"uid":"civ_c5","phase":"unlock","type":"all","params":{"nodes":["civ_p_honneur_1","civ_p_honneur_3"]}}]},
    {"uid":"civ_fin_honneur","parent":"civ_p_honneur_4","kind":"node","name":"Accomplissement Honneur","icon":"star","x":360,"y":580,"theme":{"color":"#d9534f","size":1.4},"conditions":[{"uid":"civ_c6","phase":"unlock","type":"all","params":{"nodes":["civ_p_honneur_1","civ_p_honneur_2","civ_p_honneur_3","civ_p_honneur_4"]}}]},
    {"uid":"civ_ideologie","parent":null,"kind":"hub","name":"Idéologie","icon":"flame","x":0,"y":745,"theme":{"color":"#b18cff"},"conditions":[{"uid":"civ_c9","phase":"unlock","type":"any","params":{"nodes":["civ_fin_tradition","civ_fin_liberte","civ_fin_honneur"],"min":2}},{"uid":"civ_c10","phase":"unlock","type":"metric","params":{"metric":"era","op":">=","value":4}}]}
  ],
  "links": [
    {"uid":"civ_l_p_tradition_4_0","from":"civ_p_tradition_2","to":"civ_p_tradition_4","type":"path"},
    {"uid":"civ_l_fin_p_tradition_3","from":"civ_p_tradition_3","to":"civ_fin_tradition","type":"path"},
    {"uid":"civ_l_p_liberte_4_0","from":"civ_p_liberte_3","to":"civ_p_liberte_4","type":"path"},
    {"uid":"civ_l_p_honneur_4_0","from":"civ_p_honneur_3","to":"civ_p_honneur_4","type":"path"},
    {"uid":"civ_l_bridge","from":"civ_p_liberte_2","to":"civ_p_tradition_3","type":"visual"},
    {"uid":"civ_l_ideo_tradition","from":"civ_fin_tradition","to":"civ_ideologie","type":"path"},
    {"uid":"civ_l_ideo_liberte","from":"civ_fin_liberte","to":"civ_ideologie","type":"path"},
    {"uid":"civ_l_ideo_honneur","from":"civ_fin_honneur","to":"civ_ideologie","type":"path"}
  ],
  "metrics": {"era":2},
  "permissions": {"edit":true,"progress":false}
}
```
