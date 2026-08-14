/**
 * Carte interactive des massifs — enrichissement progressif, jamais un prérequis.
 *
 * Lit UNE SEULE FOIS l'îlot JSON rendu par templates/parts/carte.php et récupère
 * la géométrie par un fetch sur l'URL statique de notre domaine. Aucune route
 * REST, aucune origine tierce, aucun formatage de date, aucune phrase rédigée :
 * toutes les chaînes affichées viennent du serveur.
 *
 * Seule mutation de présentation autorisée : classList.add/remove/toggle et
 * l'attribut hidden. Aucun hexadécimal, aucun nom de jeton CSS, aucune
 * propriété personnalisée, aucun element.style, aucun setProperty.
 *
 * Échec fatal ⇒ la racine est retirée : layout.css pose ses filets sur
 * .bande--carte:has(*), et un conteneur vide en dessinerait deux accolés. Le
 * repli de la chaîne #9 est le FRÈRE de la racine, jamais son descendant : il
 * survit à ce retrait, la bande garde un enfant et dégrade vers l'image
 * statique au lieu de disparaître. Il n'est retiré — et seul son nœud
 * .carte-secours__repli l'est — qu'après un montage RÉUSSI (contrat #9, F-2).
 *
 * Contrat : docs/contracts/issue-7.md · clauses F-* de docs/contracts/issue-9.md
 *
 * @package Massifs
 * @license GPL-2.0-or-later
 */

