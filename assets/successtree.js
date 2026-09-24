/*!
 * SuccessTree — moteur de rendu + éditeur d'arbres de progression gamifiés.
 * Vanilla ES2017, zéro dépendance, zéro build. Voir docs/SPEC.md (§4 format, §5 conditions, §7 front).
 */
(function (root, factory) {
  'use strict';
  var api = factory(root);
  if (typeof module === 'object' && module && module.exports) module.exports = api;
  if (root && typeof root === 'object') root.SuccessTree = api;
})(typeof window !== 'undefined' ? window : (typeof globalThis !== 'undefined' ? globalThis : this), function (win) {
  'use strict';

  var doc = win && win.document;
  var SVGNS = 'http://www.w3.org/2000/svg';
  var TAU = Math.PI * 2;
  var VERSION = '1.0.0';
  var instanceCounter = 0;

  /* ------------------------------------------------------------------ i18n */
  var FR = {
    loading: 'Chargement…', error: 'Erreur', retry: 'Réessayer', treeEmpty: 'Arbre vide',
    close: 'Fermer', recenter: 'Recentrer', zoomIn: 'Zoom avant', zoomOut: 'Zoom arrière',
    treeLabel: 'Arbre de progression {name}',
    status_locked: 'Verrouillé', status_available: 'Disponible', status_completed: 'Complété',
    kind_origin: 'Origine', kind_hub: 'Domaine', kind_node: 'Étape',
    reward: 'Récompense', conditions_unlock: 'Pour débloquer', conditions_complete: 'Pour valider',
    children: 'Étapes suivantes', noConditions: 'Aucune condition particulière.',
    cond_parent: 'Compléter l’étape parente : {name}', cond_parent_none: 'Aucun prérequis',
    cond_node: 'Compléter : {name}', cond_all: 'Compléter toutes ces étapes : {names}',
    cond_any: 'Compléter au moins {min} parmi : {names}', cond_children_all: 'Compléter toutes les étapes enfants',
    cond_children_min: 'Compléter au moins {min} étape(s) enfant(s)',
    cond_metric: 'Métrique {metric} {op} {value} (actuel : {current})',
    cond_manual: 'Validation manuelle par un responsable', cond_callback: 'Condition externe : {name}',
    cond_unknown: 'Condition « {type} »',
    complete: 'Valider cette étape', completedToast: 'Étape validée : {name}', xp: '+{xp} XP', badge: 'Badge : {badge}',
    cannotComplete: 'Cette étape ne peut pas encore être validée', progress: '{done} / {total} étapes', xpTotal: '{xp} XP',
    ed_toolbar: 'Outils d’édition', ed_add: 'Ajouter enfant', ed_delete: 'Supprimer', ed_link: 'Lier', ed_unlink: 'Délier',
    ed_heart: 'Preset Cœur', ed_export: 'Exporter JSON', ed_import: 'Importer JSON', ed_undo: 'Annuler',
    ed_save: 'Sauvegarder', ed_saved: 'Arbre sauvegardé', ed_savedLocal: 'Données transmises (mode hors-ligne)',
    ed_saving: 'Sauvegarde…', ed_properties: 'Propriétés', ed_name: 'Nom', ed_slug: 'Slug', ed_kind: 'Type',
    ed_parent: 'Parent', ed_none: '— aucun —', ed_icon: 'Icône', ed_iconText: 'ou texte / emoji', ed_noIcon: 'Aucune',
    ed_color: 'Couleur', ed_inherit: 'Hériter', ed_description: 'Description', ed_subtitle: 'Sous-titre',
    ed_central: 'Hub central (posé sur l’origine)', ed_conditions: 'Conditions', ed_addCondition: '+ Condition',
    ed_rawJson: 'JSON brut', ed_apply: 'Appliquer', ed_reward: 'Récompense (JSON)', ed_meta: 'Méta (JSON)',
    ed_theme: 'Thème (JSON)', ed_position: 'Position (x, y)', ed_auto: 'Auto', ed_sort: 'Ordre',
    ed_invalidJson: 'JSON invalide : {msg}', ed_invalidSlug: 'Slug invalide (a-z, 0-9, - et _)',
    ed_oneOrigin: 'Un seul nœud origine est autorisé', ed_linkSource: 'Lier : cliquez sur le nœud source',
    ed_linkPick: 'Lier : cliquez sur le nœud cible (Échap pour annuler)', ed_linkExists: 'Ce lien existe déjà',
    ed_linkCreated: 'Lien créé', ed_selectFirst: 'Sélectionnez d’abord un nœud', ed_unlinked: '{count} lien(s) supprimé(s)',
    ed_parentLink: 'Lien de parenté : changez le parent dans les propriétés',
    ed_confirmDelete: 'Supprimer « {name} » et ses {count} descendant(s) ?', ed_newNode: 'Nouvelle étape',
    ed_newHub: 'Nouveau domaine', ed_link_title: 'Lien', ed_linkType: 'Type de lien', ed_link_path: 'Chemin (prérequis)',
    ed_link_visual: 'Visuel seul', ed_importError: 'Import impossible : {msg}', ed_imported: 'Arbre importé',
    ed_nothingToUndo: 'Rien à annuler', ed_heartNeed: 'Aucun hub à positionner', ed_heartDone: 'Preset cœur appliqué',
    ed_hint: 'Glissez pour déplacer · Maj+clic pour lier · Suppr pour effacer', ed_noSelection: 'Sélectionnez un nœud pour éditer ses propriétés.',
    ed_invalidConditions: 'Conditions invalides : {msg}', ed_from: 'De', ed_to: 'Vers', remove: 'Retirer',
    phase_unlock: 'Déblocage', phase_complete: 'Validation',
    ctype_parent: 'Parent complété', ctype_node: 'Nœud complété', ctype_all: 'Tous complétés', ctype_any: 'Au moins N',
    ctype_children: 'Enfants complétés', ctype_metric: 'Métrique', ctype_manual: 'Manuelle', ctype_callback: 'Callback hôte',
    p_node: 'Nœud', p_nodes: 'Nœuds', p_min: 'Minimum', p_minAll: 'tous', p_metric: 'Clé', p_op: 'Op.', p_value: 'Valeur',
    p_name: 'Nom'
  };

  /* ------------------------------------------------------------------ icons (24x24, trait) */
  var ICONS = {
    target: [['circle', { cx: 12, cy: 12, r: 9 }], ['circle', { cx: 12, cy: 12, r: 5 }], ['circle', { cx: 12, cy: 12, r: 1.2 }]],
    heart: [['path', { d: 'M12 20.3s-7.4-4.5-9.1-9.2C1.7 7.8 3.9 4.6 7.2 4.6c2 0 3.6 1.1 4.8 2.9 1.2-1.8 2.8-2.9 4.8-2.9 3.3 0 5.5 3.2 4.3 6.5-1.7 4.7-9.1 9.2-9.1 9.2z' }]],
    star: [['path', { d: 'M12 3l2.7 5.8 6.3.7-4.7 4.3 1.3 6.2L12 16.9 6.4 20l1.3-6.2L3 9.5l6.3-.7z' }]],
    list: [['path', { d: 'M9 6.5h11M9 12h11M9 17.5h11' }], ['circle', { cx: 4.8, cy: 6.5, r: 1 }], ['circle', { cx: 4.8, cy: 12, r: 1 }], ['circle', { cx: 4.8, cy: 17.5, r: 1 }]],
    users: [['circle', { cx: 9, cy: 8.2, r: 3.2 }], ['path', { d: 'M3 19.5c0-3.3 2.7-5.6 6-5.6s6 2.3 6 5.6' }], ['circle', { cx: 16.8, cy: 9, r: 2.5 }], ['path', { d: 'M16.6 13.7c2.6.3 4.4 2.3 4.4 5.1' }]],
    filter: [['path', { d: 'M3.5 5h17l-6.5 7.6V19l-4 1.6v-8z' }]],
    database: [['ellipse', { cx: 12, cy: 5.6, rx: 7.5, ry: 2.8 }], ['path', { d: 'M4.5 5.6v12.8c0 1.5 3.4 2.8 7.5 2.8s7.5-1.3 7.5-2.8V5.6M4.5 12c0 1.5 3.4 2.8 7.5 2.8s7.5-1.3 7.5-2.8' }]],
    globe: [['circle', { cx: 12, cy: 12, r: 9 }], ['path', { d: 'M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z' }]],
    chart: [['path', { d: 'M4 3.5V20h16.5' }], ['path', { d: 'M7.5 15.5l4-4.6 3 3 5-6.4' }]],
    building: [['path', { d: 'M5 21V4h9v17M14 9.5h5V21M3 21h18M8 7.5h3M8 11h3M8 14.5h3M16.3 13h1.4M16.3 16.5h1.4' }]],
    check: [['circle', { cx: 12, cy: 12, r: 9 }], ['path', { d: 'M8 12.3l2.7 2.7 5.5-5.6' }]],
    layers: [['path', { d: 'M12 3.5l9 4.8-9 4.8-9-4.8z' }], ['path', { d: 'M3 12.4l9 4.8 9-4.8M3 16.4l9 4.8 9-4.8' }]],
    bolt: [['path', { d: 'M13 2.5L4.5 13.5H11l-1 8 8.5-11H12z' }]],
    book: [['path', { d: 'M4 19.5v-14c0-1.1.9-2 2-2h14v14H6c-1.1 0-2 .9-2 2s.9 2 2 2h14v-4' }]],
    flag: [['path', { d: 'M5 21.5V3.5M5 4.2h12.5l-2.6 4 2.6 4H5' }]],
    crown: [['path', { d: 'M3.5 8l4.3 4L12 5l4.2 7 4.3-4-1.8 10.5H5.3zM5.5 21h13' }]],
    gem: [['path', { d: 'M6.5 3.5h11l4 5.5L12 20.5 2.5 9zM2.5 9h19M9.5 3.5L8 9l4 11.5L16 9l-1.5-5.5' }]],
    shield: [['path', { d: 'M12 2.8l7.5 3v5.7c0 4.6-3.2 8.4-7.5 9.7-4.3-1.3-7.5-5.1-7.5-9.7V5.8z' }]],
    leaf: [['path', { d: 'M5.2 18.8C4.6 10.4 10 4.6 20 4.5c.2 9.8-5.4 15.3-14.8 14.3zM5 19l8.5-8.5' }]],
    flame: [['path', { d: 'M12 21.5c-3.9 0-6.5-2.6-6.5-6.2 0-3.9 3-5.7 3.7-9.8 2.6 1.6 3.4 3.9 3.4 5.9 1-.6 1.7-1.8 1.9-3.1 2.2 1.8 4 4.3 4 7 0 3.6-2.6 6.2-6.5 6.2z' }]],
    lock: [['rect', { x: 5, y: 10.5, width: 14, height: 10.5, rx: 2 }], ['path', { d: 'M8 10.5V7.5a4 4 0 0 1 8 0v3M12 14.5v2.5' }]],
    compass: [['circle', { cx: 12, cy: 12, r: 9 }], ['path', { d: 'M15.6 8.4l-2 5.2-5.2 2 2-5.2z' }]],
    rocket: [['path', { d: 'M12 2.8c3.4 2.3 5 5.8 5 9.7l-2.3 3.8H9.3L7 12.5c0-3.9 1.6-7.4 5-9.7zM9.3 16.3L7 20.5l3.2-1.4M14.7 16.3l2.3 4.2-3.2-1.4M12 18.3v3' }], ['circle', { cx: 12, cy: 9.6, r: 1.8 }]],
    coin: [['circle', { cx: 12, cy: 12, r: 9 }], ['path', { d: 'M12 6.8v10.4M14.4 9.3c-.4-.8-1.2-1.3-2.4-1.3-1.4 0-2.4.8-2.4 1.9 0 2.6 4.9 1.4 4.9 4.2 0 1.1-1.1 1.9-2.5 1.9-1.1 0-2-.5-2.4-1.4' }]]
  };
  var ICON_NAMES = Object.keys(ICONS);

  var PALETTE = ['#ff5c8a', '#ff9f43', '#ffd166', '#06d6a0', '#4cc9f0', '#7b8cff', '#c77dff', '#f72585', '#80ed99', '#ff7a59'];
  var COND_TYPES = ['parent', 'node', 'all', 'any', 'children', 'metric', 'manual', 'callback'];
  var OPS = ['>=', '>', '<=', '<', '==', '!='];
  var OP_GLYPH = { '>=': '≥', '<=': '≤', '!=': '≠', '==': '=', '>': '>', '<': '<' };
  var DEFAULT_SETTINGS = {
    expandPush: 1.6, hubRadius: 34, nodeRadius: 9, showLabels: 'hover', radius: 0, branchLength: 0,
    centralLength: 0, spread: 110, centralScale: 0.55, zoomMin: 0.15, zoomMax: 4, duration: 600, particles: 320
  };

  /* ------------------------------------------------------------------ utils */
  function fmt(s, v) { return String(s == null ? '' : s).replace(/\{(\w+)\}/g, function (m, k) { return v && v[k] != null ? String(v[k]) : ''; }); }
  function clamp(v, a, b) { return v < a ? a : (v > b ? b : v); }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function easeInOutCubic(t) { return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2; }
  function hash(str) {
    var h = 2166136261; str = String(str);
    for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); }
    h ^= h >>> 13; h = Math.imul(h, 0x5bd1e995); h ^= h >>> 15;
    return (h >>> 0) / 4294967296;
  }
  function clone(o) { return o == null ? o : JSON.parse(JSON.stringify(o)); }
  function isObj(o) { return !!o && typeof o === 'object' && !Array.isArray(o); }
  function num(v) { if (v === null || v === undefined || v === '') return null; v = +v; return isFinite(v) ? v : null; }
  function round1(v) { return Math.round(v * 10) / 10; }
  function uid(prefix) { return (prefix || 'n_') + Date.now().toString(36) + Math.random().toString(36).slice(2, 8); }
  function assign(t) { for (var i = 1; i < arguments.length; i++) { var s = arguments[i]; if (s) for (var k in s) if (Object.prototype.hasOwnProperty.call(s, k)) t[k] = s[k]; } return t; }
  function truncate(s, n) { s = String(s || ''); var a = Array.from(s); return a.length > n ? a.slice(0, n - 1).join('') + '…' : s; }

  var COLOR_HEX = /^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i;
  var COLOR_FN = /^(?:rgba?|hsla?)\(\s*[-+0-9.%]+(?:deg|turn|rad)?(?:\s*[,\s/]\s*[-+0-9.%]+(?:deg|turn|rad)?){2,3}\s*\)$/i;
  var COLOR_NAME = /^[a-z]{3,24}$/i;
  function safeColor(c) {
    if (typeof c !== 'string') return null;
    c = c.trim();
    if (c.length > 64) return null;
    return (COLOR_HEX.test(c) || COLOR_FN.test(c) || COLOR_NAME.test(c)) ? c : null;
  }
  function safeFont(f) { return typeof f === 'string' && /^[\w\s,'".-]{1,160}$/.test(f) ? f : null; }
  function toHex(c) {
    if (!c) return '#ffffff';
    if (/^#[0-9a-f]{6}$/i.test(c)) return c.toLowerCase();
    if (/^#[0-9a-f]{3}$/i.test(c)) return ('#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3]).toLowerCase();
    var m = /^rgba?\(\s*(\d+)[,\s]+(\d+)[,\s]+(\d+)/i.exec(c);
    if (m) return '#' + [m[1], m[2], m[3]].map(function (x) { return ('0' + (+x).toString(16)).slice(-2); }).join('');
    return '#ffffff';
  }

  function h(tag, cls, parent, text) {
    var e = doc.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    if (parent) parent.appendChild(e);
    return e;
  }
  function s(tag, attrs, parent) {
    var e = doc.createElementNS(SVGNS, tag);
    if (attrs) for (var k in attrs) if (attrs[k] != null) e.setAttribute(k, String(attrs[k]));
    if (parent) parent.appendChild(e);
    return e;
  }
  function setA(e, k, v) {
    var c = e.__st || (e.__st = {});
    if (c[k] !== v) { c[k] = v; if (v == null) e.removeAttribute(k); else e.setAttribute(k, v); }
  }
  function clear(e) { while (e.firstChild) e.removeChild(e.firstChild); }

  /** Dessine une icône (nom intégré ou texte court) centrée en 0,0 dans un <g> SVG. */
  function drawIcon(parent, name, size) {
    var g = s('g', { 'class': 'st-icon' }, parent);
    var def = ICONS[name];
    if (def) {
      g.setAttribute('transform', 'scale(' + (size / 24).toFixed(4) + ') translate(-12 -12)');
      def.forEach(function (d) { s(d[0], d[1], g); });
    } else if (name) {
      var t = s('text', { 'class': 'st-icon-text', 'text-anchor': 'middle', 'dominant-baseline': 'central', 'font-size': (size * 0.72).toFixed(1) }, g);
      t.textContent = Array.from(String(name)).slice(0, 3).join('');
    }
    return g;
  }
  /** Icône autonome pour le HTML (<svg viewBox 24>). */
  function iconSvg(name, cls) {
    var def = ICONS[name];
    if (!def) { var sp = h('span', (cls || '') + ' st-icon-txt'); sp.textContent = name ? Array.from(String(name)).slice(0, 3).join('') : ''; return sp; }
    var sv = s('svg', { viewBox: '0 0 24 24', 'aria-hidden': 'true', focusable: 'false', 'class': (cls || '') + ' st-ico' });
    def.forEach(function (d) { s(d[0], d[1], sv); });
    return sv;
  }
  var UI_ICONS = {
    plus: [['path', { d: 'M12 5v14M5 12h14' }]], minus: [['path', { d: 'M5 12h14' }]],
    recenter: [['circle', { cx: 12, cy: 12, r: 3 }], ['path', { d: 'M12 2.5v4M12 17.5v4M2.5 12h4M17.5 12h4' }]],
    close: [['path', { d: 'M6 6l12 12M18 6L6 18' }]],
    add: [['circle', { cx: 12, cy: 12, r: 8.5 }], ['path', { d: 'M12 8v8M8 12h8' }]],
    trash: [['path', { d: 'M4.5 7h15M9.5 7V4.5h5V7M6.5 7l1 13h9l1-13' }]],
    link: [['path', { d: 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.2 1.2M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.2-1.2' }]],
    unlink: [['path', { d: 'M15.5 12.7l3.2-3.2a4 4 0 0 0-5.7-5.7l-1.4 1.4M8.5 11.3l-3.2 3.2a4 4 0 0 0 5.7 5.7l1.4-1.4M4 4l16 16' }]],
    download: [['path', { d: 'M12 4v11M7.5 10.5L12 15l4.5-4.5M5 19.5h14' }]],
    upload: [['path', { d: 'M12 15.5V4.5M7.5 9L12 4.5 16.5 9M5 19.5h14' }]],
    undo: [['path', { d: 'M9 5L4.5 9.5 9 14M4.5 9.5H15a4.5 4.5 0 0 1 0 9h-3' }]],
    save: [['path', { d: 'M5 4h11l3 3v13H5zM8 4v5h7V4M8 20v-6h8v6' }]]
  };
  function uiIcon(name) {
    var sv = s('svg', { viewBox: '0 0 24 24', 'aria-hidden': 'true', focusable: 'false', 'class': 'st-ico' });
    (UI_ICONS[name] || ICONS[name] || []).forEach(function (d) { s(d[0], d[1], sv); });
    return sv;
  }

  /* ------------------------------------------------------------------ layouts (identiques au PHP) */
  function clean4(v) { v = Math.round(v * 10000) / 10000; return v === 0 ? 0 : v; }
  function heartPoint(t, R) {
    var x = 16 * Math.pow(Math.sin(t), 3);
    var y = -(13 * Math.cos(t) - 5 * Math.cos(2 * t) - 2 * Math.cos(3 * t) - Math.cos(4 * t));
    return { x: clean4(x / 16 * R), y: clean4(y / 16 * R) };
  }
  var layouts = {
    /**
     * Preset cœur (SPEC §8, identique à PHP Layout::heart) : 9 hubs à longueur d'arc égale sur la courbe
     * normalisée (1440 pas de t = π à 3π, cible L·k/9), k = 0 dans la pointe basse puis gauche en montant,
     * droite en redescendant. Renvoie un tableau de 9 points {x,y}, avec aussi .points (idem) et .center {0,0}
     * pour la parité avec le PHP (['points' => …, 'center' => …]).
     */
    heart: function (R) {
      R = num(R) || 420;
      var steps = 1440, lengths = [0], prev = heartPoint(Math.PI, 1);
      for (var i = 1; i <= steps; i++) {
        var cur = heartPoint(Math.PI + TAU * i / steps, 1);
        lengths[i] = lengths[i - 1] + Math.hypot(cur.x - prev.x, cur.y - prev.y);
        prev = cur;
      }
      var total = lengths[steps], pts = [], j = 0;
      for (var k = 0; k < 9; k++) {
        var target = total * k / 9;
        while (j < steps && lengths[j + 1] < target) j++;
        var seg = lengths[j + 1] - lengths[j];
        var frac = seg > 0 ? (target - lengths[j]) / seg : 0;
        pts.push(heartPoint(Math.PI + TAU * (j + frac) / steps, R));
      }
      pts.points = pts.slice();
      pts.center = { x: 0, y: 0 };
      return pts;
    },
    /** n points réguliers sur un cercle de rayon R (défaut 300), à partir de start (radians, défaut −π/2 = haut). */
    radial: function (n, R, start) {
      n = Math.max(0, Math.floor(num(n) || 0)); R = num(R); if (R == null) R = 300;
      start = num(start); if (start == null) start = -Math.PI / 2;
      var pts = [];
      for (var i = 0; i < n; i++) { var a = start + TAU * i / Math.max(1, n); pts.push({ x: clean4(Math.cos(a) * R), y: clean4(Math.sin(a) * R) }); }
      return pts;
    },
    /** Anneaux concentriques : counts[i] points sur l'anneau i (rayon (i+1)·spacing, défaut 160), anneaux impairs décalés d'un demi-pas. */
    ring: function (counts, spacing) {
      if (!Array.isArray(counts)) counts = [num(counts) || 0];
      spacing = num(spacing) || 160;
      var out = [];
      counts.forEach(function (n, i) {
        n = Math.floor(num(n) || 0); if (n <= 0) return;
        var off = (i % 2) * (Math.PI / n);
        layouts.radial(n, (i + 1) * spacing, -Math.PI / 2 + off).forEach(function (p) { p.ring = i; out.push(p); });
      });
      return out;
    }
  };
  /** Répartit n éléments sur des anneaux de capacité 6, 12, 18… (utilisé par layout 'ring'). */
  function ringCounts(n) { var c = [], cap = 6; while (n > 0) { var k = Math.min(cap, n); c.push(k); n -= k; cap += 6; } return c; }

  /* ------------------------------------------------------------------ données & statuts (§4, §5) */
  function normalizeData(d) {
    d = clone(d || {}) || {};
    if (isObj(d.data) && Array.isArray(d.data.nodes)) d = d.data;
    var out = {
      tree: isObj(d.tree) ? d.tree : {},
      nodes: Array.isArray(d.nodes) ? d.nodes : [],
      links: Array.isArray(d.links) ? d.links : [],
      subject: d.subject != null ? d.subject : null,
      metrics: isObj(d.metrics) ? d.metrics : {},
      permissions: isObj(d.permissions) ? d.permissions : {}
    };
    for (var k in d) if (!(k in out)) out[k] = d[k];
    var seen = {};
    out.nodes = out.nodes.filter(function (n) {
      if (!isObj(n)) return false;
      if (n.uid == null || n.uid === '') n.uid = uid('n_');
      n.uid = String(n.uid);
      if (seen[n.uid]) return false;
      seen[n.uid] = true;
      if (n.parent === undefined && n.parent_uid !== undefined) n.parent = n.parent_uid;
      n.parent = n.parent == null || n.parent === '' ? null : String(n.parent);
      if (['origin', 'hub', 'node'].indexOf(n.kind) < 0) n.kind = 'node';
      n.name = n.name == null ? '' : String(n.name);
      if (n.x === undefined && n.pos_x !== undefined) n.x = n.pos_x;
      if (n.y === undefined && n.pos_y !== undefined) n.y = n.pos_y;
      n.x = num(n.x); n.y = num(n.y);
      n.sort = num(n.sort) || 0;
      if (!isObj(n.theme)) n.theme = {};
      if (!isObj(n.meta)) n.meta = {};
      if (!isObj(n.reward)) n.reward = n.reward == null ? {} : { value: n.reward };
      if (!Array.isArray(n.conditions)) n.conditions = [];
      n.conditions = n.conditions.filter(isObj).map(function (c) {
        if (!c.uid) c.uid = uid('c_');
        c.phase = c.phase === 'complete' ? 'complete' : 'unlock';
        if (!isObj(c.params)) c.params = {};
        c.type = String(c.type || 'parent');
        return c;
      });
      return true;
    });
    out.links = out.links.filter(function (l) {
      if (!isObj(l)) return false;
      if (l.from === undefined && l.from_uid !== undefined) l.from = l.from_uid;
      if (l.to === undefined && l.to_uid !== undefined) l.to = l.to_uid;
      if (!l.from || !l.to || !seen[l.from] || !seen[l.to]) return false;
      l.from = String(l.from); l.to = String(l.to);
      if (!l.uid) l.uid = uid('l_');
      l.type = l.type === 'visual' ? 'visual' : 'path';
      return true;
    });
    return out;
  }

  /** Indexe l'arbre : parents effectifs (hub/nœud sans parent ⇒ origin), enfants triés, hub de rattachement. */
  function buildIndex(data) {
    var by = {}, order = {}, origin = null;
    data.nodes.forEach(function (n, i) { by[n.uid] = n; order[n.uid] = i; if (n.kind === 'origin' && !origin) origin = n; });
    var eff = {}, kids = {};
    data.nodes.forEach(function (n) {
      var p = n.parent && by[n.parent] && n.parent !== n.uid ? n.parent : null;
      if (!p && origin && n !== origin) p = origin.uid;
      eff[n.uid] = p;
    });
    // casse les cycles
    data.nodes.forEach(function (n) {
      var seen = {}, cur = n.uid;
      while (cur) {
        if (seen[cur]) { eff[cur] = origin && cur !== origin.uid ? origin.uid : null; if (eff[cur] && seen[eff[cur]]) eff[cur] = null; break; }
        seen[cur] = true; cur = eff[cur];
      }
    });
    data.nodes.forEach(function (n) { var p = eff[n.uid]; if (p) (kids[p] || (kids[p] = [])).push(n.uid); });
    Object.keys(kids).forEach(function (p) {
      kids[p].sort(function (a, b) { return (by[a].sort - by[b].sort) || (order[a] - order[b]); });
    });
    var hubOf = {};
    data.nodes.forEach(function (n) {
      var cur = n.uid, guard = 0;
      while (cur && guard++ < 10000) { if (by[cur].kind === 'hub') { hubOf[n.uid] = cur; break; } cur = eff[cur]; }
      if (!hubOf[n.uid]) hubOf[n.uid] = null;
    });
    // parité PHP : enfants « bruts » (parent_uid) et sources des liens 'path' entrants
    var rawKids = {}, pathIn = {};
    data.nodes.forEach(function (n) { if (n.parent && by[n.parent]) (rawKids[n.parent] || (rawKids[n.parent] = [])).push(n.uid); });
    (data.links || []).forEach(function (l) {
      if (l.type === 'path' && by[l.from] && by[l.to]) (pathIn[l.to] || (pathIn[l.to] = [])).push(l.from);
    });
    return { by: by, order: order, origin: origin, eff: eff, kids: kids, hubOf: hubOf, rawKids: rawKids, pathIn: pathIn };
  }

  /** Prérequis implicites (condition 'parent') : parent_uid + sources des liens 'path' entrants ; sinon l'origine. */
  function prerequisites(idx, u) {
    var n = idx.by[u]; if (!n || n.kind === 'origin') return [];
    var pre = [];
    if (n.parent && idx.by[n.parent]) pre.push(n.parent);
    (idx.pathIn[u] || []).forEach(function (f) { if (pre.indexOf(f) < 0) pre.push(f); });
    if (!pre.length && idx.origin && idx.origin.uid !== u) pre.push(idx.origin.uid);
    return pre;
  }

  function isDone(n) { return !!n && (n.status === 'completed' || !!(n.progress && n.progress.completed_at)); }

  /**
   * Évalue une condition (identique à PHP ProgressEngine::evalCondition).
   * mode: 'unlock' | 'auto' | 'complete' (validation explicite : 'manual' devient vrai).
   * Renvoie true/false, ou null pour un 'callback' en ligne (évalué côté serveur uniquement).
   */
  function evalCondition(c, n, ctx, mode) {
    var p = isObj(c.params) ? c.params : {}, done = ctx.done, idx = ctx.idx, list, cnt, min;
    switch (c.type) {
      case 'parent': return prerequisites(idx, n.uid).every(function (u) { return !!done[u]; });
      case 'node': return typeof p.node === 'string' && !!done[p.node];
      case 'all':
        if (!Array.isArray(p.nodes) || !p.nodes.length) return false;
        return p.nodes.every(function (u) { return typeof u === 'string' && !!done[u]; });
      case 'any':
        if (!Array.isArray(p.nodes) || !p.nodes.length) return false;
        min = num(p.min) == null ? 1 : Math.max(1, Math.trunc(num(p.min)));
        return p.nodes.filter(function (u) { return typeof u === 'string' && !!done[u]; }).length >= min;
      case 'children':
        list = idx.rawKids[n.uid] || [];
        if (!list.length) return false;
        cnt = list.filter(function (u) { return !!done[u]; }).length;
        return num(p.min) == null ? cnt === list.length : cnt >= Math.max(1, Math.trunc(num(p.min)));
      case 'metric': {
        if (typeof p.metric !== 'string') return false;
        var v = num(ctx.metrics[p.metric]); if (v == null) v = 0;
        var target = num(p.value); if (target == null) target = 0;
        var eps = 1e-9;
        switch (p.op == null ? '>=' : String(p.op)) {
          case '>': return v > target; case '>=': return v >= target - eps; case '<': return v < target;
          case '<=': return v <= target + eps; case '==': return Math.abs(v - target) < eps; case '!=': return Math.abs(v - target) >= eps;
        }
        return false;
      }
      case 'manual': return mode === 'complete';
      case 'callback': return ctx.offline ? false : null;
      default: return false;
    }
  }

  /** Calcule les statuts locked/available/completed (§5). evaluate=true ⇒ auto-complétions. */
  function computeStatuses(data, idx, opts) {
    opts = opts || {};
    var done = {}, ctx = { done: done, idx: idx, metrics: data.metrics || {}, offline: opts.offline !== false };
    data.nodes.forEach(function (n) { if (isDone(n)) done[n.uid] = true; });
    function unlocked(n) {
      if (n.kind === 'origin') return true;
      var conds = n.conditions.filter(function (c) { return c.phase !== 'complete'; });
      if (!conds.length) conds = [{ type: 'parent', params: {} }];
      return conds.every(function (c) { return evalCondition(c, n, ctx, 'unlock') === true; });
    }
    if (opts.evaluate) {
      var changed = true, guard = 0;
      while (changed && guard++ < 1000) {
        changed = false;
        data.nodes.forEach(function (n) {
          if (done[n.uid] || !unlocked(n)) return;
          var cc = n.conditions.filter(function (c) { return c.phase === 'complete'; });
          if (!cc.length || cc.some(function (c) { return c.type === 'manual'; })) return;
          if (cc.every(function (c) { return evalCondition(c, n, ctx, 'auto') === true; })) {
            done[n.uid] = true; n.progress = assign({}, n.progress, { completed_at: new Date().toISOString(), auto: true });
            changed = true;
          }
        });
      }
    }
    var out = {};
    data.nodes.forEach(function (n) { out[n.uid] = done[n.uid] ? 'completed' : (unlocked(n) ? 'available' : 'locked'); });
    out.__ctx = ctx;
    return out;
  }

  /* ------------------------------------------------------------------ View */
  function View(el, opts) {
    this.el = el;
    this.opts = opts = assign({}, opts || {});
    this.id = 'st' + (++instanceCounter);
    this.t = assign({}, FR, opts.i18n || {});
    this.handlers = {};
    this.data = null;
    this.idx = null;
    this.base = {}; this.dir = {}; this.disp = {};
    this.cam = { x: 0, y: 0, k: 1 }; this.W = 0; this.H = 0;
    this.slots = { cur: { uid: null, t: 0 }, prev: { uid: null, t: 0 } };
    this.tweens = {};
    this.editing = false; this.selected = null; this.selectedLink = null; this.linkMode = 0;
    this.undoStack = []; this.dirtyEdits = false;
    this.nodeEls = {}; this.linkEls = {}; this.labelEls = {}; this.gradients = {};
    this.ptrs = {}; this.gesture = null;
    this.bursts = []; this.particles = null;
    this.slug = opts.tree || null;
    this.offline = !opts.endpoint;
    this.reduced = !!(win.matchMedia && win.matchMedia('(prefers-reduced-motion: reduce)').matches);
    this.visible = true;
    this._raf = 0; this._destroyed = false;
    this._dirty = true; this._dirtyCam = true; this._bgDirty = true;
    this._build();
    var self = this;
    this.api = {
      reload: function () { return self.reload(); },
      focus: function (u) { self.focus(u); return self.api; },
      expand: function (u) { self.expand(u); return self.api; },
      collapse: function () { self.collapse(); return self.api; },
      getData: function () { return self.getData(); },
      setData: function (d) { self.setData(d, { reset: true }); return self.api; },
      setEdit: function (b) { self.setEdit(b); return self.api; },
      destroy: function () { self.destroy(); },
      on: function (e, fn) { return self.on(e, fn); },
      off: function (e, fn) { self.off(e, fn); return self.api; },
      open: function (u) { self.openDetail(u); return self.api; },
      close: function () { self.closePanel(); return self.api; },
      complete: function (u) { return self.complete(u); },
      metric: function (k, v, mode) { return self.metric(k, v, mode); },
      save: function () { return self.save(); },
      undo: function () { self.undo(); return self.api; },
      recenter: function () { self.recenter(); return self.api; },
      applyPreset: function (name) { self.applyPreset(name || 'heart'); return self.api; },
      get element() { return self.el; },
      get editing() { return self.editing; }
    };
    el.__successtree = this.api;
    this._loop = this._loop.bind(this);
    this._raf = win.requestAnimationFrame(this._loop);
    this.reload();
  }

  View.prototype.on = function (evt, fn) {
    var self = this;
    (this.handlers[evt] || (this.handlers[evt] = [])).push(fn);
    return function () { self.off(evt, fn); };
  };
  View.prototype.off = function (evt, fn) {
    var l = this.handlers[evt]; if (!l) return;
    var i = l.indexOf(fn); if (i >= 0) l.splice(i, 1);
  };
  View.prototype.emit = function (evt, a, b) {
    var l = (this.handlers[evt] || []).slice();
    for (var i = 0; i < l.length; i++) { try { l[i].call(this.api, a, b); } catch (e) { if (win.console) console.error(e); } }
    try { this.el.dispatchEvent(new win.CustomEvent('successtree:' + evt, { detail: { value: a, extra: b, view: this.api } })); } catch (e) { /* noop */ }
  };

  /* ---------- construction du DOM */
  View.prototype._build = function () {
    var el = this.el, t = this.t, self = this;
    el.classList.add('st-host');
    if (el.dataset && el.dataset.height && !el.style.height) el.style.height = el.dataset.height;
    if (this.opts.height && !el.style.height) el.style.height = this.opts.height;
    clear(el);
    var root = this.root = h('div', 'st-root', el);
    if (this.reduced) root.classList.add('st-reduced');
    this.cvStars = h('canvas', 'st-canvas st-stars', root);
    this.cvFx = h('canvas', 'st-canvas st-fx', root);
    this.cvStars.setAttribute('aria-hidden', 'true'); this.cvFx.setAttribute('aria-hidden', 'true');
    var svgEl = this.svg = s('svg', { 'class': 'st-svg', role: 'group', tabindex: '0', 'aria-roledescription': 'carte' }, root);
    var defs = this.defs = s('defs', null, svgEl);
    var f = s('filter', { id: this.id + '-glow', x: '-150%', y: '-150%', width: '400%', height: '400%' }, defs);
    s('feGaussianBlur', { stdDeviation: '4', result: 'b' }, f);
    var m = s('feMerge', null, f); s('feMergeNode', { 'in': 'b' }, m); s('feMergeNode', { 'in': 'b' }, m); s('feMergeNode', { 'in': 'SourceGraphic' }, m);
    var f2 = s('filter', { id: this.id + '-soft', x: '-100%', y: '-100%', width: '300%', height: '300%' }, defs);
    s('feGaussianBlur', { stdDeviation: '6' }, f2);
    this.world = s('g', { 'class': 'st-world' }, svgEl);
    this.gLinks = s('g', { 'class': 'st-links' }, this.world);
    this.gNodes = s('g', { 'class': 'st-nodes' }, this.world);
    this.gLabels = s('g', { 'class': 'st-labels', 'aria-hidden': 'true' }, svgEl);

    this.header = h('div', 'st-header', root);
    this.titleEl = h('div', 'st-title', this.header);
    this.subtitleEl = h('div', 'st-subtitle', this.header);
    this.stats = h('div', 'st-stats', root);
    this.stats.setAttribute('aria-live', 'polite');
    this.tabsEl = h('nav', 'st-tabs', root); this.tabsEl.hidden = true;

    var ctr = h('div', 'st-controls', root);
    function ctl(icon, label, fn) {
      var b = h('button', 'st-ctl', ctr); b.type = 'button'; b.setAttribute('aria-label', label); b.title = label;
      b.appendChild(uiIcon(icon)); b.addEventListener('click', fn); return b;
    }
    ctl('plus', t.zoomIn, function () { self.zoomBy(1.35); });
    ctl('minus', t.zoomOut, function () { self.zoomBy(1 / 1.35); });
    ctl('recenter', t.recenter, function () { self.collapse(); self.recenter(); });

    this.toolbar = h('div', 'st-toolbar', root); this.toolbar.hidden = true;
    this.toolbar.setAttribute('role', 'toolbar'); this.toolbar.setAttribute('aria-label', t.ed_toolbar);
    this.hint = h('div', 'st-hint', root); this.hint.hidden = true;

    this.tooltip = h('div', 'st-tooltip', root); this.tooltip.setAttribute('role', 'tooltip'); this.tooltip.hidden = true;
    this.panel = h('aside', 'st-panel', root);
    this.panel.setAttribute('role', 'dialog'); this.panel.setAttribute('aria-modal', 'false');
    this.panel.setAttribute('aria-labelledby', this.id + '-ptitle'); this.panel.hidden = true;
    this.toastEl = h('div', 'st-toast', root); this.toastEl.setAttribute('role', 'status'); this.toastEl.setAttribute('aria-live', 'polite');
    this.loadingEl = h('div', 'st-loading', root, t.loading);
    this.fileInput = h('input', 'st-file', root); this.fileInput.type = 'file'; this.fileInput.accept = 'application/json,.json'; this.fileInput.hidden = true;
    this.fileInput.addEventListener('change', function () { self._importFile(self.fileInput.files && self.fileInput.files[0]); self.fileInput.value = ''; });

    this._bindEvents();
    var RO = win.ResizeObserver;
    if (RO) { this.ro = new RO(function () { self._resize(); }); this.ro.observe(root); }
    else { this._onWinResize = function () { self._resize(); }; win.addEventListener('resize', this._onWinResize); }
    var IO = win.IntersectionObserver;
    if (IO) { this.io = new IO(function (en) { self.visible = en[0] ? en[0].isIntersecting : true; }); this.io.observe(root); }
    this._resize();
  };

  View.prototype._resize = function () {
    var r = this.root.getBoundingClientRect();
    var W = Math.max(1, Math.round(r.width)), H = Math.max(1, Math.round(r.height));
    var first = !this.W;
    var changed = W !== this.W || H !== this.H;
    this.W = W; this.H = H;
    this.dpr = Math.min(2, win.devicePixelRatio || 1);
    if (changed) {
      [this.cvStars, this.cvFx].forEach(function (c) { c.width = Math.round(W * this.dpr); c.height = Math.round(H * this.dpr); }, this);
      this.svg.setAttribute('viewBox', '0 0 ' + W + ' ' + H);
      this._makeStars();
      this.mobile = W < 640;
      this.root.classList.toggle('st-mobile', this.mobile);
      if (this.data && (first || !this._userMoved) && !this.slots.cur.uid) this._fitAll(false);
      this._dirtyCam = true; this._bgDirty = true;
    }
  };

  /* ---------- chargement */
  View.prototype.reload = function () {
    var self = this, o = this.opts;
    this._setLoading(true);
    var p;
    if (o.data && !this._loadedOnce) p = Promise.resolve(o.data);
    else if (o.src && this.offline) p = win.fetch(o.src, { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    else if (!this.offline) p = this._api('tree');
    else p = Promise.resolve(this.data ? this.getData() : (o.data || { nodes: [] }));
    return p.then(function (d) {
      self._setLoading(false);
      var first = !self._loadedOnce;
      self._loadedOnce = true;
      self.setData(d, { reset: first });
      if (first) {
        if (o.tabs) self._setupTabs(o.tabs);
        if (o.edit) self.setEdit(true);
        self.emit('ready', self.api);
        if (typeof o.onReady === 'function') o.onReady(self.api);
      }
      return self.api;
    }).catch(function (err) {
      self._setLoading(false, err);
      self._error(err);
      return self.api;
    });
  };

  View.prototype._setLoading = function (on, err) {
    var el = this.loadingEl, self = this;
    el.hidden = !on && !err;
    clear(el);
    if (on) el.textContent = this.t.loading;
    else if (err) {
      h('div', 'st-loading-msg', el, this.t.error + ' : ' + (err && err.message ? err.message : String(err)));
      var b = h('button', 'st-btn', el, this.t.retry); b.type = 'button';
      b.addEventListener('click', function () { self.reload(); });
    }
  };

  View.prototype._error = function (err) {
    var msg = err && err.message ? err.message : String(err);
    this.toast(msg, 'error');
    this.emit('error', err);
    if (typeof this.opts.onError === 'function') this.opts.onError(err);
  };

  View.prototype._url = function (action) {
    var ep = String(this.opts.endpoint || '');
    var q = 'action=' + encodeURIComponent(action);
    if (this.slug && action !== 'list') q += '&tree=' + encodeURIComponent(this.slug);
    return ep + (ep.indexOf('?') >= 0 ? '&' : '?') + q;
  };

  View.prototype._api = function (action, body) {
    var init = {
      method: body !== undefined ? 'POST' : 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    };
    if (body !== undefined) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(body); }
    return win.fetch(this._url(action), init).then(function (r) {
      return r.text().then(function (txt) {
        var j = null;
        try { j = JSON.parse(txt); } catch (e) { throw new Error('Réponse invalide (HTTP ' + r.status + ')'); }
        if (!j || j.ok === false || !r.ok) throw new Error((j && j.error) || ('HTTP ' + r.status));
        return j.data;
      });
    });
  };

  /* ---------- données */
  View.prototype.setData = function (d, o) {
    o = o || {};
    var prevSel = this.selected;
    this.data = normalizeData(d);
    if (!this.slug && this.data.tree.slug) this.slug = this.data.tree.slug;
    this.settings = assign({}, DEFAULT_SETTINGS, isObj(this.data.tree.settings) ? this.data.tree.settings : {}, this.opts.settings || {});
    this.settings.expandPush = num(this.settings.expandPush) || 1.6;
    this.settings.hubRadius = num(this.settings.hubRadius) || 34;
    this.settings.nodeRadius = num(this.settings.nodeRadius) || 9;
    this._applyTheme();
    this._recompute(!!o.evaluate);
    if (this.selected && !this.idx.by[this.selected]) this.selected = null;
    if (o.reset) {
      this.slots.cur = { uid: null, t: 0 }; this.slots.prev = { uid: null, t: 0 };
      this.selected = null; this.selectedLink = null;
      this.closePanel(true);
      this._fitAll(false);
    } else if (this.slots.cur.uid && !this.idx.by[this.slots.cur.uid]) {
      this.slots.cur = { uid: null, t: 0 };
    }
    this._renderHeader();
    if (this.panelUid && this.idx.by[this.panelUid]) this._refreshPanel();
    else if (this.panelUid) this.closePanel(true);
    if (this.editing) { this._renderToolbar(); if (prevSel !== this.selected) this._refreshPanel(); }
    this.emit('load', this.api);
    return this;
  };

  View.prototype._recompute = function (evaluate) {
    this.idx = buildIndex(this.data);
    var local = computeStatuses(this.data, this.idx, { offline: this.offline, evaluate: evaluate && this.offline });
    var self = this;
    this.data.nodes.forEach(function (n) {
      if (self.offline || !n.status || ['locked', 'available', 'completed'].indexOf(n.status) < 0 || n.__localStatus) {
        n.status = local[n.uid];
        if (!self.offline) Object.defineProperty(n, '__localStatus', { value: true, enumerable: false, configurable: true, writable: true });
      }
    });
    this._colors();
    this._layout();
    this._sync();
    this._dirty = true;
  };

  View.prototype._applyTheme = function () {
    var th = assign({}, isObj(this.data.tree.theme) ? this.data.tree.theme : {}, this.opts.theme || {});
    var map = { background: '--st-bg', background2: '--st-bg2', accent: '--st-accent', line: '--st-line', hub: '--st-hub', hubInk: '--st-hub-ink', text: '--st-text' };
    var st = this.root.style;
    Object.keys(map).forEach(function (k) { var c = safeColor(th[k]); if (c) st.setProperty(map[k], c); else st.removeProperty(map[k]); });
    var ft = safeFont(th.font || th.fontTitle); if (ft) st.setProperty('--st-font-title', ft); else st.removeProperty('--st-font-title');
    var fb = safeFont(th.fontBody); if (fb) st.setProperty('--st-font', fb); else st.removeProperty('--st-font');
    var cs = win.getComputedStyle ? win.getComputedStyle(this.root) : null;
    this.accent = (cs && safeColor(cs.getPropertyValue('--st-accent'))) || '#ff7a59';
    this._bgDirty = true;
  };

  View.prototype._colors = function () {
    var idx = this.idx, colors = {}, hubI = 0, self = this;
    var own = function (n) { return safeColor(n.theme && n.theme.color); };
    var order = [];
    // parcours BFS depuis les racines pour l'héritage
    var roots = this.data.nodes.filter(function (n) { return !idx.eff[n.uid]; });
    var q = roots.map(function (n) { return n.uid; }), seen = {};
    while (q.length) { var u = q.shift(); if (seen[u]) continue; seen[u] = true; order.push(u); (idx.kids[u] || []).forEach(function (k) { q.push(k); }); }
    this.data.nodes.forEach(function (n) { if (!seen[n.uid]) order.push(n.uid); });
    order.forEach(function (u) {
      var n = idx.by[u], c = own(n);
      if (!c) {
        if (n.kind === 'origin') c = '#ffffff';
        else if (n.kind === 'hub') { var p = idx.eff[u]; c = p && idx.by[p].kind !== 'origin' && colors[p] ? colors[p] : PALETTE[hubI++ % PALETTE.length]; }
        else { var pp = idx.eff[u]; c = pp && colors[pp] && idx.by[pp].kind !== 'origin' ? colors[pp] : self.accent; }
      } else if (n.kind === 'hub') hubI++;
      colors[u] = c;
    });
    this.colors = colors;
  };

  /* ---------- auto-layout */
  View.prototype._layout = function () {
    var S = this.settings, idx = this.idx, data = this.data;
    var pos = {}, dir = {}, dep = {};
    var O = idx.origin ? { x: num(idx.origin.x) || 0, y: num(idx.origin.y) || 0 } : { x: 0, y: 0 };
    this.O = O;
    if (idx.origin) pos[idx.origin.uid] = { x: O.x, y: O.y };
    data.nodes.forEach(function (n) { if (n.x != null && n.y != null) pos[n.uid] = { x: n.x, y: n.y }; });
    var R = num(S.radius) || 420;
    var topHubs = data.nodes.filter(function (n) {
      var p = idx.eff[n.uid];
      return n.kind === 'hub' && !pos[n.uid] && (!p || (idx.origin && p === idx.origin.uid));
    }).sort(function (a, b) { return (a.sort - b.sort) || (idx.order[a.uid] - idx.order[b.uid]); });
    var layoutName = String(data.tree.layout || 'free');
    var centralUid = null;
    var flagged = data.nodes.filter(function (n) { return n.kind === 'hub' && n.meta && n.meta.central === true; })[0];
    if (flagged) centralUid = flagged.uid;
    if (topHubs.length) {
      if (layoutName === 'heart') {
        var others = topHubs.filter(function (n) { return n.uid !== centralUid; });
        if (!centralUid && others.length >= 10) { centralUid = others[9].uid; others.splice(9, 1); }
        var hp = layouts.heart(R);
        others.forEach(function (n, i) {
          if (i < 9) pos[n.uid] = { x: O.x + hp[i].x, y: O.y + hp[i].y };
          else { var a = -Math.PI / 2 + TAU * (i - 9) / Math.max(1, others.length - 9); pos[n.uid] = { x: O.x + Math.cos(a) * R * 1.45, y: O.y + Math.sin(a) * R * 1.45 }; }
        });
        if (centralUid && !pos[centralUid]) pos[centralUid] = { x: O.x, y: O.y };
      } else {
        var ringHubs = topHubs.filter(function (n) { return n.uid !== centralUid || !!pos[n.uid]; });
        if (centralUid && !pos[centralUid]) pos[centralUid] = { x: O.x, y: O.y };
        var rc = ringCounts(ringHubs.length);
        var pts = layoutName === 'ring' ? layouts.ring(rc, R / rc.length) : layouts.radial(ringHubs.length, R);
        ringHubs.forEach(function (n, i) { pos[n.uid] = { x: O.x + pts[i].x, y: O.y + pts[i].y }; });
      }
    }
    // hub central : flag, sinon hub le plus proche de l'origine (< 0.15 R)
    var Rh = 0;
    data.nodes.forEach(function (n) { if (n.kind === 'hub' && pos[n.uid]) Rh = Math.max(Rh, Math.hypot(pos[n.uid].x - O.x, pos[n.uid].y - O.y)); });
    Rh = Rh || R;
    this.R = Rh;
    if (!centralUid) {
      var best = null, bd = Infinity;
      data.nodes.forEach(function (n) {
        if (n.kind !== 'hub' || !pos[n.uid]) return;
        var dd = Math.hypot(pos[n.uid].x - O.x, pos[n.uid].y - O.y);
        if (dd < bd) { bd = dd; best = n.uid; }
      });
      if (best && bd < 0.15 * Rh) centralUid = best;
    }
    this.central = centralUid;

    var L0 = num(S.branchLength) || R * 0.23;
    var Lc = num(S.centralLength) || R * 0.32;
    var spread0 = (num(S.spread) || 110) * Math.PI / 180;
    var queue = data.nodes.filter(function (n) { return !idx.eff[n.uid]; }).map(function (n) { return n.uid; });
    var visited = {};
    // positions de secours pour racines sans position
    queue.forEach(function (u, i) { if (!pos[u]) pos[u] = { x: O.x + i * 60, y: O.y }; });
    while (queue.length) {
      var p = queue.shift();
      if (visited[p]) continue;
      visited[p] = true;
      var P = pos[p], pn = idx.by[p];
      var list = idx.kids[p] || [];
      var free = list.filter(function (u) { return !pos[u]; });
      if (free.length) {
        var full = pn.kind === 'origin' || p === centralUid || Math.hypot(P.x - O.x, P.y - O.y) < 1;
        var depth = (pn.kind === 'hub' || pn.kind === 'origin') ? 1 : (dep[p] || 1) + 1;
        var base;
        if (!full) {
          if (pn.kind === 'hub') base = Math.atan2(P.y - O.y, P.x - O.x);
          else if (dir[p] != null) base = dir[p];
          else {
            var gp = idx.eff[p] && pos[idx.eff[p]];
            base = gp && (gp.x !== P.x || gp.y !== P.y) ? Math.atan2(P.y - gp.y, P.x - gp.x) : Math.atan2(P.y - O.y, P.x - O.x);
            if (!isFinite(base)) base = -Math.PI / 2;
          }
        }
        var n = free.length;
        var L = (full ? Lc : L0) * Math.pow(0.8, depth - 1);
        var perKid = (34 * Math.PI / 180) * Math.pow(0.85, depth - 1);
        var spread = Math.min(spread0 * Math.pow(0.75, depth - 1), (n - 1) * perKid);
        for (var i = 0; i < n; i++) {
          var c = free[i];
          var j1 = hash(c), j2 = hash(c + ':l');
          var a;
          if (full) a = -Math.PI / 2 + TAU * i / n + (n > 1 ? Math.PI / n : 0) * (pn.kind === 'origin' ? 0 : 1) + (j1 - 0.5) * 0.12;
          else a = base + (n > 1 ? (i / (n - 1) - 0.5) * spread : 0) + (j1 - 0.5) * 0.24;
          var len = L * (0.86 + 0.28 * j2);
          pos[c] = { x: P.x + Math.cos(a) * len, y: P.y + Math.sin(a) * len };
          dir[c] = a; dep[c] = depth;
        }
      }
      list.forEach(function (u) { if (dep[u] == null) dep[u] = (pn.kind === 'hub' || pn.kind === 'origin') ? 1 : (dep[p] || 1) + 1; queue.push(u); });
    }
    data.nodes.forEach(function (n) { if (!pos[n.uid]) pos[n.uid] = { x: O.x, y: O.y }; });
    this.base = pos; this.dir = dir; this.depth = dep;
    // label side for hubs
    var side = {};
    data.nodes.forEach(function (n) {
      if (n.kind !== 'hub') return;
      var P = pos[n.uid], dx = P.x - O.x, dy = P.y - O.y, d = Math.hypot(dx, dy);
      side[n.uid] = n.uid === centralUid || d < 1 ? 1 : (dy / d > 0.35 ? -1 : 1);
    });
    this.labelSide = side;
    // l'origine est-elle recouverte par un hub ?
    this.originCovered = false;
    var hubR = S.hubRadius;
    if (idx.origin) {
      var self = this;
      data.nodes.forEach(function (n) {
        if (n.kind === 'hub' && Math.hypot(pos[n.uid].x - O.x, pos[n.uid].y - O.y) < hubR * 1.2) self.originCovered = n.uid;
      });
    }
  };

  /* ---------- position d'affichage (expansion) */
  View.prototype._centralE = function () {
    var c = this.central, sl = this.slots;
    if (!c) return 0;
    return (sl.cur.uid === c ? sl.cur.t : 0) + (sl.prev.uid === c ? sl.prev.t : 0);
  };
  View.prototype._posAt = function (u, e) {
    var B = this.base[u], idx = this.idx, c = this.central, O = this.O;
    if (!c || this.editing) return B;
    var hub = idx.hubOf[u], S = this.settings;
    var cs = clamp(num(S.centralScale) || 0.55, 0.1, 1);
    var push = lerp(1, S.expandPush, e);
    if (u === c) return B;
    if (hub === c) { var C = this.base[c], f = lerp(cs, 1, e); return { x: C.x + (B.x - C.x) * f, y: C.y + (B.y - C.y) * f }; }
    if (idx.origin && u === idx.origin.uid) return B;
    if (hub) { var H = this.base[hub]; return { x: B.x + (H.x - O.x) * (push - 1), y: B.y + (H.y - O.y) * (push - 1) }; }
    return { x: O.x + (B.x - O.x) * push, y: O.y + (B.y - O.y) * push };
  };
  View.prototype._display = function () {
    var e = this._centralE(), sl = this.slots, idx = this.idx, disp = {}, self = this;
    var D = Math.max(sl.cur.t, sl.prev.t);
    var c = this.central;
    this.data.nodes.forEach(function (n) {
      var u = n.uid, p = self._posAt(u, e), hub = idx.hubOf[u];
      var hl = 0;
      if (sl.cur.uid && (hub === sl.cur.uid)) hl += sl.cur.t;
      if (sl.prev.uid && (hub === sl.prev.uid)) hl += sl.prev.t;
      if (c && idx.origin && u === idx.origin.uid && (sl.cur.uid === c || sl.prev.uid === c)) hl = e;
      hl = Math.min(1, hl);
      var o = 1 - 0.75 * clamp(D - hl, 0, 1);
      var sc = 1 + (n.kind === 'hub' ? 0.1 : 0.35) * hl;
      if (c && hub === c && u !== c && !self.editing) { sc *= lerp(0.72, 1, e); o *= lerp(0.8, 1, e); }
      disp[u] = { x: p.x, y: p.y, o: o, s: sc, h: hl };
    });
    this.disp = disp;
  };

  /* ---------- synchronisation DOM (création / réutilisation) */
  View.prototype._grad = function (color) {
    if (this.gradients[color]) return this.gradients[color];
    var id = this.id + '-g' + Object.keys(this.gradients).length;
    var g = s('radialGradient', { id: id }, this.defs);
    s('stop', { offset: '0', 'stop-color': color, 'stop-opacity': '0.6' }, g);
    s('stop', { offset: '0.42', 'stop-color': color, 'stop-opacity': '0.22' }, g);
    s('stop', { offset: '1', 'stop-color': color, 'stop-opacity': '0' }, g);
    this.gradients[color] = 'url(#' + id + ')';
    return this.gradients[color];
  };

  View.prototype._statusLabel = function (st) { return this.t['status_' + st] || st; };

  View.prototype._sync = function () {
    var self = this, idx = this.idx, S = this.settings, t = this.t, keep = {};
    this.data.nodes.forEach(function (n) {
      var u = n.uid; keep[u] = true;
      var color = self.colors[u];
      var leaf = !(idx.kids[u] && idx.kids[u].length);
      var covered = n.kind === 'origin' && !!self.originCovered;
      var size = num(n.theme.size) || 1;
      var sig = [n.kind, n.status, n.icon || '', color, leaf ? 1 : 0, covered ? 1 : 0, size, n.theme.glow ? 1 : 0].join('|');
      var rec = self.nodeEls[u];
      if (!rec) {
        var g = s('g', { 'class': 'st-node', 'data-uid': u, tabindex: '0', role: 'button' }, self.gNodes);
        rec = self.nodeEls[u] = { g: g, sig: null };
        g.addEventListener('keydown', function (ev) {
          if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); self._activate(u, ev, true); }
        });
        g.addEventListener('focus', function () { self._showTip(u); });
        g.addEventListener('blur', function () { self._hideTip(); });
      }
      var g2 = rec.g;
      setA(g2, 'aria-label', (n.name || u) + ' — ' + (t['kind_' + n.kind] || '') + ', ' + self._statusLabel(n.status));
      g2.style.setProperty('--c', color);
      if (rec.sig !== sig) {
        rec.sig = sig;
        clear(g2);
        g2.setAttribute('class', 'st-node st-' + n.kind + ' st-' + n.status + (leaf ? ' st-leaf' : ''));
        rec.r = self._radius(n, leaf);
        var r = rec.r;
        if (n.kind === 'hub') {
          s('circle', { 'class': 'st-halo', r: (r * 2.4).toFixed(1), fill: self._grad(color) }, g2);
          if (n.status === 'available') s('circle', { 'class': 'st-pulse', r: (r + 7).toFixed(1) }, g2);
          s('circle', { 'class': 'st-ring', r: (r + 5).toFixed(1) }, g2);
          s('circle', { 'class': 'st-disc', r: r.toFixed(1) }, g2);
          drawIcon(g2, n.icon, r * 1.05);
          if (n.status === 'completed') {
            var bg = s('g', { 'class': 'st-badge', transform: 'translate(' + (r * 0.72).toFixed(1) + ' ' + (-r * 0.72).toFixed(1) + ')' }, g2);
            s('circle', { r: (r * 0.28).toFixed(1) }, bg);
            s('path', { d: 'M' + (-r * 0.12).toFixed(1) + ' 0l' + (r * 0.09).toFixed(1) + ' ' + (r * 0.09).toFixed(1) + 'l' + (r * 0.15).toFixed(1) + ' ' + (-r * 0.17).toFixed(1) }, bg);
          }
        } else if (n.kind === 'origin') {
          if (covered) {
            var cr = S.hubRadius * 1.55;
            s('circle', { 'class': 'st-origin-hit', r: cr.toFixed(1) }, g2);
            s('circle', { 'class': 'st-origin-ring', r: cr.toFixed(1) }, g2);
          } else {
            s('circle', { 'class': 'st-halo', r: (r * 3).toFixed(1), fill: self._grad('#ffffff') }, g2);
            s('circle', { 'class': 'st-origin-ring', r: (r * 1.6).toFixed(1) }, g2);
            s('circle', { 'class': 'st-dot', r: r.toFixed(1) }, g2);
          }
        } else {
          s('circle', { 'class': 'st-hit', r: Math.max(r + 6, 13).toFixed(1) }, g2);
          if (n.status === 'completed' || n.theme.glow) s('circle', { 'class': 'st-glow', r: (r * 1.25).toFixed(1), filter: 'url(#' + self.id + '-glow)' }, g2);
          if (n.status === 'available') s('circle', { 'class': 'st-pulse', r: (r + 4).toFixed(1) }, g2);
          s('circle', { 'class': 'st-dot', r: r.toFixed(1) }, g2);
        }
      }
      g2.classList.toggle('st-selected', self.editing && self.selected === u);
      g2.classList.toggle('st-link-src', self.editing && self.linkMode === 2 && self.linkSrc === u);
      // labels
      var lab = self.labelEls[u];
      var needSub = n.kind === 'hub';
      if (!lab) {
        lab = self.labelEls[u] = { g: s('g', { 'class': 'st-label st-label-' + n.kind }, self.gLabels) };
        lab.title = s('text', { 'class': 'st-label-title', 'text-anchor': 'middle' }, lab.g);
        lab.sub = s('text', { 'class': 'st-label-sub', 'text-anchor': 'middle' }, lab.g);
      }
      lab.g.setAttribute('class', 'st-label st-label-' + n.kind + ' st-' + n.status);
      var title = n.kind === 'hub' ? (n.name || '').toLocaleUpperCase('fr') : (n.name || '');
      if (lab.title.textContent !== title) lab.title.textContent = title;
      var sub = needSub ? (n.meta && typeof n.meta.subtitle === 'string' ? n.meta.subtitle : truncate(n.description || '', 42)).toLocaleLowerCase('fr') : '';
      if (lab.sub.textContent !== sub) lab.sub.textContent = sub;
    });
    Object.keys(this.nodeEls).forEach(function (u) {
      if (!keep[u]) { self.nodeEls[u].g.remove(); delete self.nodeEls[u]; }
    });
    Object.keys(this.labelEls).forEach(function (u) {
      if (!keep[u]) { self.labelEls[u].g.remove(); delete self.labelEls[u]; }
    });
    // liens
    var links = this.allLinks = [];
    this.data.nodes.forEach(function (n) {
      if (n.parent && idx.by[n.parent] && idx.eff[n.uid] === n.parent) links.push({ id: 'p:' + n.uid, from: n.parent, to: n.uid, type: 'path', implicit: true });
    });
    this.data.links.forEach(function (l) { links.push({ id: l.uid, from: l.from, to: l.to, type: l.type, implicit: false, ref: l }); });
    var keepL = {};
    links.forEach(function (l) {
      keepL[l.id] = true;
      var rec = self.linkEls[l.id];
      if (!rec) {
        var g = s('g', { 'class': 'st-link', 'data-link': l.id }, self.gLinks);
        rec = self.linkEls[l.id] = { g: g, hit: s('line', { 'class': 'st-link-hit' }, g), line: s('line', { 'class': 'st-link-line', 'vector-effect': 'non-scaling-stroke' }, g) };
      }
      var to = idx.by[l.to], from = idx.by[l.from];
      var cls = 'st-link st-link-' + l.type + ' st-to-' + to.status;
      if (from.kind === 'origin') cls += ' st-link-root';
      if (!l.implicit) cls += ' st-link-extra';
      if (self.editing && self.selectedLink === l.id) cls += ' st-selected';
      setA(rec.g, 'class', cls);
      rec.g.style.setProperty('--c', self.colors[l.to]);
      rec.l = l;
    });
    Object.keys(this.linkEls).forEach(function (id) { if (!keepL[id]) { self.linkEls[id].g.remove(); delete self.linkEls[id]; } });
    this._dirty = true;
  };

  View.prototype._radius = function (n, leaf) {
    var S = this.settings, size = num(n.theme && n.theme.size) || 1;
    if (n.kind === 'hub') return S.hubRadius * size;
    if (n.kind === 'origin') return S.nodeRadius * 0.9 * size;
    var r = S.nodeRadius;
    if (n.status === 'available') r *= 0.72; else if (n.status === 'completed') r *= 0.56; else r *= 0.44;
    if (leaf) r *= 1.12;
    return r * size;
  };

  /* ---------- application des positions / caméra */
  View.prototype._applyNodes = function () {
    var disp = this.disp, self = this, focusH = Math.max(this.slots.cur.t, this.slots.prev.t);
    Object.keys(this.nodeEls).forEach(function (u) {
      var d = disp[u], rec = self.nodeEls[u]; if (!d) return;
      setA(rec.g, 'transform', 'translate(' + d.x.toFixed(2) + ' ' + d.y.toFixed(2) + ')' + (Math.abs(d.s - 1) > 0.001 ? ' scale(' + d.s.toFixed(3) + ')' : ''));
      setA(rec.g, 'opacity', d.o > 0.999 ? null : d.o.toFixed(3));
    });
    Object.keys(this.linkEls).forEach(function (id) {
      var rec = self.linkEls[id], l = rec.l, a = disp[l.from], b = disp[l.to];
      if (!a || !b) return;
      var x1 = a.x.toFixed(2), y1 = a.y.toFixed(2), x2 = b.x.toFixed(2), y2 = b.y.toFixed(2);
      setA(rec.line, 'x1', x1); setA(rec.line, 'y1', y1); setA(rec.line, 'x2', x2); setA(rec.line, 'y2', y2);
      setA(rec.hit, 'x1', x1); setA(rec.hit, 'y1', y1); setA(rec.hit, 'x2', x2); setA(rec.hit, 'y2', y2);
      var o = Math.min(a.o, b.o);
      setA(rec.g, 'opacity', o > 0.999 ? null : o.toFixed(3));
      var lit = focusH > 0.05 && Math.min(a.h, b.h) > 0.3;
      if (rec.lit !== lit) { rec.lit = lit; rec.g.classList.toggle('st-lit', lit); }
    });
  };

  View.prototype._applyCamera = function () {
    var c = this.cam;
    setA(this.world, 'transform', 'translate(' + (this.W / 2 - c.x * c.k).toFixed(2) + ' ' + (this.H / 2 - c.y * c.k).toFixed(2) + ') scale(' + c.k.toFixed(4) + ')');
  };
  View.prototype.toScreen = function (x, y) { var c = this.cam; return { x: (x - c.x) * c.k + this.W / 2, y: (y - c.y) * c.k + this.H / 2 }; };
  View.prototype.toWorld = function (sx, sy) { var c = this.cam; return { x: c.x + (sx - this.W / 2) / c.k, y: c.y + (sy - this.H / 2) / c.k }; };

  View.prototype._applyLabels = function () {
    var self = this, k = this.cam.k, disp = this.disp, showAll = this.settings.showLabels === 'always', never = this.settings.showLabels === 'never';
    var fs = clamp(0.62 + k * 0.55, 0.78, 1.25);
    Object.keys(this.labelEls).forEach(function (u) {
      var lab = self.labelEls[u], d = disp[u], n = self.idx.by[u], rec = self.nodeEls[u];
      if (!d || !n || !rec) return;
      var p = self.toScreen(d.x, d.y);
      var o;
      if (n.kind === 'hub') o = d.o;
      else if (n.kind === 'origin') o = self.originCovered || never ? 0 : d.o;
      else o = never ? 0 : (showAll ? d.o : d.h * d.o);
      if (self.editing && self.selected === u) o = 1;
      if (p.x < -200 || p.x > self.W + 200 || p.y < -100 || p.y > self.H + 100) o = 0;
      setA(lab.g, 'opacity', o < 0.02 ? '0' : o.toFixed(3));
      if (o < 0.02) return;
      var r = rec.r * d.s * k;
      if (n.kind === 'hub') {
        var sideSign = self.labelSide[u] || 1;
        var off = r + (u === self.central && self.originCovered ? self.settings.hubRadius * 0.6 * k : 0) + 12;
        var ty, sy2;
        if (sideSign > 0) { ty = off + 14 * fs; sy2 = ty + 15 * fs; }
        else { sy2 = -off - 2; ty = sy2 - 15 * fs; }
        setA(lab.g, 'transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ')');
        setA(lab.title, 'y', ty.toFixed(1)); setA(lab.sub, 'y', sy2.toFixed(1));
        setA(lab.title, 'x', '0'); setA(lab.sub, 'x', '0');
        setA(lab.g, 'font-size', (fs * 100).toFixed(0) + '%');
      } else {
        var a = self.dir[u]; if (a == null) a = 0;
        var right = Math.cos(a) >= -0.2;
        setA(lab.title, 'text-anchor', right ? 'start' : 'end');
        setA(lab.title, 'x', ((right ? 1 : -1) * (r + 7)).toFixed(1));
        setA(lab.title, 'y', '4');
        setA(lab.g, 'transform', 'translate(' + p.x.toFixed(1) + ' ' + p.y.toFixed(1) + ')');
      }
    });
    if (this.tipUid) this._placeTip();
  };

  /* ---------- boucle d'animation */
  View.prototype._loop = function (now) {
    if (this._destroyed) return;
    this._raf = win.requestAnimationFrame(this._loop);
    this.now = now;
    var keys = Object.keys(this.tweens);
    for (var i = 0; i < keys.length; i++) {
      var tw = this.tweens[keys[i]];
      if (tw.start == null) tw.start = now;
      var p = tw.dur > 0 ? clamp((now - tw.start) / tw.dur, 0, 1) : 1;
      tw.fn(easeInOutCubic(p), p);
      if (p >= 1) { delete this.tweens[keys[i]]; if (tw.done) tw.done(); }
    }
    if (!this.data) return;
    if (this._dirty) { this._display(); this._applyNodes(); this._dirty = false; this._dirtyCam = true; }
    if (this._dirtyCam) { this._applyCamera(); this._applyLabels(); this._dirtyCam = false; this._bgDirty = true; }
    if (this.visible && (this._bgDirty || !this.reduced)) { this._drawBg(now); this._bgDirty = false; }
  };

  View.prototype._tween = function (key, dur, fn, done) {
    if (this.reduced) dur = 0;
    this.tweens[key] = { dur: dur, fn: fn, done: done, start: null };
  };

  View.prototype._animateCamera = function (target, dur) {
    var self = this, from = assign({}, this.cam);
    target.k = clamp(target.k, this.settings.zoomMin, this.settings.zoomMax);
    this._tween('cam', dur == null ? this.settings.duration : dur, function (e) {
      self.cam.x = lerp(from.x, target.x, e);
      self.cam.y = lerp(from.y, target.y, e);
      self.cam.k = Math.exp(lerp(Math.log(from.k), Math.log(target.k), e));
      self._dirtyCam = true;
    });
  };

  /* ---------- fond : étoiles + nuage de particules */
  View.prototype._makeStars = function () {
    var W = this.W, H = this.H, dpr = this.dpr;
    var off = this.starTile = doc.createElement('canvas');
    off.width = Math.round(W * dpr); off.height = Math.round(H * dpr);
    var g = off.getContext('2d'); if (!g) return;
    g.scale(dpr, dpr);
    var seed = 7;
    function rnd() { seed = (seed * 16807) % 2147483647; return (seed - 1) / 2147483646; }
    var neb = [['rgba(123,108,230,0.10)', 0.2, 0.25, 0.55], ['rgba(255,122,89,0.045)', 0.8, 0.7, 0.5], ['rgba(76,201,240,0.05)', 0.75, 0.2, 0.4], ['rgba(199,125,255,0.06)', 0.3, 0.85, 0.45]];
    neb.forEach(function (nb) {
      var rr = Math.max(W, H) * nb[3], gr = g.createRadialGradient(nb[1] * W, nb[2] * H, 0, nb[1] * W, nb[2] * H, rr);
      gr.addColorStop(0, nb[0]); gr.addColorStop(1, 'rgba(0,0,0,0)');
      g.fillStyle = gr; g.fillRect(0, 0, W, H);
    });
    var count = Math.min(900, Math.round(W * H / 1800));
    for (var i = 0; i < count; i++) {
      var x = rnd() * W, y = rnd() * H, r = rnd() < 0.93 ? rnd() * 0.8 + 0.25 : rnd() * 1.1 + 0.9;
      var a = 0.15 + rnd() * 0.6;
      var tint = rnd();
      g.fillStyle = tint < 0.7 ? 'rgba(255,255,255,' + a + ')' : (tint < 0.85 ? 'rgba(200,190,255,' + a + ')' : 'rgba(255,210,190,' + a + ')');
      g.beginPath(); g.arc(x, y, r, 0, TAU); g.fill();
    }
    this.twinkles = [];
    for (var j = 0; j < 36; j++) this.twinkles.push({ x: rnd() * W, y: rnd() * H, r: 0.8 + rnd() * 1.1, p: rnd() * TAU, s: 0.6 + rnd() * 1.6 });
    this._bgDirty = true;
  };

  View.prototype._makeParticles = function () {
    var n = Math.round(num(this.settings.particles) || 320), list = [];
    var cols = ['255,255,255', '233,228,245', '255,177,153', '159,216,255', '255,122,89', '199,170,255'];
    for (var i = 0; i < n; i++) {
      var a = hash('pa' + i), b = hash('pb' + i), c = hash('pc' + i), d = hash('pd' + i);
      var arm = i % 3;
      var rr = 0.18 + 0.82 * Math.sqrt(a);
      var gauss = (b + c + d) / 3 - 0.5;
      list.push({
        r: rr, a: arm * TAU / 3 + rr * 2.6 + gauss * 1.1, w: 0.05 + 0.16 * (1 - rr), size: 0.5 + 1.5 * Math.pow(c, 2),
        al: 0.25 + 0.7 * d, col: cols[Math.floor(b * cols.length) % cols.length], tw: a * TAU, z: (d - 0.5) * 0.18
      });
    }
    this.particles = list;
  };

  View.prototype._drawBg = function (now) {
    var dpr = this.dpr, W = this.W, H = this.H, cam = this.cam, t = this.reduced ? 0 : now / 1000;
    var gs = this.cvStars.getContext('2d');
    if (gs && this.starTile) {
      gs.setTransform(1, 0, 0, 1, 0, 0);
      gs.clearRect(0, 0, this.cvStars.width, this.cvStars.height);
      var ox = ((-cam.x * cam.k * 0.06) % W + W) % W * dpr, oy = ((-cam.y * cam.k * 0.06) % H + H) % H * dpr;
      var tw = this.cvStars.width, th = this.cvStars.height;
      gs.drawImage(this.starTile, ox - tw, oy - th); gs.drawImage(this.starTile, ox, oy - th);
      gs.drawImage(this.starTile, ox - tw, oy); gs.drawImage(this.starTile, ox, oy);
    }
    var g = this.cvFx.getContext('2d'); if (!g) return;
    g.setTransform(dpr, 0, 0, dpr, 0, 0);
    g.clearRect(0, 0, W, H);
    // étoiles scintillantes
    if (this.twinkles) {
      var px = ((-cam.x * cam.k * 0.06) % W + W) % W, py = ((-cam.y * cam.k * 0.06) % H + H) % H;
      for (var i = 0; i < this.twinkles.length; i++) {
        var s0 = this.twinkles[i], al = 0.25 + 0.75 * (0.5 + 0.5 * Math.sin(t * s0.s + s0.p));
        var x = (s0.x + px) % W, y = (s0.y + py) % H;
        g.fillStyle = 'rgba(255,255,255,' + (al * 0.9).toFixed(3) + ')';
        g.beginPath(); g.arc(x, y, s0.r, 0, TAU); g.fill();
        if (s0.r > 1.5) { g.fillStyle = 'rgba(255,255,255,' + (al * 0.25).toFixed(3) + ')'; g.fillRect(x - s0.r * 3, y - 0.4, s0.r * 6, 0.8); g.fillRect(x - 0.4, y - s0.r * 3, 0.8, s0.r * 6); }
      }
    }
    if (!this.data) return;
    // nuage central autour de l'origine
    if (!this.particles) this._makeParticles();
    var e = this._centralE();
    var od = this.idx.origin ? this.disp[this.idx.origin.uid] : null;
    var O = od || this.O || { x: 0, y: 0 };
    var c = this.toScreen(O.x, O.y), k = cam.k;
    var cloudR = this.settings.hubRadius * 3.4 * lerp(1, 1.75, e) * k;
    if (c.x > -cloudR * 2 && c.x < W + cloudR * 2 && c.y > -cloudR * 2 && c.y < H + cloudR * 2) {
      var dim = od ? od.o : 1;
      var glow = g.createRadialGradient(c.x, c.y, 0, c.x, c.y, cloudR * 1.2);
      glow.addColorStop(0, 'rgba(233,228,245,' + (0.22 * dim).toFixed(3) + ')');
      glow.addColorStop(0.35, 'rgba(160,140,255,' + (0.10 * dim).toFixed(3) + ')');
      glow.addColorStop(1, 'rgba(120,100,220,0)');
      g.fillStyle = glow; g.beginPath(); g.arc(c.x, c.y, cloudR * 1.2, 0, TAU); g.fill();
      var ps = this.particles, sz = clamp(k * 1.1, 0.6, 1.8), fade = lerp(1, 0.75, e) * dim;
      for (var j = 0; j < ps.length; j++) {
        var p = ps[j], ang = p.a + t * p.w;
        var rr = p.r * cloudR * (1 + p.z * Math.sin(t * 0.7 + p.tw));
        var xx = c.x + Math.cos(ang) * rr, yy = c.y + Math.sin(ang) * rr * 0.92;
        var al2 = p.al * fade * (this.reduced ? 1 : 0.65 + 0.35 * Math.sin(t * 1.3 + p.tw));
        g.fillStyle = 'rgba(' + p.col + ',' + al2.toFixed(3) + ')';
        g.beginPath(); g.arc(xx, yy, p.size * sz, 0, TAU); g.fill();
      }
    }
    // gerbes de validation
    if (this.bursts.length) {
      var alive = [];
      for (var b = 0; b < this.bursts.length; b++) {
        var bu = this.bursts[b], age = (now - bu.t0) / 1000;
        if (age > 1.4) continue;
        alive.push(bu);
        var bc = this.toScreen(bu.x, bu.y);
        for (var q = 0; q < bu.parts.length; q++) {
          var pp = bu.parts[q], dist = pp.v * (1 - Math.exp(-age * 3.2)) * k;
          var aa = Math.max(0, 1 - age / 1.4);
          g.fillStyle = 'rgba(' + pp.col + ',' + aa.toFixed(3) + ')';
          g.beginPath(); g.arc(bc.x + Math.cos(pp.a) * dist, bc.y + Math.sin(pp.a) * dist + age * age * 18, pp.s, 0, TAU); g.fill();
        }
      }
      this.bursts = alive;
    }
  };

  View.prototype._burst = function (u) {
    if (this.reduced) return;
    var d = this.disp[u]; if (!d) return;
    var parts = [], cols = ['255,122,89', '255,209,102', '255,255,255', '233,228,245'];
    for (var i = 0; i < 48; i++) parts.push({ a: Math.random() * TAU, v: 30 + Math.random() * 90, s: 0.8 + Math.random() * 1.8, col: cols[i % cols.length] });
    this.bursts.push({ x: d.x, y: d.y, t0: this.now || 0, parts: parts });
  };

  /* ---------- caméra : cadrages */
  View.prototype._bbox = function (uids, e) {
    var x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity, self = this;
    uids.forEach(function (u) {
      var p = self._posAt(u, e), n = self.idx.by[u];
      var r = n.kind === 'hub' ? self.settings.hubRadius * 1.6 + 30 : 22;
      x0 = Math.min(x0, p.x - r); y0 = Math.min(y0, p.y - r); x1 = Math.max(x1, p.x + r); y1 = Math.max(y1, p.y + r + (n.kind === 'hub' ? 40 : 0));
    });
    if (!isFinite(x0)) return { x0: -100, y0: -100, x1: 100, y1: 100 };
    return { x0: x0, y0: y0, x1: x1, y1: y1 };
  };
  View.prototype._panelW = function () { return this.panelUid !== null && this.panelUid !== undefined && !this.panel.hidden && !this.mobile ? Math.min(380, this.W * 0.42) : 0; };
  View.prototype._camFor = function (bb, maxK, pad) {
    pad = pad == null ? 40 : pad;
    var pw = this._panelW(), top = 70, bottom = this.mobile && !this.panel.hidden ? this.H * 0.45 : 20;
    var aw = Math.max(80, this.W - pw - pad * 2), ah = Math.max(80, this.H - top - bottom - pad);
    var bw = Math.max(bb.x1 - bb.x0, 160), bh = Math.max(bb.y1 - bb.y0, 160);
    var k = clamp(Math.min(aw / bw, ah / bh), this.settings.zoomMin, maxK || this.settings.zoomMax);
    var cx = (bb.x0 + bb.x1) / 2 + (pw / 2) / k, cy = (bb.y0 + bb.y1) / 2 - ((top - bottom) / 2) / k;
    return { x: cx, y: cy, k: k };
  };
  View.prototype._fitAll = function (animate) {
    if (!this.data || !this.W) return;
    var uids = this.data.nodes.map(function (n) { return n.uid; });
    var target = this._camFor(this._bbox(uids, 0), 1.4);
    this._home = target;
    if (animate) this._animateCamera(target); else { this.cam = target; this._dirtyCam = true; }
  };
  View.prototype.recenter = function () { this._userMoved = false; this._fitAll(true); };
  View.prototype.zoomBy = function (f, sx, sy) {
    if (sx == null) { sx = this.W / 2; sy = this.H / 2; }
    var w = this.toWorld(sx, sy);
    var k = clamp(this.cam.k * f, this.settings.zoomMin, this.settings.zoomMax);
    delete this.tweens.cam;
    this.cam.k = k; this.cam.x = w.x - (sx - this.W / 2) / k; this.cam.y = w.y - (sy - this.H / 2) / k;
    this._dirtyCam = true; this._userMoved = true;
  };

  /* ---------- expansion */
  View.prototype._groupOf = function (hub) {
    var idx = this.idx;
    return this.data.nodes.filter(function (n) { return idx.hubOf[n.uid] === hub; }).map(function (n) { return n.uid; });
  };
  View.prototype.expand = function (u) {
    if (!this.data || this.editing) return;
    var n = this.idx.by[u]; if (!n) return;
    var target = n.kind === 'hub' ? u : this.idx.hubOf[u];
    if (!target) { this.focus(u); return; }
    var sl = this.slots, self = this;
    if (sl.cur.uid !== target) {
      sl.prev = { uid: sl.cur.uid, t: sl.cur.t };
      sl.cur = { uid: target, t: 0 };
      var p0 = sl.prev.t, c0 = 0;
      this._tween('slots', this.settings.duration, function (e) {
        sl.cur.t = lerp(c0, 1, e); sl.prev.t = lerp(p0, 0, e); self._dirty = true;
      }, function () { sl.prev = { uid: null, t: 0 }; });
      this.root.classList.add('st-expanded');
      this.emit('expand', n);
    }
    var eT = target === this.central ? 1 : 0;
    var group = this._groupOf(target);
    if (target === this.central && this.idx.origin) group.push(this.idx.origin.uid);
    this._animateCamera(this._camFor(this._bbox(group, eT), target === this.central ? 1.3 : 1.8, 50));
  };
  View.prototype.collapse = function () {
    var sl = this.slots, self = this;
    if (!sl.cur.uid && !sl.prev.t) return;
    var had = sl.cur.uid;
    sl.prev = { uid: sl.cur.uid, t: sl.cur.t };
    sl.cur = { uid: null, t: 0 };
    var p0 = sl.prev.t;
    this._tween('slots', this.settings.duration, function (e) { sl.prev.t = lerp(p0, 0, e); self._dirty = true; }, function () { sl.prev = { uid: null, t: 0 }; });
    this.root.classList.remove('st-expanded');
    var uids = this.data.nodes.map(function (n) { return n.uid; });
    this._animateCamera(this._camFor(this._bbox(uids, 0), 1.4));
    if (had) this.emit('collapse', this.idx.by[had]);
  };
  View.prototype.focus = function (u) {
    if (!this.data) return;
    var d = this.disp[u] || this.base[u]; if (!d) return;
    var pw = this._panelW(), k = Math.max(this.cam.k, 1.1);
    this._animateCamera({ x: d.x + (pw / 2) / k, y: d.y, k: k });
    var rec = this.nodeEls[u];
    if (rec) {
      try { rec.g.focus({ preventScroll: true }); } catch (e) { /* noop */ }
      rec.g.classList.remove('st-flash'); void rec.g.getBBox; rec.g.classList.add('st-flash');
      setTimeout(function () { rec.g.classList.remove('st-flash'); }, 1600);
    }
    this.emit('focus', this.idx.by[u]);
  };

  /* ---------- interactions */
  View.prototype._bindEvents = function () {
    var self = this, svgEl = this.svg;
    function local(e) { var r = svgEl.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
    function nodeOf(t) { var g = t && t.closest ? t.closest('[data-uid]') : null; return g ? g.getAttribute('data-uid') : null; }
    function linkOf(t) { var g = t && t.closest ? t.closest('[data-link]') : null; return g ? g.getAttribute('data-link') : null; }
    this._on(svgEl, 'pointerdown', function (e) {
      if (e.pointerType === 'mouse' && e.button !== 0) return;
      try { svgEl.setPointerCapture(e.pointerId); } catch (err) { /* noop */ }
      var p = local(e);
      self.ptrs[e.pointerId] = p;
      var ids = Object.keys(self.ptrs);
      if (ids.length === 1) {
        self.gesture = { type: 'maybe', uid: nodeOf(e.target), link: linkOf(e.target), x0: p.x, y0: p.y, cam0: assign({}, self.cam), moved: false, shift: e.shiftKey };
        if (!self.gesture.uid) { try { svgEl.focus({ preventScroll: true }); } catch (err) { /* noop */ } }
      } else if (ids.length === 2) {
        var a = self.ptrs[ids[0]], b = self.ptrs[ids[1]];
        var mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        self.gesture = { type: 'pinch', d0: Math.hypot(a.x - b.x, a.y - b.y) || 1, k0: self.cam.k, w: self.toWorld(mid.x, mid.y), moved: true };
      }
    });
    this._on(svgEl, 'pointermove', function (e) {
      var p = local(e);
      if (!self.ptrs[e.pointerId]) {
        if (e.pointerType === 'mouse') { var u = nodeOf(e.target); if (u !== self.tipUid) { if (u) self._showTip(u); else self._hideTip(); } }
        return;
      }
      self.ptrs[e.pointerId] = p;
      var g = self.gesture; if (!g) return;
      var ids = Object.keys(self.ptrs);
      if (g.type === 'pinch' && ids.length >= 2) {
        var a = self.ptrs[ids[0]], b = self.ptrs[ids[1]];
        var mid = { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 };
        var k = clamp(g.k0 * Math.hypot(a.x - b.x, a.y - b.y) / g.d0, self.settings.zoomMin, self.settings.zoomMax);
        delete self.tweens.cam;
        self.cam.k = k; self.cam.x = g.w.x - (mid.x - self.W / 2) / k; self.cam.y = g.w.y - (mid.y - self.H / 2) / k;
        self._dirtyCam = true; self._userMoved = true;
        return;
      }
      var dx = p.x - g.x0, dy = p.y - g.y0;
      if (!g.moved && Math.hypot(dx, dy) > 4) {
        g.moved = true;
        self._hideTip();
        if (self.editing && g.uid && !self.linkMode) {
          g.type = 'drag';
          self._pushUndo();
          var b0 = self.base[g.uid], w0 = self.toWorld(g.x0, g.y0);
          g.off = { x: b0.x - w0.x, y: b0.y - w0.y };
          self.root.classList.add('st-dragging');
        } else { g.type = 'pan'; self.root.classList.add('st-panning'); }
      }
      if (!g.moved) return;
      if (g.type === 'pan') {
        delete self.tweens.cam;
        self.cam.x = g.cam0.x - dx / self.cam.k; self.cam.y = g.cam0.y - dy / self.cam.k;
        self._dirtyCam = true; self._userMoved = true;
      } else if (g.type === 'drag') {
        var w = self.toWorld(p.x, p.y), n = self.idx.by[g.uid];
        n.x = round1(w.x + g.off.x); n.y = round1(w.y + g.off.y);
        self._layout(); self._dirty = true; self._markDirty();
        if (self.panelUid === g.uid) self._syncPosFields();
      }
    });
    function end(e) {
      if (!self.ptrs[e.pointerId]) return;
      delete self.ptrs[e.pointerId];
      try { svgEl.releasePointerCapture(e.pointerId); } catch (err) { /* noop */ }
      var g = self.gesture, ids = Object.keys(self.ptrs);
      if (g && g.type === 'pinch') {
        if (ids.length === 1) { var q = self.ptrs[ids[0]]; self.gesture = { type: 'pan', moved: true, x0: q.x, y0: q.y, cam0: assign({}, self.cam) }; }
        else if (!ids.length) self.gesture = null;
        return;
      }
      self.gesture = null;
      self.root.classList.remove('st-panning', 'st-dragging');
      if (!g || e.type === 'pointercancel') return;
      if (g.type === 'drag') { self._recompute(false); self.emit('change', self.api); return; }
      if (g.moved) return;
      if (g.uid) self._activate(g.uid, { shiftKey: e.shiftKey || g.shift }, false);
      else if (g.link && self.editing) self._selectLink(g.link);
      else self._background();
    }
    this._on(svgEl, 'pointerup', end);
    this._on(svgEl, 'pointercancel', end);
    this._on(svgEl, 'pointerleave', function (e) { if (e.pointerType === 'mouse' && !self.ptrs[e.pointerId]) self._hideTip(); });
    this._on(svgEl, 'wheel', function (e) {
      e.preventDefault();
      var p = local(e), dy = e.deltaY * (e.deltaMode === 1 ? 16 : (e.deltaMode === 2 ? 400 : 1));
      if (e.ctrlKey) dy *= 3;
      self.zoomBy(Math.exp(-dy * 0.0016), p.x, p.y);
    }, { passive: false });
    this._on(this.root, 'keydown', function (e) {
      var tag = e.target && e.target.tagName;
      var inField = tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
      if (e.key === 'Escape') {
        if (self.linkMode) { self._setLinkMode(0); return; }
        if (self.panelUid != null && !self.panel.hidden) { e.preventDefault(); self.closePanel(); return; }
        self.collapse();
        return;
      }
      if (inField) return;
      if (self.editing) {
        if ((e.key === 'Delete' || e.key === 'Backspace') && (self.selected || self.selectedLink)) { e.preventDefault(); self._delete(); return; }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'z' || e.key === 'Z')) { e.preventDefault(); self.undo(); return; }
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S')) { e.preventDefault(); self.save(); return; }
      }
      if (e.target === svgEl) {
        if (e.key === '+' || e.key === '=') { self.zoomBy(1.25); e.preventDefault(); }
        else if (e.key === '-' || e.key === '_') { self.zoomBy(0.8); e.preventDefault(); }
        else if (e.key === '0') { self.recenter(); e.preventDefault(); }
        else if (/^Arrow/.test(e.key)) {
          var st = 60 / self.cam.k; delete self.tweens.cam;
          if (e.key === 'ArrowLeft') self.cam.x -= st; if (e.key === 'ArrowRight') self.cam.x += st;
          if (e.key === 'ArrowUp') self.cam.y -= st; if (e.key === 'ArrowDown') self.cam.y += st;
          self._dirtyCam = true; e.preventDefault();
        }
      }
    });
  };
  View.prototype._on = function (target, evt, fn, o) {
    target.addEventListener(evt, fn, o || false);
    (this._listeners || (this._listeners = [])).push([target, evt, fn, o || false]);
  };

  View.prototype._background = function () {
    if (this.editing) {
      if (this.linkMode) { this._setLinkMode(0); return; }
      this.selected = null; this.selectedLink = null; this._sync(); this._refreshPanel(); this._renderToolbar();
      return;
    }
    if (this.panelUid != null && !this.panel.hidden) this.closePanel();
    this.collapse();
  };

  View.prototype._activate = function (u, ev, keyboard) {
    var n = this.idx.by[u]; if (!n) return;
    if (typeof this.opts.onNodeClick === 'function' && this.opts.onNodeClick(n, this.api) === false) return;
    this.emit('nodeclick', n);
    if (this.editing) {
      if (this.linkMode === 1) { this.linkSrc = u; this.linkMode = 2; this._hintText(this.t.ed_linkPick); this._sync(); return; }
      if (this.linkMode === 2) { this._createLink(this.linkSrc, u); this._setLinkMode(0); return; }
      if (ev && ev.shiftKey && this.selected && this.selected !== u) { this._createLink(this.selected, u); return; }
      this._select(u);
      return;
    }
    if (n.kind === 'hub') {
      var already = this.slots.cur.uid === u;
      if (!already) {
        if (!this.mobile || keyboard) this.openDetail(u, true);
        this.expand(u);
      } else this.openDetail(u);
    } else {
      this.openDetail(u);
    }
  };

  /* ---------- tooltip */
  View.prototype._showTip = function (u) {
    var n = this.idx && this.idx.by[u]; if (!n) return;
    this.tipUid = u;
    var tip = this.tooltip; clear(tip);
    h('div', 'st-tip-name', tip, n.name || u);
    var st = h('div', 'st-tip-status st-' + n.status, tip, this._statusLabel(n.status));
    st.setAttribute('data-status', n.status);
    tip.hidden = false;
    this._placeTip();
  };
  View.prototype._placeTip = function () {
    var d = this.disp[this.tipUid], rec = this.nodeEls[this.tipUid];
    if (!d || !rec) { this._hideTip(); return; }
    var p = this.toScreen(d.x, d.y), r = rec.r * d.s * this.cam.k;
    this.tooltip.style.transform = 'translate(' + Math.round(p.x) + 'px,' + Math.round(p.y - r - 10) + 'px) translate(-50%,-100%)';
  };
  View.prototype._hideTip = function () { this.tipUid = null; this.tooltip.hidden = true; };

  /* ---------- en-tête, onglets, stats */
  View.prototype._renderHeader = function () {
    var tr = this.data.tree || {};
    this.titleEl.textContent = tr.name || '';
    this.subtitleEl.textContent = tr.description ? truncate(tr.description, 120) : '';
    this.header.hidden = this.opts.header === false || (!tr.name && !tr.description);
    this.svg.setAttribute('aria-label', fmt(this.t.treeLabel, { name: tr.name || '' }));
    var total = 0, done = 0, xp = 0;
    this.data.nodes.forEach(function (n) {
      if (n.kind === 'origin') return;
      total++;
      if (n.status === 'completed') { done++; xp += num(n.reward && n.reward.xp) || 0; }
    });
    clear(this.stats);
    this.stats.hidden = this.opts.stats === false || !total;
    var bar = h('div', 'st-stats-bar', this.stats);
    var fill = h('span', 'st-stats-fill', bar); fill.style.width = (total ? (done / total * 100) : 0).toFixed(1) + '%';
    h('div', 'st-stats-txt', this.stats, fmt(this.t.progress, { done: done, total: total }) + (xp ? ' · ' + fmt(this.t.xpTotal, { xp: xp }) : ''));
    this._renderTabsActive();
  };

  View.prototype._setupTabs = function (tabs) {
    var self = this;
    if (tabs === true || tabs === '1' || tabs === 'auto') {
      if (this.offline) return;
      this._api('list').then(function (list) {
        self._tabs = (Array.isArray(list) ? list : []).map(function (x) { return { label: x.name || x.slug, tree: x.slug }; });
        self._renderTabs();
      }).catch(function (e) { self._error(e); });
      return;
    }
    if (Array.isArray(tabs)) { this._tabs = tabs.filter(isObj); this._renderTabs(); }
  };
  View.prototype._renderTabs = function () {
    var self = this, nav = this.tabsEl; clear(nav);
    var list = this._tabs || [];
    nav.hidden = list.length < 2;
    nav.setAttribute('aria-label', 'Arbres');
    list.forEach(function (tb, i) {
      var b = h('button', 'st-tab', nav, String(tb.label || tb.tree || ('#' + (i + 1))));
      b.type = 'button';
      b.addEventListener('click', function () { self._switchTab(i); });
    });
    this._renderTabsActive();
  };
  View.prototype._renderTabsActive = function () {
    var list = this._tabs || [], slug = this.slug, self = this;
    Array.prototype.forEach.call(this.tabsEl.children, function (b, i) {
      var tb = list[i] || {};
      var on = self._tabIdx != null ? self._tabIdx === i : (tb.tree && tb.tree === slug);
      b.classList.toggle('st-active', !!on);
      b.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };
  View.prototype._switchTab = function (i) {
    var tb = (this._tabs || [])[i]; if (!tb) return;
    var self = this;
    this._tabIdx = i;
    var p;
    if (tb.data) p = Promise.resolve(tb.data);
    else if (tb.src) p = win.fetch(tb.src, { credentials: 'same-origin' }).then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });
    else if (!this.offline && tb.tree) { this.slug = tb.tree; p = this._api('tree'); }
    else return;
    this._setLoading(true);
    p.then(function (d) {
      self._setLoading(false);
      if (tb.tree) self.slug = tb.tree;
      self.undoStack = []; self.dirtyEdits = false;
      self.setData(d, { reset: true });
      if (self.editing) self._renderToolbar();
    }).catch(function (e) { self._setLoading(false, e); self._error(e); });
  };

  /* ---------- toast */
  View.prototype.toast = function (msg, kind) {
    var el = this.toastEl, self = this;
    el.textContent = msg;
    el.className = 'st-toast st-show' + (kind ? ' st-toast-' + kind : '');
    clearTimeout(this._toastT);
    this._toastT = setTimeout(function () { self.toastEl.className = 'st-toast'; }, kind === 'error' ? 4200 : 2600);
  };

  /* ---------- panneau de détail */
  View.prototype._panelShell = function (titleText, kicker) {
    var p = this.panel, self = this;
    clear(p);
    var head = h('div', 'st-panel-head', p);
    var close = h('button', 'st-close', head); close.type = 'button';
    close.setAttribute('aria-label', this.t.close); close.title = this.t.close;
    close.appendChild(uiIcon('close'));
    close.addEventListener('click', function () { self.closePanel(); });
    var body = h('div', 'st-panel-body', p);
    if (kicker) h('div', 'st-kicker', body, kicker);
    var ttl = h('h2', 'st-panel-title', body, titleText);
    ttl.id = this.id + '-ptitle';
    return body;
  };
  View.prototype._showPanel = function (focusClose) {
    var p = this.panel;
    var wasHidden = p.hidden;
    p.hidden = false;
    this.root.classList.add('st-panel-open');
    // force reflow pour l'animation
    if (wasHidden) { void p.offsetWidth; }
    p.classList.add('st-open');
    if (focusClose) { var c = p.querySelector('.st-close'); if (c) try { c.focus({ preventScroll: true }); } catch (e) { /* noop */ } }
  };
  View.prototype.closePanel = function (silent) {
    var p = this.panel, u = this.panelUid;
    if (u == null && p.hidden) return;
    this.panelUid = null;
    p.classList.remove('st-open');
    this.root.classList.remove('st-panel-open');
    var self = this;
    clearTimeout(this._panelT);
    this._panelT = setTimeout(function () { if (self.panelUid == null) p.hidden = true; }, this.reduced ? 0 : 320);
    if (!silent) {
      var rec = u && this.nodeEls[u];
      if (rec) try { rec.g.focus({ preventScroll: true }); } catch (e) { /* noop */ }
      this.emit('close', u && this.idx.by[u]);
    }
  };
  View.prototype._refreshPanel = function () {
    if (this.editing) { this._renderEditor(); return; }
    if (this.panelUid != null && this.idx.by[this.panelUid]) this.openDetail(this.panelUid, false, true);
  };

  View.prototype._canProgress = function () {
    var perm = this.data && this.data.permissions || {};
    if (this.opts.canProgress === false) return false;
    if (this.offline) return perm.progress !== false || this.opts.canProgress === true;
    return perm.progress === true;
  };
  View.prototype._canEdit = function () {
    var perm = this.data && this.data.permissions || {};
    return this.offline ? perm.edit !== false : perm.edit === true;
  };

  View.prototype.condText = function (c, n) {
    var t = this.t, idx = this.idx, p = c.params || {};
    function nm(u) { var x = idx.by[u]; return x ? (x.name || u) : String(u); }
    function nms(a) { return (Array.isArray(a) ? a : []).map(nm).join(', '); }
    switch (c.type) {
      case 'parent': { var pre = prerequisites(idx, n.uid); return pre.length ? fmt(t.cond_parent, { name: nms(pre) }) : t.cond_parent_none; }
      case 'node': return fmt(t.cond_node, { name: nm(p.node) });
      case 'all': return fmt(t.cond_all, { names: nms(p.nodes) });
      case 'any': return fmt(t.cond_any, { min: num(p.min) || 1, names: nms(p.nodes) });
      case 'children': return num(p.min) == null ? t.cond_children_all : fmt(t.cond_children_min, { min: num(p.min) });
      case 'metric': {
        var cur = this.data.metrics ? this.data.metrics[p.metric] : undefined;
        return fmt(t.cond_metric, { metric: p.metric || '?', op: OP_GLYPH[p.op || '>='] || p.op, value: p.value != null ? p.value : 0, current: cur != null ? cur : 0 });
      }
      case 'manual': return t.cond_manual;
      case 'callback': return fmt(t.cond_callback, { name: p.name || '?' });
      default: return fmt(t.cond_unknown, { type: c.type });
    }
  };

  View.prototype.openDetail = function (u, noFocus, refresh) {
    if (this.editing) { this._select(u); return; }
    var n = this.idx && this.idx.by[u]; if (!n) return;
    var t = this.t, self = this;
    var prev = this.panelUid;
    this.panelUid = u;
    var body = this._panelShell(n.name || u, t['kind_' + n.kind]);
    var p = this.panel;
    p.style.setProperty('--c', this.colors[u]);
    var hero = h('div', 'st-hero', body);
    body.insertBefore(hero, body.firstChild);
    var ic = h('div', 'st-hero-icon st-' + n.status, hero);
    if (n.icon) ic.appendChild(iconSvg(n.icon)); else ic.appendChild(iconSvg(n.kind === 'hub' ? 'star' : (n.kind === 'origin' ? 'target' : 'check')));
    var badge = h('span', 'st-status st-' + n.status, hero, this._statusLabel(n.status));
    badge.setAttribute('data-status', n.status);
    if (n.description) h('p', 'st-desc', body, n.description);
    // récompense
    var rw = n.reward || {}, rkeys = Object.keys(rw);
    if (rkeys.length) {
      var sec = h('section', 'st-sec', body);
      h('h3', null, sec, t.reward);
      var ul = h('ul', 'st-reward', sec);
      rkeys.forEach(function (k) {
        var v = rw[k], txt;
        if (k === 'xp') txt = fmt(t.xp, { xp: v });
        else if (k === 'badge') txt = fmt(t.badge, { badge: v });
        else txt = k + ' : ' + (typeof v === 'object' ? JSON.stringify(v) : String(v));
        h('li', k === 'xp' ? 'st-xp' : null, ul, txt);
      });
    }
    // conditions
    var ctx = { done: {}, idx: this.idx, metrics: this.data.metrics || {}, offline: this.offline };
    this.data.nodes.forEach(function (x) { if (x.status === 'completed') ctx.done[x.uid] = true; });
    ['unlock', 'complete'].forEach(function (ph) {
      var list = n.conditions.filter(function (c) { return (c.phase || 'unlock') === ph; });
      if (ph === 'unlock' && !list.length && prerequisites(self.idx, n.uid).length) list = [{ type: 'parent', params: {}, phase: 'unlock' }];
      if (!list.length) return;
      var sec = h('section', 'st-sec', body);
      h('h3', null, sec, t['conditions_' + ph]);
      var ul = h('ul', 'st-conds', sec);
      list.forEach(function (c) {
        var ok = n.status === 'completed' ? true : evalCondition(c, n, ctx, 'unlock');
        var li = h('li', 'st-cond ' + (ok === true ? 'st-ok' : (ok === null ? 'st-unknown' : 'st-ko')), ul);
        h('span', 'st-cond-mark', li, ok === true ? '✓' : (ok === null ? '?' : '○')).setAttribute('aria-hidden', 'true');
        h('span', 'st-cond-txt', li, self.condText(c, n));
      });
    });
    // enfants
    var kids = this.idx.kids[u] || [];
    if (kids.length) {
      var sk = h('section', 'st-sec', body);
      h('h3', null, sk, t.children);
      var ulk = h('ul', 'st-children', sk);
      kids.forEach(function (k) {
        var kn = self.idx.by[k];
        var li = h('li', null, ulk);
        var b = h('button', 'st-child st-' + kn.status, li); b.type = 'button';
        b.style.setProperty('--c', self.colors[k]);
        h('span', 'st-child-dot', b).setAttribute('aria-hidden', 'true');
        h('span', 'st-child-name', b, kn.name || k);
        h('span', 'st-child-status', b, self._statusLabel(kn.status));
        b.addEventListener('click', function () {
          if (kn.kind === 'hub') { self.expand(k); self.openDetail(k); } else { self.focus(k); self.openDetail(k); }
        });
      });
    }
    // action
    if (this._canProgress() && n.status === 'available') {
      var act = h('div', 'st-actions', body);
      var btn = h('button', 'st-btn st-btn-primary', act, t.complete); btn.type = 'button';
      btn.addEventListener('click', function () { btn.disabled = true; self.complete(u).then(function () { btn.disabled = false; }); });
    }
    this._showPanel(!noFocus && !refresh);
    if (!refresh && prev !== u) this.emit('open', n);
  };

  /* ---------- progression */
  View.prototype.complete = function (u) {
    var n = this.idx && this.idx.by[u], self = this, t = this.t;
    if (!n) return Promise.resolve(false);
    var done = function (xpN) {
      var fresh = self.idx.by[u] || n;
      self._burst(u);
      var msg = fmt(t.completedToast, { name: fresh.name || u });
      var xp = num(fresh.reward && fresh.reward.xp);
      if (xp) msg += ' · ' + fmt(t.xp, { xp: xp });
      self.toast(msg, 'success');
      self.emit('complete', fresh);
      if (typeof self.opts.onComplete === 'function') self.opts.onComplete(fresh, self.api);
      return true;
    };
    if (this.offline) {
      var ctx = { done: {}, idx: this.idx, metrics: this.data.metrics || {}, offline: true };
      this.data.nodes.forEach(function (x) { if (x.status === 'completed') ctx.done[x.uid] = true; });
      var cc = n.conditions.filter(function (c) { return c.phase === 'complete'; });
      var ok = n.status === 'available' && cc.every(function (c) { return evalCondition(c, n, ctx, 'complete') === true; });
      if (!ok) { this.toast(t.cannotComplete, 'error'); return Promise.resolve(false); }
      n.progress = assign({}, n.progress, { completed_at: new Date().toISOString() });
      n.status = 'completed';
      this._recompute(true);
      this._renderHeader();
      this._refreshPanel();
      return Promise.resolve(done());
    }
    return this._api('complete', { node: u }).then(function (d) {
      if (d && Array.isArray(d.nodes)) self.setData(d); else return self.reload().then(function () { return done(); });
      return done();
    }).catch(function (e) { self._error(e); return false; });
  };

  View.prototype.metric = function (key, value, mode) {
    var self = this;
    mode = mode === 'inc' ? 'inc' : 'set';
    if (this.offline) {
      var m = this.data.metrics || (this.data.metrics = {});
      m[key] = mode === 'inc' ? (num(m[key]) || 0) + (num(value) || 0) : num(value);
      this._recompute(true); this._renderHeader(); this._refreshPanel();
      this.emit('metric', { metric: key, value: m[key] });
      return Promise.resolve(true);
    }
    return this._api('metric', { metric: key, value: value, mode: mode }).then(function (d) {
      if (d && Array.isArray(d.nodes)) self.setData(d); else return self.reload();
      self.emit('metric', { metric: key, value: value });
      return true;
    }).catch(function (e) { self._error(e); return false; });
  };

  /* ---------- export des données */
  View.prototype.getData = function () {
    if (!this.data) return null;
    return clone(this.data);
  };
  View.prototype._payload = function () {
    var d = this.data;
    var tree = clone(d.tree) || {};
    if (!tree.slug && this.slug) tree.slug = this.slug;
    return {
      tree: tree,
      nodes: d.nodes.map(function (n) {
        return {
          uid: n.uid, parent: n.parent || null, kind: n.kind, slug: n.slug || null, name: n.name || '',
          description: n.description || null, icon: n.icon || null,
          x: n.x == null ? null : n.x, y: n.y == null ? null : n.y,
          theme: clone(n.theme) || {}, reward: clone(n.reward) || {}, sort: n.sort || 0, meta: clone(n.meta) || {},
          conditions: (n.conditions || []).map(function (c, i) { return { uid: c.uid || uid('c_'), phase: c.phase || 'unlock', type: c.type, params: clone(c.params) || {}, sort: c.sort != null ? c.sort : i }; })
        };
      }),
      links: d.links.map(function (l) { return { uid: l.uid, from: l.from, to: l.to, type: l.type || 'path' }; })
    };
  };

  /* ================================================================== ÉDITEUR */
  View.prototype.setEdit = function (on) {
    on = !!on && !!this.data && this._canEdit();
    if (on === this.editing) { if (on) this._renderToolbar(); return; }
    this.editing = on;
    this.root.classList.toggle('st-editing', on);
    this.toolbar.hidden = !on;
    this.hint.hidden = !on;
    this.linkMode = 0;
    if (on) {
      this.slots.cur = { uid: null, t: 0 }; this.slots.prev = { uid: null, t: 0 }; delete this.tweens.slots;
      this.root.classList.remove('st-expanded');
      this.panelUid = null;
      this._renderToolbar();
      this._hintText(this.t.ed_hint);
      this._renderEditor();
    } else {
      this.selected = null; this.selectedLink = null;
      this.closePanel(true);
    }
    this._layout(); this._sync(); this._dirty = true;
    this.emit('edit', on);
  };

  View.prototype._hintText = function (txt) { this.hint.textContent = txt; };

  View.prototype._renderToolbar = function () {
    var tb = this.toolbar, t = this.t, self = this; clear(tb);
    function btn(icon, label, fn, opts2) {
      opts2 = opts2 || {};
      var b = h('button', 'st-tool' + (opts2.cls ? ' ' + opts2.cls : ''), tb); b.type = 'button';
      if (icon) b.appendChild(typeof icon === 'string' ? uiIcon(icon) : icon);
      h('span', 'st-tool-label', b, label);
      b.title = label;
      if (opts2.disabled) b.disabled = true;
      if (opts2.pressed != null) b.setAttribute('aria-pressed', opts2.pressed ? 'true' : 'false');
      b.addEventListener('click', fn);
      return b;
    }
    var has = !!this.selected, hasL = !!this.selectedLink;
    btn('add', t.ed_add, function () { self._addChild(); });
    btn('trash', t.ed_delete, function () { self._delete(); }, { disabled: !has && !hasL });
    btn('link', t.ed_link, function () { self._setLinkMode(self.linkMode ? 0 : (self.selected ? 2 : 1)); }, { pressed: !!this.linkMode });
    btn('unlink', t.ed_unlink, function () { self._unlink(); }, { disabled: !has && !hasL });
    h('span', 'st-tool-sep', tb);
    btn(iconSvg('heart'), t.ed_heart, function () { self.applyPreset('heart'); });
    btn('download', t.ed_export, function () { self._export(); });
    btn('upload', t.ed_import, function () { self.fileInput.click(); });
    h('span', 'st-tool-sep', tb);
    btn('undo', t.ed_undo, function () { self.undo(); }, { disabled: !this.undoStack.length });
    btn('save', t.ed_save, function () { self.save(); }, { cls: 'st-tool-primary' + (this.dirtyEdits ? ' st-dirty' : '') });
  };

  View.prototype._setLinkMode = function (m) {
    this.linkMode = m;
    this.linkSrc = m === 2 ? this.selected : null;
    this._hintText(m === 1 ? this.t.ed_linkSource : (m === 2 ? this.t.ed_linkPick : this.t.ed_hint));
    this.root.classList.toggle('st-linking', !!m);
    this._renderToolbar(); this._sync();
  };

  View.prototype._snapshot = function () { return JSON.stringify({ tree: this.data.tree, nodes: this.data.nodes, links: this.data.links, metrics: this.data.metrics }); };
  View.prototype._pushUndo = function (snap) {
    this.undoStack.push(snap || this._snapshot());
    if (this.undoStack.length > 60) this.undoStack.shift();
    this._markDirty();
  };
  View.prototype._markDirty = function () {
    if (!this.dirtyEdits) { this.dirtyEdits = true; if (this.editing) this._renderToolbar(); }
  };
  View.prototype.undo = function () {
    if (!this.undoStack.length) { this.toast(this.t.ed_nothingToUndo); return; }
    var s0 = JSON.parse(this.undoStack.pop());
    var d = this.data;
    d.tree = s0.tree; d.nodes = s0.nodes; d.links = s0.links; d.metrics = s0.metrics || d.metrics;
    this.setData(d);
    this._renderToolbar(); this._renderEditor();
    this.emit('change', this.api);
  };

  View.prototype._changed = function (keepPanel) {
    this._recompute(false);
    this._renderHeader();
    if (!keepPanel) this._renderEditor();
    this._renderToolbar();
    this.emit('change', this.api);
  };

  View.prototype._select = function (u) {
    this.selected = u; this.selectedLink = null;
    this._sync(); this._renderToolbar(); this._renderEditor();
    this.emit('select', this.idx.by[u]);
  };
  View.prototype._selectLink = function (id) {
    this.selectedLink = id; this.selected = null;
    this._sync(); this._renderToolbar(); this._renderEditor();
  };

  View.prototype._addChild = function () {
    var idx = this.idx, t = this.t;
    var parent = this.selected || (idx.origin ? idx.origin.uid : null);
    var pn = parent ? idx.by[parent] : null;
    var kind = !pn || pn.kind === 'origin' ? 'hub' : 'node';
    if (!idx.origin && !this.data.nodes.length) kind = 'origin';
    this._pushUndo();
    var sib = (parent ? idx.kids[parent] : []) || [];
    var maxSort = sib.reduce(function (m, u) { return Math.max(m, idx.by[u].sort || 0); }, -1);
    var n = {
      uid: uid('n_'), parent: parent, kind: kind, slug: null,
      name: kind === 'hub' ? t.ed_newHub : (kind === 'origin' ? t.kind_origin : t.ed_newNode),
      description: '', icon: kind === 'hub' ? 'star' : null, x: null, y: null, theme: {}, reward: {}, sort: maxSort + 1, meta: {}, conditions: []
    };
    if (kind === 'origin') { n.parent = null; n.x = 0; n.y = 0; }
    this.data.nodes.push(n);
    this.selected = n.uid; this.selectedLink = null;
    this._changed();
    var nameInput = this.panel.querySelector('input[name="name"]');
    if (nameInput) try { nameInput.focus(); nameInput.select(); } catch (e) { /* noop */ }
  };

  View.prototype._descendants = function (u) {
    var out = [], q = [u], kids = this.idx.kids;
    while (q.length) { var x = q.shift(); (kids[x] || []).forEach(function (k) { if (out.indexOf(k) < 0 && k !== u) { out.push(k); q.push(k); } }); }
    return out;
  };

  View.prototype._delete = function () {
    var t = this.t;
    if (this.selectedLink) {
      var id = this.selectedLink;
      if (id.indexOf('p:') === 0) { this.toast(t.ed_parentLink); return; }
      this._pushUndo();
      this.data.links = this.data.links.filter(function (l) { return l.uid !== id; });
      this.selectedLink = null; this._changed(); return;
    }
    var u = this.selected; if (!u) { this.toast(t.ed_selectFirst); return; }
    var n = this.idx.by[u], desc = this._descendants(u);
    if (desc.length && typeof win.confirm === 'function' && !win.confirm(fmt(t.ed_confirmDelete, { name: n.name || u, count: desc.length }))) return;
    this._pushUndo();
    var kill = {}; kill[u] = true; desc.forEach(function (d) { kill[d] = true; });
    this.data.nodes = this.data.nodes.filter(function (x) { return !kill[x.uid]; });
    this.data.links = this.data.links.filter(function (l) { return !kill[l.from] && !kill[l.to]; });
    this.selected = null;
    this._changed();
  };

  View.prototype._createLink = function (a, b) {
    var t = this.t;
    if (!a || !b || a === b) return;
    var exists = this.data.links.some(function (l) { return (l.from === a && l.to === b) || (l.from === b && l.to === a); });
    var n = this.idx.by[b], m = this.idx.by[a];
    if (exists || n.parent === a || m.parent === b) { this.toast(t.ed_linkExists); return; }
    this._pushUndo();
    var l = { uid: uid('l_'), from: a, to: b, type: 'path' };
    this.data.links.push(l);
    this.selectedLink = l.uid; this.selected = null;
    this._changed();
    this.toast(t.ed_linkCreated);
  };

  View.prototype._unlink = function () {
    var t = this.t, u = this.selected;
    if (this.selectedLink) { this._delete(); return; }
    if (!u) { this.toast(t.ed_selectFirst); return; }
    var before = this.data.links.length;
    var rest = this.data.links.filter(function (l) { return l.from !== u && l.to !== u; });
    if (rest.length === before) { this.toast(fmt(t.ed_unlinked, { count: 0 })); return; }
    this._pushUndo();
    this.data.links = rest;
    this._changed();
    this.toast(fmt(t.ed_unlinked, { count: before - rest.length }));
  };

  View.prototype.applyPreset = function (name) {
    var t = this.t, idx = this.idx, O = this.O, self = this;
    var hubs = this.data.nodes.filter(function (n) { return n.kind === 'hub'; });
    if (!hubs.length) { this.toast(t.ed_heartNeed); return; }
    var R = num(this.settings.radius) || 420;
    var central = this.central ? idx.by[this.central] : null;
    var others = hubs.filter(function (n) { return n !== central; }).sort(function (a, b) { return (a.sort - b.sort) || (idx.order[a.uid] - idx.order[b.uid]); });
    if (!central && others.length >= 10 && name === 'heart') { central = others.splice(9, 1)[0]; }
    this._pushUndo();
    var rc2 = ringCounts(others.length);
    var pts = name === 'heart' ? layouts.heart(R) : (name === 'ring' ? layouts.ring(rc2, R / Math.max(1, rc2.length)) : layouts.radial(others.length, R));
    function move(n, x, y) {
      var bx = self.base[n.uid].x, by = self.base[n.uid].y, dx = x - bx, dy = y - by;
      self._descendants(n.uid).forEach(function (d) { var dn = idx.by[d]; if (dn.x != null && dn.y != null && dn.kind !== 'hub') { dn.x = round1(dn.x + dx); dn.y = round1(dn.y + dy); } });
      n.x = round1(x); n.y = round1(y);
    }
    others.forEach(function (n, i) { if (i < pts.length) move(n, O.x + pts[i].x, O.y + pts[i].y); });
    if (central) { move(central, O.x, O.y); central.meta = assign({}, central.meta, { central: true }); }
    if (idx.origin) { idx.origin.x = round1(O.x); idx.origin.y = round1(O.y); }
    this.data.tree.layout = name;
    this._changed();
    this._fitAll(true);
    this.toast(t.ed_heartDone);
  };

  View.prototype._export = function () {
    var payload = this._payload();
    var blob = new win.Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' });
    var a = doc.createElement('a');
    var url = win.URL.createObjectURL(blob);
    a.href = url; a.download = (payload.tree.slug || 'successtree') + '.json';
    a.style.display = 'none';
    this.root.appendChild(a); a.click(); a.remove();
    setTimeout(function () { win.URL.revokeObjectURL(url); }, 2000);
    this.emit('export', payload);
  };

  View.prototype._importFile = function (file) {
    if (!file) return;
    var self = this, t = this.t;
    var rd = new win.FileReader();
    rd.onload = function () {
      try {
        var j = JSON.parse(String(rd.result));
        if (isObj(j) && isObj(j.data)) j = j.data;
        if (!isObj(j) || !Array.isArray(j.nodes)) throw new Error('nodes[] manquant');
        self._pushUndo();
        var keep = self.data;
        var tree = assign({}, isObj(j.tree) ? j.tree : {}, { uid: keep.tree.uid || (j.tree && j.tree.uid), slug: keep.tree.slug || (j.tree && j.tree.slug) });
        self.setData({ tree: tree, nodes: j.nodes, links: j.links || [], metrics: keep.metrics, permissions: keep.permissions, subject: keep.subject });
        self.selected = null; self.selectedLink = null;
        self._fitAll(true);
        self._renderToolbar(); self._renderEditor();
        self.toast(t.ed_imported);
        self.emit('change', self.api);
      } catch (e) { self.toast(fmt(t.ed_importError, { msg: e.message }), 'error'); }
    };
    rd.readAsText(file);
  };

  View.prototype.save = function () {
    var self = this, t = this.t, payload = this._payload();
    if (typeof this.opts.onSave === 'function') {
      try { if (this.opts.onSave(payload, this.api) === false) return Promise.resolve(false); } catch (e) { this._error(e); return Promise.resolve(false); }
    }
    if (this.offline) {
      this.dirtyEdits = false; this._renderToolbar();
      this.toast(t.ed_savedLocal, 'success');
      this.emit('save', payload);
      return Promise.resolve(true);
    }
    this.toast(t.ed_saving);
    return this._api('save', payload).then(function (d) {
      self.dirtyEdits = false;
      if (d && Array.isArray(d.nodes)) self.setData(d);
      self._renderToolbar();
      self.toast(t.ed_saved, 'success');
      self.emit('save', payload);
      return true;
    }).catch(function (e) { self._error(e); return false; });
  };

  /* ---------- panneau propriétés */
  View.prototype._renderEditor = function () {
    if (!this.editing) return;
    var t = this.t, self = this;
    if (this.selectedLink) { this._renderLinkEditor(); return; }
    var u = this.selected, n = u && this.idx.by[u];
    if (!n) {
      this.panelUid = null;
      var body0 = this._panelShell(t.ed_properties);
      h('p', 'st-muted', body0, t.ed_noSelection);
      this.panelUid = '';
      this._showPanel(false);
      if (this.mobile) { this.panel.classList.remove('st-open'); this.panel.hidden = true; }
      return;
    }
    this.panelUid = u;
    var body = this._panelShell(n.name || t.ed_properties, t.ed_properties + ' · ' + (t['kind_' + n.kind] || n.kind));
    this.panel.style.setProperty('--c', this.colors[u]);
    var form = h('form', 'st-form', body);
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    var pending = null;
    function snap() { pending = self._snapshot(); }
    function commit() { self._pushUndo(pending || undefined); pending = null; }
    function field(label, input, full) {
      var f = h('div', 'st-field' + (full ? ' st-field-full' : ''), form);
      var id = self.id + '-f' + (++self._fid || (self._fid = 1));
      var lb = h('label', null, f, label); lb.htmlFor = id;
      input.id = id;
      f.appendChild(input);
      var err = h('div', 'st-err', f); err.setAttribute('aria-live', 'polite');
      input.addEventListener('focus', snap);
      return { f: f, err: err, input: input };
    }
    // nom
    var iName = h('input'); iName.type = 'text'; iName.name = 'name'; iName.value = n.name || ''; iName.maxLength = 190;
    field(t.ed_name, iName, true);
    iName.addEventListener('input', function () { n.name = iName.value; self._sync(); self._dirty = true; var ti = self.panel.querySelector('.st-panel-title'); if (ti) ti.textContent = n.name; });
    iName.addEventListener('change', function () { commit(); self._changed(true); });
    // slug
    var iSlug = h('input'); iSlug.type = 'text'; iSlug.value = n.slug || ''; iSlug.maxLength = 120; iSlug.spellcheck = false;
    var fSlug = field(t.ed_slug, iSlug);
    iSlug.addEventListener('change', function () {
      var v = iSlug.value.trim();
      if (v && !/^[a-z0-9][a-z0-9_-]*$/.test(v)) { fSlug.err.textContent = t.ed_invalidSlug; iSlug.setAttribute('aria-invalid', 'true'); return; }
      fSlug.err.textContent = ''; iSlug.removeAttribute('aria-invalid');
      commit(); n.slug = v || null; self._changed(true);
    });
    // kind
    var sKind = h('select');
    ['origin', 'hub', 'node'].forEach(function (k) { var o = h('option', null, sKind, t['kind_' + k] + ' (' + k + ')'); o.value = k; });
    sKind.value = n.kind;
    var fKind = field(t.ed_kind, sKind);
    sKind.addEventListener('change', function () {
      var v = sKind.value;
      if (v === 'origin' && self.idx.origin && self.idx.origin !== n) { fKind.err.textContent = t.ed_oneOrigin; sKind.value = n.kind; return; }
      fKind.err.textContent = ''; commit(); n.kind = v; if (v === 'origin') n.parent = null; self._changed();
    });
    // parent
    var sPar = h('select');
    var excl = {}; excl[u] = true; this._descendants(u).forEach(function (d) { excl[d] = true; });
    var o0 = h('option', null, sPar, t.ed_none); o0.value = '';
    this.data.nodes.forEach(function (x) { if (!excl[x.uid]) { var o = h('option', null, sPar, (x.name || x.uid) + ' · ' + x.kind); o.value = x.uid; } });
    sPar.value = n.parent || '';
    field(t.ed_parent, sPar, true);
    sPar.addEventListener('change', function () { commit(); n.parent = sPar.value || null; self._changed(); });
    // icône
    var fIcon = h('div', 'st-field st-field-full', form);
    h('div', 'st-flabel', fIcon, t.ed_icon);
    var grid = h('div', 'st-icon-grid', fIcon);
    grid.setAttribute('role', 'group'); grid.setAttribute('aria-label', t.ed_icon);
    var none = h('button', 'st-icon-btn st-icon-none', grid, '∅'); none.type = 'button'; none.title = t.ed_noIcon; none.setAttribute('aria-label', t.ed_noIcon);
    none.setAttribute('aria-pressed', !n.icon ? 'true' : 'false');
    none.addEventListener('click', function () { self._pushUndo(); n.icon = null; self._changed(); });
    ICON_NAMES.forEach(function (nm) {
      var b = h('button', 'st-icon-btn', grid); b.type = 'button'; b.title = nm; b.setAttribute('aria-label', nm);
      b.setAttribute('aria-pressed', n.icon === nm ? 'true' : 'false');
      b.appendChild(iconSvg(nm));
      b.addEventListener('click', function () { self._pushUndo(); n.icon = nm; self._changed(); });
    });
    var iIconTxt = h('input'); iIconTxt.type = 'text'; iIconTxt.maxLength = 8; iIconTxt.value = n.icon && !ICONS[n.icon] ? n.icon : '';
    iIconTxt.placeholder = '🔥'; iIconTxt.className = 'st-icon-text-input';
    var lbI = h('label', 'st-inline', fIcon, t.ed_iconText); lbI.appendChild(iIconTxt);
    iIconTxt.addEventListener('focus', snap);
    iIconTxt.addEventListener('change', function () { commit(); n.icon = iIconTxt.value.trim() || null; self._changed(); });
    // couleur
    var fCol = h('div', 'st-field', form);
    var cid = this.id + '-col';
    var lbc = h('label', null, fCol, t.ed_color); lbc.htmlFor = cid;
    var rowC = h('div', 'st-row', fCol);
    var iCol = h('input', null, rowC); iCol.type = 'color'; iCol.id = cid; iCol.value = toHex(this.colors[u]);
    var bInh = h('button', 'st-btn st-btn-small', rowC, t.ed_inherit); bInh.type = 'button';
    bInh.disabled = !n.theme.color;
    iCol.addEventListener('focus', snap);
    iCol.addEventListener('input', function () { n.theme.color = iCol.value; self._colors(); self._sync(); self._dirty = true; });
    iCol.addEventListener('change', function () { commit(); n.theme.color = iCol.value; self._changed(); });
    bInh.addEventListener('click', function () { self._pushUndo(); delete n.theme.color; self._changed(); });
    // ordre
    var iSort = h('input'); iSort.type = 'number'; iSort.step = '1'; iSort.value = String(n.sort || 0);
    field(t.ed_sort, iSort);
    iSort.addEventListener('change', function () { commit(); n.sort = Math.round(num(iSort.value) || 0); self._changed(true); });
    // position
    var fPos = h('div', 'st-field st-field-full', form);
    h('div', 'st-flabel', fPos, t.ed_position);
    var rowP = h('div', 'st-row', fPos);
    var iX = h('input', null, rowP); iX.type = 'number'; iX.step = '0.1'; iX.setAttribute('aria-label', 'x');
    var iY = h('input', null, rowP); iY.type = 'number'; iY.step = '0.1'; iY.setAttribute('aria-label', 'y');
    var bAuto = h('button', 'st-btn st-btn-small', rowP, t.ed_auto); bAuto.type = 'button';
    this._posFields = { x: iX, y: iY, u: u };
    this._syncPosFields();
    [iX, iY].forEach(function (inp) {
      inp.addEventListener('focus', snap);
      inp.addEventListener('change', function () {
        commit();
        var x = num(iX.value), y = num(iY.value);
        if (x == null || y == null) { var b = self.base[u]; x = x == null ? b.x : x; y = y == null ? b.y : y; }
        n.x = round1(x); n.y = round1(y); self._changed(true);
      });
    });
    bAuto.addEventListener('click', function () { self._pushUndo(); n.x = null; n.y = null; self._changed(); });
    // hub : sous-titre + central
    if (n.kind === 'hub') {
      var iSub = h('input'); iSub.type = 'text'; iSub.maxLength = 80; iSub.value = typeof n.meta.subtitle === 'string' ? n.meta.subtitle : '';
      field(t.ed_subtitle, iSub, true);
      iSub.addEventListener('change', function () { commit(); if (iSub.value.trim()) n.meta.subtitle = iSub.value.trim(); else delete n.meta.subtitle; self._changed(true); });
      var fc = h('div', 'st-field st-field-full', form);
      var lc = h('label', 'st-check', fc);
      var cb = h('input', null, lc); cb.type = 'checkbox'; cb.checked = n.meta.central === true;
      h('span', null, lc, t.ed_central);
      cb.addEventListener('change', function () {
        self._pushUndo();
        self.data.nodes.forEach(function (x) { if (x.meta && x.meta.central) delete x.meta.central; });
        if (cb.checked) n.meta.central = true;
        self._changed();
      });
    }
    // description
    var iDesc = h('textarea'); iDesc.rows = 3; iDesc.value = n.description || '';
    field(t.ed_description, iDesc, true);
    iDesc.addEventListener('change', function () { commit(); n.description = iDesc.value; self._changed(true); });
    // conditions
    this._conditionsEditor(form, n, snap, commit);
    // JSON
    function jsonField(label, key, rows) {
      var ta = h('textarea', 'st-code'); ta.rows = rows || 3; ta.spellcheck = false;
      ta.value = JSON.stringify(n[key] || {}, null, 1);
      var fx = field(label, ta, true);
      ta.addEventListener('input', function () {
        try { var v = JSON.parse(ta.value || '{}'); if (!isObj(v)) throw new Error('objet {…} attendu'); fx.err.textContent = ''; ta.removeAttribute('aria-invalid'); }
        catch (e) { fx.err.textContent = fmt(t.ed_invalidJson, { msg: e.message }); ta.setAttribute('aria-invalid', 'true'); }
      });
      ta.addEventListener('change', function () {
        var v;
        try { v = JSON.parse(ta.value || '{}'); if (!isObj(v)) throw new Error('objet {…} attendu'); } catch (e) { fx.err.textContent = fmt(t.ed_invalidJson, { msg: e.message }); return; }
        commit(); n[key] = v; self._changed(key !== 'theme');
        if (key === 'theme') return;
      });
    }
    jsonField(t.ed_reward, 'reward', 2);
    jsonField(t.ed_meta, 'meta', 2);
    jsonField(t.ed_theme, 'theme', 2);
    this._showPanel(false);
  };

  View.prototype._syncPosFields = function () {
    var f = this._posFields; if (!f || !this.idx.by[f.u]) return;
    var n = this.idx.by[f.u], b = this.base[f.u] || { x: 0, y: 0 };
    f.x.value = n.x != null ? String(n.x) : ''; f.y.value = n.y != null ? String(n.y) : '';
    f.x.placeholder = 'auto ' + round1(b.x); f.y.placeholder = 'auto ' + round1(b.y);
  };

  View.prototype._conditionsEditor = function (form, n, snap, commit) {
    var t = this.t, self = this;
    var wrap = h('fieldset', 'st-field st-field-full st-conds-ed', form);
    h('legend', null, wrap, t.ed_conditions);
    var list = h('div', 'st-cond-rows', wrap);
    var others = this.data.nodes.filter(function (x) { return x.uid !== n.uid; });
    function nodeSelect(value, multiple) {
      var sel = h('select'); if (multiple) { sel.multiple = true; sel.size = Math.min(5, Math.max(3, others.length)); }
      if (!multiple) { var o0 = h('option', null, sel, '—'); o0.value = ''; }
      others.forEach(function (x) {
        var o = h('option', null, sel, x.name || x.uid); o.value = x.uid;
        if (multiple ? (value || []).indexOf(x.uid) >= 0 : value === x.uid) o.selected = true;
      });
      return sel;
    }
    function labelled(row, label, input) { var l = h('label', 'st-mini', row); h('span', null, l, label); l.appendChild(input); input.addEventListener('focus', snap); return input; }
    function render() {
      clear(list);
      n.conditions.forEach(function (c, i) {
        var row = h('div', 'st-cond-row', list);
        var sPh = h('select'); ['unlock', 'complete'].forEach(function (p) { var o = h('option', null, sPh, t['phase_' + p]); o.value = p; }); sPh.value = c.phase || 'unlock';
        labelled(row, t.ed_phase || 'Phase', sPh);
        var sTy = h('select'); COND_TYPES.forEach(function (ty) { var o = h('option', null, sTy, t['ctype_' + ty] || ty); o.value = ty; }); sTy.value = c.type;
        labelled(row, t.ed_type || 'Type', sTy);
        var p = c.params || (c.params = {});
        var upd = function () { commit(); self._changed(true); raw.value = JSON.stringify(n.conditions, null, 1); rawErr.textContent = ''; };
        sPh.addEventListener('change', function () { c.phase = sPh.value; upd(); });
        sTy.addEventListener('change', function () { c.type = sTy.value; c.params = {}; upd(); render(); });
        if (c.type === 'node') {
          var sn = labelled(row, t.p_node, nodeSelect(p.node, false));
          sn.addEventListener('change', function () { p.node = sn.value || null; upd(); });
        } else if (c.type === 'all' || c.type === 'any') {
          var sm = labelled(row, t.p_nodes, nodeSelect(p.nodes, true));
          sm.addEventListener('change', function () { p.nodes = Array.prototype.filter.call(sm.options, function (o) { return o.selected; }).map(function (o) { return o.value; }); upd(); });
          if (c.type === 'any') {
            var im = h('input'); im.type = 'number'; im.min = '1'; im.value = p.min != null ? String(p.min) : '1';
            labelled(row, t.p_min, im).addEventListener('change', function () { p.min = Math.max(1, Math.round(num(im.value) || 1)); upd(); });
          }
        } else if (c.type === 'children') {
          var ic = h('input'); ic.type = 'number'; ic.min = '0'; ic.placeholder = t.p_minAll; ic.value = p.min != null ? String(p.min) : '';
          labelled(row, t.p_min, ic).addEventListener('change', function () { p.min = num(ic.value) == null ? null : Math.round(num(ic.value)); upd(); });
        } else if (c.type === 'metric') {
          var ik = h('input'); ik.type = 'text'; ik.value = p.metric || ''; ik.placeholder = 'posts_published';
          labelled(row, t.p_metric, ik).addEventListener('change', function () { p.metric = ik.value.trim(); upd(); });
          var so = h('select'); OPS.forEach(function (op) { var o = h('option', null, so, OP_GLYPH[op] + ' (' + op + ')'); o.value = op; }); so.value = p.op || '>=';
          labelled(row, t.p_op, so).addEventListener('change', function () { p.op = so.value; upd(); });
          var iv = h('input'); iv.type = 'number'; iv.step = 'any'; iv.value = p.value != null ? String(p.value) : '';
          labelled(row, t.p_value, iv).addEventListener('change', function () { p.value = num(iv.value) == null ? 0 : num(iv.value); upd(); });
        } else if (c.type === 'callback') {
          var ib = h('input'); ib.type = 'text'; ib.value = p.name || ''; ib.placeholder = 'has_paid';
          labelled(row, t.p_name, ib).addEventListener('change', function () { p.name = ib.value.trim(); upd(); });
        }
        var rm = h('button', 'st-btn st-btn-small st-btn-ghost', row, '×'); rm.type = 'button';
        rm.setAttribute('aria-label', t.remove); rm.title = t.remove;
        rm.addEventListener('click', function () { self._pushUndo(); n.conditions.splice(i, 1); self._changed(true); render(); raw.value = JSON.stringify(n.conditions, null, 1); });
        var txt = h('div', 'st-cond-preview', row, self.condText(c, n));
        txt.setAttribute('aria-hidden', 'true');
      });
    }
    var add = h('button', 'st-btn st-btn-small', wrap, t.ed_addCondition); add.type = 'button';
    add.addEventListener('click', function () {
      self._pushUndo();
      n.conditions.push({ uid: uid('c_'), phase: 'unlock', type: 'node', params: {} });
      self._changed(true); render(); raw.value = JSON.stringify(n.conditions, null, 1);
    });
    var det = h('details', 'st-raw', wrap);
    h('summary', null, det, t.ed_rawJson);
    var raw = h('textarea', 'st-code', det); raw.rows = 5; raw.spellcheck = false; raw.value = JSON.stringify(n.conditions, null, 1);
    raw.setAttribute('aria-label', t.ed_conditions + ' JSON');
    var rawErr = h('div', 'st-err', det); rawErr.setAttribute('aria-live', 'polite');
    var apply = h('button', 'st-btn st-btn-small', det, t.ed_apply); apply.type = 'button';
    function validate(v) {
      if (!Array.isArray(v)) throw new Error('tableau […] attendu');
      v.forEach(function (c, i) {
        if (!isObj(c)) throw new Error('#' + (i + 1) + ' : objet attendu');
        if (COND_TYPES.indexOf(c.type) < 0) throw new Error('#' + (i + 1) + ' : type « ' + c.type + ' » inconnu');
        if (c.phase != null && c.phase !== 'unlock' && c.phase !== 'complete') throw new Error('#' + (i + 1) + ' : phase unlock|complete');
        if (c.params != null && !isObj(c.params)) throw new Error('#' + (i + 1) + ' : params doit être un objet');
        if (c.type === 'metric' && c.params && c.params.op && OPS.indexOf(c.params.op) < 0) throw new Error('#' + (i + 1) + ' : opérateur invalide');
      });
      return v;
    }
    raw.addEventListener('input', function () {
      try { validate(JSON.parse(raw.value || '[]')); rawErr.textContent = ''; raw.removeAttribute('aria-invalid'); }
      catch (e) { rawErr.textContent = fmt(t.ed_invalidConditions, { msg: e.message }); raw.setAttribute('aria-invalid', 'true'); }
    });
    apply.addEventListener('click', function () {
      var v;
      try { v = validate(JSON.parse(raw.value || '[]')); } catch (e) { rawErr.textContent = fmt(t.ed_invalidConditions, { msg: e.message }); return; }
      self._pushUndo();
      n.conditions = v.map(function (c) { return { uid: c.uid || uid('c_'), phase: c.phase === 'complete' ? 'complete' : 'unlock', type: c.type, params: isObj(c.params) ? c.params : {} }; });
      self._changed(true); render();
    });
    render();
  };

  View.prototype._renderLinkEditor = function () {
    var t = this.t, self = this, id = this.selectedLink;
    var rec = this.linkEls[id]; if (!rec) { this.selectedLink = null; this._renderEditor(); return; }
    var l = rec.l;
    this.panelUid = '';
    var body = this._panelShell(t.ed_link_title, l.implicit ? t.ed_parent : t.ed_link_title);
    var dl = h('dl', 'st-dl', body);
    h('dt', null, dl, t.ed_from); h('dd', null, dl, this.idx.by[l.from].name || l.from);
    h('dt', null, dl, t.ed_to); h('dd', null, dl, this.idx.by[l.to].name || l.to);
    if (!l.implicit) {
      var form = h('div', 'st-form', body);
      var f = h('div', 'st-field st-field-full', form);
      var lb = h('label', null, f, t.ed_linkType); lb.htmlFor = this.id + '-lt';
      var sel = h('select', null, f); sel.id = this.id + '-lt';
      [['path', t.ed_link_path], ['visual', t.ed_link_visual]].forEach(function (x) { var o = h('option', null, sel, x[1]); o.value = x[0]; });
      sel.value = l.type;
      sel.addEventListener('change', function () { self._pushUndo(); l.ref.type = sel.value; self._changed(true); });
      var del = h('button', 'st-btn st-btn-danger', body, t.ed_delete); del.type = 'button';
      del.addEventListener('click', function () { self._delete(); });
    } else {
      h('p', 'st-muted', body, t.ed_parentLink);
    }
    this._showPanel(false);
  };

  /* ---------- destruction */
  View.prototype.destroy = function () {
    if (this._destroyed) return;
    this._destroyed = true;
    win.cancelAnimationFrame(this._raf);
    if (this.ro) this.ro.disconnect();
    if (this.io) this.io.disconnect();
    if (this._onWinResize) win.removeEventListener('resize', this._onWinResize);
    (this._listeners || []).forEach(function (x) { x[0].removeEventListener(x[1], x[2], x[3]); });
    clearTimeout(this._toastT); clearTimeout(this._panelT);
    clear(this.el);
    this.el.classList.remove('st-host');
    delete this.el.__successtree;
    this.emit('destroy');
    this.handlers = {};
  };

  /* ================================================================== API publique */
  function parseBool(v) { return v === '' || v === '1' || v === 'true' || v === 'yes' || v === 'on'; }

  function mount(el, opts) {
    if (typeof el === 'string') el = doc.querySelector(el);
    if (!el) throw new Error('SuccessTree.mount : élément introuvable');
    if (el.__successtree) el.__successtree.destroy();
    var v = new View(el, opts || {});
    return v.api;
  }

  function optionsFromElement(el) {
    var d = el.dataset || {}, o = {};
    if (d.endpoint) o.endpoint = d.endpoint;
    if (d.tree) o.tree = d.tree;
    if (d.src) o.src = d.src;
    if (d.edit != null) o.edit = parseBool(d.edit);
    if (d.canProgress != null) o.canProgress = parseBool(d.canProgress);
    if (d.height) o.height = d.height;
    if (d.tabs) {
      if (d.tabs === '1' || d.tabs === 'true' || d.tabs === 'auto') o.tabs = true;
      else { try { var tb = JSON.parse(d.tabs); if (Array.isArray(tb)) o.tabs = tb; } catch (e) { /* ignore */ } }
    }
    if (d.showLabels) o.settings = { showLabels: d.showLabels };
    if (d.header === '0') o.header = false;
    if (d.stats === '0') o.stats = false;
    if (d.data) {
      var src = doc.getElementById(d.data);
      if (src) { try { o.data = JSON.parse(src.textContent); } catch (e) { /* ignore */ } }
    }
    return o;
  }

  function mountAll(rootEl) {
    if (!doc) return [];
    var list = (rootEl || doc).querySelectorAll('[data-successtree]');
    var out = [];
    Array.prototype.forEach.call(list, function (el) {
      if (el.__successtree) { out.push(el.__successtree); return; }
      try { out.push(mount(el, optionsFromElement(el))); } catch (e) { if (win.console) console.error(e); }
    });
    return out;
  }

  var SuccessTree = {
    version: VERSION,
    mount: mount,
    mountAll: mountAll,
    get: function (el) { if (typeof el === 'string') el = doc.querySelector(el); return el ? el.__successtree || null : null; },
    layouts: layouts,
    icons: ICON_NAMES.slice(),
    i18n: FR,
    evaluate: function (data, o) {
      var d = normalizeData(data), idx = buildIndex(d);
      var st = computeStatuses(d, idx, { offline: true, evaluate: !!(o && o.evaluate) });
      d.nodes.forEach(function (n) { n.status = st[n.uid]; });
      return d;
    },
    util: { safeColor: safeColor, hash: hash, easeInOutCubic: easeInOutCubic }
  };

  if (doc && typeof doc.addEventListener === 'function') {
    if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', function () { mountAll(); });
    else setTimeout(function () { mountAll(); }, 0);
  }

  return SuccessTree;
});