(function () {
	'use strict';

	/* ═══ 1. Racine, bibliothèque, îlot ═══ */

	var racine = document.querySelector('.carte');
	if (!racine) { return; }

	// Leaflet absent (fichier non servi, script bloqué) : la page redevient
	// exactement ce qu'elle était avant cette chaîne, sans erreur de console.
	if (typeof L === 'undefined') { racine.remove(); return; }

	var noeudDonnees = document.getElementById('carte-donnees');
	if (!noeudDonnees) { racine.remove(); return; }

	var donnees;
	try {
		donnees = JSON.parse(noeudDonnees.textContent);
	} catch (erreur) {
		racine.remove();
		return;
	}

	/* ═══ 2. Validation de forme ═══ */

	function estObjet(v) { return null !== v && 'object' === typeof v && !Array.isArray(v); }
	function estTexte(v) { return 'string' === typeof v && '' !== v; }
	function estNombre(v) { return 'number' === typeof v && isFinite(v); }

	function ilotValide(d) {
		if (!estObjet(d) || 1 !== d.version) { return false; }
		if (!estTexte(d.jour_courant) || !estTexte(d.jour_suivant)) { return false; }
		if (!Array.isArray(d.ordre) || 0 === d.ordre.length) { return false; }
		if (!estObjet(d.massifs) || !estObjet(d.jours)) { return false; }
		if (!estObjet(d.jours[d.jour_courant]) || !estObjet(d.jours[d.jour_suivant])) { return false; }
		if (!estObjet(d.emprise) || !estObjet(d.emprise.bbox) || !estNombre(d.emprise.zoom_max)) { return false; }
		var b = d.emprise.bbox;
		if (!estNombre(b.ouest) || !estNombre(b.sud) || !estNombre(b.est) || !estNombre(b.nord)) { return false; }
		return estObjet(d.geometrie) && estTexte(d.geometrie.url);
	}

	// Version inconnue ou forme cassée : le serveur a changé de contrat et le
	// client ne peut plus garantir le bon jour. Il se retire.
	if (!ilotValide(donnees)) { racine.remove(); return; }

	var toile = racine.querySelector('.carte__toile');
	var barre = racine.querySelector('.carte__barre');
	var panneau = racine.querySelector('.carte__panneau');
	var annonce = racine.querySelector('.carte__annonce');
	if (!toile || !panneau) { racine.remove(); return; }

	/* ═══ 3. Tables fermées et utilitaires ═══ */

	// Table état → classe, FERMÉE et jamais calculée. La discontinuité de
	// non_encore_publie → --non-publie est volontaire : une transformation
	// automatique produirait une classe inexistante. Clé inconnue ⇒ aucune
	// classe, donc aucun aplat : l'échec est bruyant, jamais une teinte fausse.
	var CLASSES_MASSIF = {
		autorise: 'carte__massif--autorise',
		interdit: 'carte__massif--interdit',
		indisponible: 'carte__massif--indisponible',
		hors_saison: 'carte__massif--hors-saison',
		non_encore_publie: 'carte__massif--non-publie'
	};
	var CLASSES_PASTILLE = { autorise: 'pastille--autorise', interdit: 'pastille--interdit' };
	var CLASSES_JALON = { autorise: 'jalon--autorise', interdit: 'jalon--interdit' };

	var TOUTES_CLASSES_MASSIF = Object.keys(CLASSES_MASSIF).map(function (cle) {
		return CLASSES_MASSIF[cle];
	});

	function chacun(liste, action) { Array.prototype.forEach.call(liste, action); }

	function entreeDe(jour, code) {
		var jeu = donnees.jours[jour];
		return estObjet(jeu) && estObjet(jeu[code]) ? jeu[code] : null;
	}

	// Peinture : le niveau officiel si `disponible`, l'état sinon. Un
	// `disponible` sans niveau ne trouve rien, c'est voulu.
	function clePeinture(entree) {
		if (!entree) { return ''; }
		if ('disponible' === entree.etat) {
			return estObjet(entree.niveau) && estTexte(entree.niveau.cle) ? entree.niveau.cle : '';
		}
		return estTexte(entree.etat) ? entree.etat : '';
	}

	// Région live : rien qui déplace le focus. Le vidage force la ré-annonce.
	function annoncer(texte) {
		if (!annonce || !estTexte(texte)) { return; }
		annonce.textContent = '';
		annonce.textContent = texte;
	}

	// Démonter la carte AVANT de détacher la racine : Leaflet écoute le
	// redimensionnement de la fenêtre, et un conteneur détaché sans démontage
	// laisserait un écouteur qui mesure un nœud absent. Un montage à demi
	// construit peut refuser de se démonter — son échec ne doit pas empêcher le
	// retrait, qui est ce qui évite une barre de jour orpheline au-dessus du
	// repli de la chaîne #9. Ce repli, lui, est le FRÈRE de la racine : il ne
	// part jamais par ici.
	function echecFatal() {
		if (carte) {
			try {
				carte.remove();
			} catch (erreur) {
				carte = null;
			}
		}
		racine.remove();
	}

	/* ═══ 4. Carte, panes et motifs ═══ */

	// Démasquer AVANT L.map : un conteneur en display:none mesure 0 × 0, et la
	// toile doit porter sa taille DÉFINITIVE quand Leaflet la mesure — barre et
	// message sont ses frères dans la colonne flex de la racine.
	//
	// Le message §11.3 n'est dans le DOM que si le serveur n'a pas publié demain
	// (A-3) : sa présence suffit, aucune règle métier n'est rejouée ici et aucune
	// phrase n'est écrite. Il paraît AVEC la barre, sans interaction — il explique
	// le bouton désactivé qui apparaît au même instant — et jamais sans elle :
	// hors du sélecteur, la phrase n'aurait aucun jour auquel se rapporter. Il ne
	// porte pas data-jour, la bascule de jour ne l'atteint donc jamais.
	var message = racine.querySelector('.carte__message');

	// Le montage synchrone est enveloppé : une exception de Leaflet laisserait
	// sinon la barre de jour, le message et une toile vide DÉMASQUÉS au-dessus
	// de l'image statique du repli — un chrome de carte orphelin, sans carte.
	// Le `var` reste porté par la fonction, les blocs §6 et suivants voient donc
	// `carte`, `bbox`, `svgMassifs` et `motifs` comme avant.
	var carte;
	var bbox;
	var svgMassifs;
	var motifs = [];

	try {
		if (barre) { barre.hidden = false; }
		if (message) { message.hidden = false; }
		toile.hidden = false;

		carte = L.map(toile, {
			// Sans cela Leaflet lierait les flèches au panoramique et volerait la
			// navigation entre massifs. Le zoom clavier est réimplémenté §9.
			keyboard: false,
			// Seule phase où Leaflet applique une échelle : elle étirerait les motifs.
			zoomAnimation: false,
			fadeAnimation: false,
			markerZoomAnimation: false,
			// F-4 du contrat #9 : Leaflet ne pose JAMAIS sa propre attribution. Elle
			// serait une seconde mention, non maîtrisée, flottant sur la toile nue.
			attributionControl: false,
			// L.SVG est IMPOSÉ : le renderer canvas ne rend pas les <pattern> d'un
			// <defs>, et le motif est la moitié de l'information.
			preferCanvas: false,
			// F-11 : le plafond de zoom de la CARTE est celui de l'emprise du
			// référentiel, jamais `fond.zoom_max`. Le douzième niveau de la pyramide
			// sert la netteté d'écran ; l'y suivre afficherait un fond sans polygones.
			maxZoom: donnees.emprise.zoom_max
		});

		// F-13 : la vue initiale est cadrée sur l'emprise du référentiel, jamais sur
		// `fond.bbox`, qui est alignée sur la grille de tuiles et en est un
		// sur-ensemble strict. Leaflet attend [[sud, ouest], [nord, est]] : la
		// conversion est ici, aucune coordonnée n'est écrite en dur. Le cadrage
		// précède toute couche — Leaflet refuse d'en ajouter une à une carte sans vue.
		bbox = donnees.emprise.bbox;
		carte.fitBounds(L.latLngBounds([[bbox.sud, bbox.ouest], [bbox.nord, bbox.est]]), { animate: false });

		// Deux panes, créés DANS CET ORDRE : à z-index égal (leaflet.css donne 400 à
		// tout .leaflet-pane) le DOM décide, et le repère reste SOUS les tracés
		// (MASTER §3.2, emplacement 5). Tuiles à 200.
		var paneRepere = carte.createPane('carte-repere');
		var paneMassifs = carte.createPane('carte-massifs');
		paneRepere.classList.add('carte__pane--repere');
		paneMassifs.classList.add('carte__pane--massifs');

		var rendererRepere = L.svg({ pane: 'carte-repere' });
		var rendererMassifs = L.svg({ pane: 'carte-massifs' });
		rendererRepere.addTo(carte);
		rendererMassifs.addTo(carte);

		svgMassifs = paneMassifs.querySelector('svg');

		// Le <defs> serveur est DÉPLACÉ dans le <svg> du renderer : la référence
		// fill: url(#…) devient intra-<svg>, aucune référence inter-fragments ne
		// peut plus se casser. Le <svg> hôte vide est retiré.
		var hoteDefs = racine.querySelector('.carte__defs');
		if (svgMassifs && hoteDefs) {
			var defs = hoteDefs.querySelector('defs');
			if (defs) { svgMassifs.insertBefore(defs, svgMassifs.firstChild); }
			hoteDefs.remove();
		}

		// Pas d'origine des motifs, RELEVÉ DANS LE DOM : aucune valeur numérique
		// de présentation n'est écrite ici (garde §11).
		if (svgMassifs) {
			chacun(svgMassifs.querySelectorAll('pattern'), function (motif) {
				var pas = parseFloat(motif.getAttribute('width'));
				if (isFinite(pas) && pas > 0) { motifs.push({ noeud: motif, pas: pas }); }
			});
		}
	} catch (erreur) {
		echecFatal();
		return;
	}

	/* ═══ 5. Fond de carte — refus structurel de toute origine tierce ═══ */

	// Clé `fond` absente, URL illisible ou origine ≠ celle de la page : AUCUNE
	// couche de tuiles, et rien d'autre à défaire — l'attribution du fond n'est
	// pas la nôtre. Elle est portée en permanence par .carte-secours__attribution
	// de la chaîne #9, que ce fichier ne touche jamais (F-3). En démasquer une
	// seconde ici la dupliquerait.
	function poserFond() {
		if (!estObjet(donnees.fond) || !estTexte(donnees.fond.url_modele)) { return; }

		var absolue;
		try {
			absolue = new URL(donnees.fond.url_modele, window.location.href);
		} catch (erreur) {
			return;
		}
		if (absolue.origin !== window.location.origin) { return; }

		// Une couche de fond qui refuse de se poser n'est PAS un échec de carte :
		// c'est l'état `fond_indisponible` du contrat #9 §3 — aucune couche, les
		// polygones se peignent sur la toile nue, et surtout aucun repli vers une
		// origine tierce. `maxZoom` porte ici la borne de la PYRAMIDE, jamais celle
		// de la carte, plafonnée à l'emprise du référentiel (F-11).
		try {
			L.tileLayer(donnees.fond.url_modele, {
				minZoom: estNombre(donnees.fond.zoom_min) ? donnees.fond.zoom_min : 0,
				maxZoom: estNombre(donnees.fond.zoom_max) ? donnees.fond.zoom_max : donnees.emprise.zoom_max,
				updateWhenIdle: true,
				keepBuffer: 1
			}).addTo(carte);
		} catch (erreur) {
			return;
		}
	}

	function demasquerAttribution(nature) {
		chacun(racine.querySelectorAll('.carte__attribution'), function (noeud) {
			if (nature === noeud.getAttribute('data-attribution')) { noeud.hidden = false; }
		});
	}

	poserFond();

	/* ═══ 6. Géométrie ═══ */

	var chemins = {};
	var traces = {};
	var ordre = [];
	var jourActif = donnees.jour_courant;
	var indexCourant = -1;
	var codeSelectionne = '';

	var contourMassifs = L.geoJSON(null, { renderer: rendererMassifs, className: 'carte__contour', interactive: false });
	var contourRepere = L.geoJSON(null, { renderer: rendererRepere, className: 'carte__contour-trace', interactive: false });

	var blocsHorsNiveau = {};
	chacun(racine.querySelectorAll('.carte__panneau-hors-niveau'), function (bloc) {
		blocsHorsNiveau[bloc.getAttribute('data-jour') + '|' + bloc.getAttribute('data-etat')] = bloc;
	});

	// `disponible === true` n'atteste que les métadonnées : cet appel traite
	// son propre échec, y compris une 404.
	fetch(donnees.geometrie.url, { credentials: 'omit' })
		.then(function (reponse) {
			if (!reponse.ok) { throw new Error('geometrie'); }
			return reponse.json();
		})
		.then(construire)
		.catch(echecFatal);

	function construire(geo) {
		if (!estObjet(geo) || !Array.isArray(geo.features)) { echecFatal(); return; }

		// className pose .carte__massif DÈS LA CRÉATION : la feuille du thème
		// peint au premier rendu, aucun flash du bleu Leaflet par défaut.
		var couche = L.geoJSON(geo, { renderer: rendererMassifs, className: 'carte__massif' });
		couche.addTo(carte);

		couche.eachLayer(function (forme) {
			var feature = forme.feature;
			if (!estObjet(feature) || !estObjet(feature.properties)) { return; }

			// Jointure UNIQUE : properties.code === massif_code, sans normalisation.
			var code = feature.properties.code;
			if (!estTexte(code) || !estObjet(donnees.massifs[code])) { return; }

			var noeud = forme.getElement();
			if (!noeud) { return; }

			chemins[code] = noeud;
			traces[code] = feature;
			noeud.setAttribute('role', 'button');
			noeud.setAttribute('tabindex', '-1');
		});

		// Ordre des flèches = `ordre` de l'îlot. Le thème ne trie jamais.
		donnees.ordre.forEach(function (code) {
			if (chemins[code]) { ordre.push(code); }
		});
		if (0 === ordre.length) { echecFatal(); return; }

		contourRepere.addTo(carte);
		contourMassifs.addTo(carte);

		peindre();
		cabler();

		indexCourant = 0;
		chemins[ordre[0]].setAttribute('tabindex', '0');

		racine.classList.add('carte--prete');
		demasquerAttribution('statuts');

		// F-2 du contrat #9 : le repli n'est retiré qu'ICI, au terme du montage —
		// couche posée, polygones peints, clavier câblé — et jamais sur un simple
		// test de présence de Leaflet. Tout chemin d'échec le laisse DEBOUT.
		//
		// SEUL `.carte-secours__repli` part. `.carte-secours__attribution` en est
		// le frère et non le descendant : elle survit au retrait, et c'est elle qui
		// porte l'attribution du fond de carte dans tous les états — jamais
		// retirée, jamais masquée, jamais dupliquée (F-3). Retrait sec, sans
		// transition ni animation (F-6).
		//
		// Cherché sur le document et non sur `racine` : le repli est le FRÈRE de
		// la racine dans <section id="carte">, jamais son descendant.
		var repli = document.querySelector('.carte-secours__repli');
		if (repli) { repli.remove(); }
	}

	/* ═══ 7. Peinture d'un jour — échange de classes, jamais de couche ═══ */

	// Changer de jour ne reprojette RIEN : échange de classes sur les 25 <path>
	// existants, zéro recréation de couche. C'est la décision de performance
	// structurante du sélecteur de date.
	function peindre() {
		ordre.forEach(function (code) {
			var noeud = chemins[code];
			var entree = entreeDe(jourActif, code);
			var classe = CLASSES_MASSIF[clePeinture(entree)];

			TOUTES_CLASSES_MASSIF.forEach(function (nom) { noeud.classList.remove(nom); });
			// estTexte, pas un test de vérité : une clé homonyme d'un membre de
			// Object.prototype ramènerait une fonction, jamais un nom de classe.
			if (estTexte(classe)) { noeud.classList.add(classe); }

			noeud.setAttribute('aria-label', etiquetteAccessible(code, entree));
		});
	}

	// Deux chaînes serveur et un tiret cadratin, jamais un mot ajouté : le
	// libellé du massif, puis le libellé officiel de l'état ou son étiquette,
	// LUE dans le panneau rendu par PHP.
	function etiquetteAccessible(code, entree) {
		var massif = donnees.massifs[code];
		var nom = estObjet(massif) && estTexte(massif.libelle) ? massif.libelle : '';
		var etat = phraseEtat(entree);

		return '' === etat ? nom : nom + ' — ' + etat;
	}

	function phraseEtat(entree) {
		if (!entree) { return ''; }
		if ('disponible' === entree.etat) {
			return estObjet(entree.niveau) && estTexte(entree.niveau.libelle) ? entree.niveau.libelle : '';
		}

		var bloc = blocsHorsNiveau[jourActif + '|' + entree.etat];
		if (!bloc) { return ''; }

		var libelle = bloc.querySelector('.statut__libelle');

		return libelle ? libelle.textContent : '';
	}

	/* ═══ 8. Panneau massif ═══ */

	var titrePanneau = panneau.querySelector('.carte__panneau-titre');
	var blocEtat = panneau.querySelector('.carte__panneau-etat');
	var blocZapef = panneau.querySelector('.carte__panneau-zapef');
	var noteZapef = panneau.querySelector('.carte__panneau-note-zapef');
	var blocFraicheur = panneau.querySelector('.carte__panneau-fraicheur');
	var boutonFermer = panneau.querySelector('.carte__panneau-fermer');

	function peindreMarque(bloc, table, cle, libelle) {
		if (!bloc) { return; }

		var marque = bloc.querySelector('.statut__marque');
		var texte = bloc.querySelector('.statut__libelle');

		if (marque) {
			Object.keys(table).forEach(function (nom) { marque.classList.remove(table[nom]); });
			if (estTexte(table[cle])) { marque.classList.add(table[cle]); }
		}
		if (texte) { texte.textContent = libelle; }
	}

	function remplirPanneau(code) {
		var entree = entreeDe(jourActif, code);
		var massif = donnees.massifs[code];
		var etat = entree && estTexte(entree.etat) ? entree.etat : '';
		var disponible = 'disponible' === etat;
		// niveau et zapef ne sont JAMAIS montrés hors de l'état `disponible`.
		var niveau = disponible && estObjet(entree.niveau) ? entree.niveau : null;
		var zapef = disponible && estObjet(entree.zapef) ? entree.zapef : null;

		if (titrePanneau) {
			titrePanneau.textContent = estObjet(massif) && estTexte(massif.libelle) ? massif.libelle : '';
		}

		if (blocEtat) { blocEtat.hidden = !niveau; }
		if (niveau) {
			peindreMarque(blocEtat, CLASSES_PASTILLE, niveau.cle, estTexte(niveau.libelle) ? niveau.libelle : '');
		}

		if (blocZapef) { blocZapef.hidden = !zapef; }
		if (noteZapef) { noteZapef.hidden = !zapef; }
		if (zapef) {
			peindreMarque(blocZapef, CLASSES_JALON, zapef.cle, estTexte(zapef.libelle) ? zapef.libelle : '');
		}

		Object.keys(blocsHorsNiveau).forEach(function (cle) {
			blocsHorsNiveau[cle].hidden = cle !== jourActif + '|' + etat;
		});

		// Fraîcheur PRÉ-FORMATÉE par le serveur : recopiée, jamais composée.
		if (blocFraicheur) {
			var fraicheur = entree && estTexte(entree.fraicheur) ? entree.fraicheur : '';
			blocFraicheur.textContent = fraicheur;
			blocFraicheur.hidden = '' === fraicheur;
		}
	}

	function selectionner(code, annoncerSelection) {
		if (!chemins[code]) { return; }

		codeSelectionne = code;
		indexCourant = ordre.indexOf(code);

		ordre.forEach(function (autre) {
			chemins[autre].classList.remove('carte__massif--courant');
			chemins[autre].setAttribute('tabindex', autre === code ? '0' : '-1');
		});
		chemins[code].classList.add('carte__massif--courant');

		// Duplication du tracé courant : contour dans le pane des massifs, contour
		// décalé dans le pane du repère (MASTER §3.2, emplacement 5). Le décalage
		// est une règle CSS sur le <g> du renderer, jamais sur le pane — Leaflet
		// y repose ses panes en transform et l'écraserait.
		contourMassifs.clearLayers();
		contourRepere.clearLayers();
		contourMassifs.addData(traces[code]);
		contourRepere.addData(traces[code]);

		remplirPanneau(code);

		if (annoncerSelection) { annoncer(chemins[code].getAttribute('aria-label')); }
	}

	function ouvrirPanneau(code) {
		selectionner(code, false);
		panneau.hidden = false;
		racine.classList.add('carte--panneau-ouvert');
	}

	// Le CONTOUR RESTE après Échap : sinon focus DOM sans indicateur (WCAG 2.4.7).
	function fermerPanneau(rendreFocus) {
		panneau.hidden = true;
		racine.classList.remove('carte--panneau-ouvert');

		if (rendreFocus && chemins[codeSelectionne]) { chemins[codeSelectionne].focus(); }
	}

	/* ═══ 9. Curseur roving et clavier ═══ */

	function deplacer(pas) {
		if (0 === ordre.length) { return; }
		allerA(indexCourant < 0 ? 0 : (indexCourant + pas + ordre.length) % ordre.length);
	}

	// La sélection SUIT le focus : rien n'est annoncé, c'est l'aria-label parlé.
	function allerA(index) {
		var code = ordre[index];
		if (!code) { return; }

		selectionner(code, false);
		chemins[code].focus();
	}

	// Zoom clavier réimplémenté, `keyboard: false` ayant retiré celui de Leaflet.
	function zoomer(pas) {
		var cible = carte.getZoom() + pas;
		if (cible < carte.getMinZoom() || cible > carte.getMaxZoom()) { return; }

		carte.setZoom(cible, { animate: false });
	}

	function cabler() {
		ordre.forEach(function (code) {
			var noeud = chemins[code];

			noeud.addEventListener('click', function () {
				ouvrirPanneau(code);
				annoncer(noeud.getAttribute('aria-label'));
			});

			noeud.addEventListener('focus', function () {
				if (code !== codeSelectionne) { selectionner(code, false); }
			});
		});

		toile.addEventListener('keydown', function (evenement) {
			var cible = evenement.target;

			// Les contrôles de zoom vivent dans la même toile : sans cette garde,
			// Entrée y ouvrirait le panneau au lieu de zoomer.
			if (!cible || !cible.classList || !cible.classList.contains('carte__massif')) { return; }

			var touche = evenement.key;

			if ('ArrowRight' === touche || 'ArrowDown' === touche) {
				deplacer(1);
			} else if ('ArrowLeft' === touche || 'ArrowUp' === touche) {
				deplacer(-1);
			} else if ('Home' === touche) {
				allerA(0);
			} else if ('End' === touche) {
				allerA(ordre.length - 1);
			} else if ('Enter' === touche || ' ' === touche || 'Spacebar' === touche) {
				if (codeSelectionne) { ouvrirPanneau(codeSelectionne); }
			} else if ('Escape' === touche) {
				fermerPanneau(false);
			} else if ('+' === touche || '=' === touche) {
				zoomer(1);
			} else if ('-' === touche || '_' === touche) {
				zoomer(-1);
			} else {
				return;
			}

			evenement.preventDefault();
		});

		panneau.addEventListener('keydown', function (evenement) {
			if ('Escape' !== evenement.key) { return; }

			evenement.preventDefault();
			fermerPanneau(true);
		});

		if (boutonFermer) {
			boutonFermer.addEventListener('click', function () { fermerPanneau(true); });
		}

		cablerJours();
		carte.on('zoomend', corrigerDensite);
	}

	/* ═══ 10. Barre de jour ═══ */

	// Le client ne bascule que vers les DEUX jours émis par le serveur, et rien
	// n'est conservé : ni stockage, ni URL, ni history — un rechargement revient
	// au jour courant.
	function changerJour(jour) {
		if (jour !== donnees.jour_courant && jour !== donnees.jour_suivant) { return; }

		jourActif = jour;

		chacun(racine.querySelectorAll('[data-jour]'), function (noeud) {
			// Les blocs hors niveau portent aussi data-etat : le panneau les pilote.
			if (noeud.hasAttribute('data-etat')) { return; }
			noeud.hidden = noeud.getAttribute('data-jour') !== jour;
		});

		chacun(racine.querySelectorAll('.carte__jour-bouton'), function (bouton) {
			bouton.setAttribute('aria-pressed', bouton.getAttribute('data-bascule') === jour ? 'true' : 'false');
		});

		peindre();
		if (codeSelectionne) { remplirPanneau(codeSelectionne); }

		chacun(racine.querySelectorAll('.carte__jour'), function (etiquette) {
			if (etiquette.getAttribute('data-jour') === jour) { annoncer(etiquette.textContent); }
		});
	}

	function cablerJours() {
		chacun(racine.querySelectorAll('.carte__jour-bouton'), function (bouton) {
			bouton.addEventListener('click', function () {
				// aria-disabled, jamais disabled : le bouton reste focusable et
				// ANNONCE la phrase sans changer de jour.
				if ('true' === bouton.getAttribute('aria-disabled')) {
					if (message) { annoncer(message.textContent); }
					return;
				}

				changerJour(bouton.getAttribute('data-bascule'));
			});
		});
	}

	/* ═══ 11. Garde auto-corrective de densité de motif ═══ */

	// patternUnits="userSpaceOnUse" est écrit par le serveur : le chemin nominal
	// de cette garde est un NO-OP. Elle existe pour survivre à une évolution de
	// Leaflet qui remettrait une échelle sur le <svg> du renderer — un motif
	// étiré redevient une information portée par la couleur seule, sans que rien
	// n'ait l'air cassé.
	function corrigerDensite() {
		if (!svgMassifs || 0 === motifs.length) { return; }

		var boite = svgMassifs.viewBox ? svgMassifs.viewBox.baseVal : null;
		var largeur = svgMassifs.width ? svgMassifs.width.baseVal.value : 0;
		if (!boite || !boite.width || !largeur) { return; }

		var rapport = boite.width / largeur;
		if (Math.abs(rapport - 1) <= 0.01) { return; }

		motifs.forEach(function (motif) {
			var pas = String(motif.pas / rapport);
			motif.noeud.setAttribute('width', pas);
			motif.noeud.setAttribute('height', pas);
		});
	}
}());
