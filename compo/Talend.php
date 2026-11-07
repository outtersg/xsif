<?php
/*
 * Copyright (c) 2026 Guillaume Outters
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT.  IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

require_once dirname(__FILE__).'/../Modele/Diagramme/Forme.php';
require_once dirname(__FILE__).'/../Modele/Diagramme/Tableau.php';

use Gui\Xsif\Modele\Diagramme\Forme;
use Gui\Xsif\Modele\Diagramme\Tableau;

/* À FAIRE: les différentes <metadata connector="…">: la sortie nominale a souvent pour nom l'identifiant du nœud, mais il existe aussi le REJECT, etc. */
/* À FAIRE: les liens conditionnés. */

class Talend
{
	/** Les bases de données sont-elles en bout de chaîne? */
	public $basesÀDroite = true;
	
	public function charger($fichier)
	{
		$contenu = file_get_contents($fichier);
		$doc = new DOMDocument();
		$doc->loadXML($contenu);
		
		$racine = $doc->documentElement;
		
		$this->_compiler($racine);
		
		return $this->_nœuds;
	}
	
	protected function _compiler($nœud)
	{
		switch($nœud->localName)
		{
			case 'context':
			case 'parameters':
				break;
			case 'ProcessType':
				$this->_fils($nœud);
				break;
			case 'node':
				if(($nœud = $this->_compilerNœud($nœud)))
					$this->_nœuds[] = $nœud;
				break;
			case 'connection':
				$this->_compilerLien($nœud);
				break;
			case '': break; // Texte?
			default:
				fprintf(STDERR, "# Je ne sais pas ingérer un nœud de type %s\n", $nœud->localName);
		}
	}
	
	protected function _fils($nœud)
	{
		$r = [];
		if(isset($nœud->childNodes))
			foreach($nœud->childNodes as $fils)
			{
				if(($fils = $this->_compiler($fils)))
					$r[] = $fils;
			}
		return $r;
	}
	
	protected function _compilerNœud($nœud)
	{
		if(!($type = $nœud->getAttributeNS(null, 'componentName'))) { fprintf(STDERR, "# <node> sans componentName.\n"); return; }
		if(!method_exists($this, $fonc = '_compilerNœud_'.$type)) { fprintf(STDERR, "# Je ne sais pas gérer les nœuds %s.\n", $type); return; }
		
		$infos = $this->_infosNœud($nœud);
		$infos['type'] = $type;
		
		return $this->$fonc($nœud, $infos);
	}
	
	protected function _infosNœud($nœud)
	{
		$r = [];
		
		$corresp =
		[
			'TEXT' =>
			[
				/* Génériques */
				'UNIQUE_NAME' => 'id',
				'LABEL' => 'nom',
				/* Correspondances */
				'LOOP_QUERY' => 'exprItér',
				/* Bases */
				'HOST',
				'DBNAME',
			],
			'MEMO_SQL' =>
			[
				/* À FAIRE: ça ça part en 'code', pour uniformiser. */
				'QUERY',
			],
			'MEMO_JAVA' =>
			[
				'CODE' => 'code',
			],
			'MEMO_IMPORT' =>
			[
				'IMPORT' => 'inc',
			],
			'CHECK' =>
			[
				'DEFAULT_MAP' => 'GlobalMap',
			],
			'FILE' =>
			[
				'FILENAME',
			],
			'COMPONENT_LIST' =>
			[
				/* Ressources liées */
				'CONNECTION',
			],
		];
		/** À FAIRE: coller cela dans un static. Inutile de recalculer notre table de correspondances à chaque fois. */
		foreach($corresp as $caté => $trucs)
			foreach($trucs as $clé => $val)
				if(is_int($clé))
					$corresp[$caté][$val] = $val;
		
		$tableaux = [];
		
		foreach($nœud->childNodes as $fils)
			if($fils->localName == 'elementParameter')
			{
				// Les tableaux: Talend pousse successivement toutes les cases, à nous de comprendre qu'on passe à la ligne suivante quand on revient au même nom de champ.
				if($fils->getAttributeNS(null, 'field') == 'TABLE')
				{
					$tableaux[$nomT = $fils->getAttributeNS(null, 'name')] = [];
					$t = [];
					foreach($fils->childNodes as $petitFils)
					{
						if(!method_exists($petitFils, 'getAttributeNS')) continue; // Un peu de blanc?
						$col = $petitFils->getAttributeNS(null, 'elementRef');
						if(array_key_exists($col, $t))
						{
							$tableaux[$nomT][] = $t;
							$t = [];
						}
						$t[$col] = $petitFils->getAttributeNS(null, 'value');
					}
					if(count($t))
						$tableaux[$nomT][] = $t;
					continue;
				}
				
				// Les champs texte: on veut une correspondance mentionnée dans $corresp.
				if(isset($corresp[$type = $fils->getAttributeNS(null, 'field')]) && isset($corresp[$type][$champ = $fils->getAttributeNS(null, 'name')]))
					$r[$corresp[$type][$champ]] = $fils->getAttributeNS(null, 'value');
			}
			/* Les sorties exposées.
			 * On peut avoir plusieurs FLOW (ex.: out6 et copyOfout1_0).
			 * Couples rencontrés: FLOW+REJECT, FLOW+RESPONSE+FAULT, FLOW+FLOW
			 * Dans FLOW+RESPONSE, RESPONSE, c'était statusCode + les champs de FLOW. Redondant?
			 * Dans FLOW+REJECT, REJECT, c'était les champs de FLOW + errorCode + errorMessage [+ errorXMLField].
			 * FAULT c'est errorCode + errorMessage.
			 * Investigation:
			 *   egrep '<node |<metadata connector.*">' ….item | awk '/<node /{n=0;accu=$0;next}/<metadata/{++n}n==2{print accu}n>=2{print;next}{accu=accu"\n"$0}'
			 */
			else if($fils->localName == 'metadata' && ($typeSortie = $fils->getAttribute('connector')))
			{
				switch($typeSortie)
				{
					case 'FLOW': $typeSortie = '>'; break;
					case 'RESPONSE': $typeSortie = '>'; break;
					case 'REJECT': $typeSortie = '!'; break;
					case 'FAULT': $typeSortie = '!'; break;
					default: fprintf(STDERR, "# Je ne sais pas gérer les <metadata connector=\"%s\">\n", $typeSortie); continue 2;
				}
				
				$champsSortie = [];
				foreach($fils->childNodes as $petitFils)
				{
					if($petitFils->localName != 'column') continue;
					$col = $petitFils->getAttribute('name');
					$champsSortie[] = $col;
				}
				// Et une copie indicée [0] pour s'y retrouver:
				$r[$typeSortie.'n'][] = $r[$typeSortie][$nom = $fils->getAttribute('name')] = $champsSortie;
			}
		
		if(!isset($r['nom']))
			$r['nom'] = $r['id'];
		else
			$r['nom'] .= ' ('.$r['id'].')';
		
		$r += $tableaux;
		
		/* À FAIRE: posX et posY pour une idée de la position relative des éléments (à reproduire en sortie si on n'a pas d'idée). */
		
		return $r;
	}
	
	/**
	 * Convertit une chaîne Java (éléments fixes entre guillemets, éléments variables tels quels) en chaîne shell (éléments fixes tels quels, éléments variables entre ${…}).
	 */
	public function _déchaîne($chaîne)
	{
		// Les entiers bruts sortent tels quels:
		if(is_numeric($chaîne)) return $chaîne;
		if(strpos($chaîne, '"') === false) return '${'.$chaîne.'}';
		if(preg_match('/^"[^"]*"$/', $chaîne)) return trim($chaîne, '"');
		
		preg_match_all('/["()+]|\\\\./', $chaîne, $r, PREG_OFFSET_CAPTURE);
		$retour = '';
		$pile = [];
		$courant = null;
		$dollar = false;
		$pos = 0;
		foreach($r[0] as $p)
		{
			// Si un caractère précédent (ex.: \) a déjà traité le caractère actuel, on passe.
			if($p[1] < $pos) continue;
			switch(substr($p[0], 0, 1))
			{
				case '"':
					if($courant == '"')
					{
						$courant = array_pop($pile);
						if(!count($pile) && !isset($courant))
						{
							$retour .= substr($chaîne, $pos, $p[1] - $pos);
							$pos = $p[1] + 1;
							if(preg_match('/^\s*[+]\s*/', substr($chaîne, $pos), /*&*/ $rex)) $pos += strlen($rex[0]);
							continue 2;
						}
					}
					else
					{
						if(isset($courant)) $pile[] = $courant;
						$courant = '"';
						if(!count($pile))
						{
							if($p[1] > $pos && substr($chaîne, $p[1] - 1, 1) == '+')
								$retour .= ($dollar ? '' : '${').substr($chaîne, $pos, $p[1] - 1 - $pos).'}';
							$dollar = false;
							$pos = $p[1] + 1;
							continue 2;
						}
					}
					break;
				case '\\':
					if($courant == '"' && !count($pile))
					{
						$retour .= substr($chaîne, $pos, $p[1] - $pos).substr($p[0], 1);
						$pos = $p[1] + strlen($p[0]);
					}
					break;
				case '(':
					if($courant == '"') break;
					if(isset($courant)) $pile[] = $courant;
					$courant = '(';
					break;
				case ')':
					if($courant == '"') break;
					if($courant == '(')
						$courant = array_pop($pile);
					break;
				case '+':
					if(!count($pile) && !isset($courant) && preg_match('/^\s*"/', substr($chaîne, $p[1] + 1)))
					{
						$retour .= ($dollar ? '' : '${').rtrim(substr($chaîne, $pos, $p[1] - $pos)).'}';
						$pos = $p[1] + 1;
						continue 2;
					}
					break;
			}
			$pos0 = $pos;
			$pos = $p[1] + strlen($p[0]);
			// Si on est dans une section variabilisée dont on n'a pas encore sorti la balise de démarrage;
			// sachant qu'on n'est pas en variabilisé si on est dans un guillemet de premier niveau.
			if(!$dollar && ($courant != '"' || count($pile))) { $retour .= '${'; $dollar = true; }
			$retour .= substr($chaîne, $pos0, $pos - $pos0);
		}
		if($dollar)
			$retour .= '}';
		/* À FAIRE: là on doit avoir une pile vide, et $courant === null. */
		/* À FAIRE?: simplifier certaines formules Talend, par ex.: ${((<type>)globalMap.get("raymond.carré")} en ${GM.raymond.carré}
		 * Ou alors le proposer via un fonction passée en paramètre: comme ça l'appelant peut fournir sa version spécialisée, capable d'en profiter pour créer un lien depuis un nœud virtuel GlobalMap. */
		return $retour;
	}
	
	public function testDéchaîne()
	{
		foreach
		(
			[
				'"fixe."+globalMap.get("entre \"guillemets\"")+" et \"guillemets\""+" etc."' => 'fixe.${globalMap.get("entre \"guillemets\"")} et "guillemets" etc.',
				'globalMap.get("entre \"guillemets\"")+" et \"guillemets\""+" etc."' => '${globalMap.get("entre \"guillemets\"")} et "guillemets" etc.',
			]
			as $avant => $attendu
		)
			if(($après = $this->_déchaîne($avant)) != $attendu)
				echo "# Résultat inattendu:\n    $avant\n  - $attendu\n  + $après\n";
	}
	
	/**
	 * Tri stable d'une liste de chemins, pour éviter de rentrer plusieurs fois dans la même branche.
	 */
	public function regrouperChemins($chemins)
	{
		/* Tri stable: si on a:
		 *   b
		 *   b/b
		 *   b/b/c
		 *   a
		 *   b     # ← Là une répétition de b pour pouvoir rerentrer dans la branche b en vue de la ligne suivante.
		 *   b/a
		 * le b/a ira se ranger après les b/b, histoire de perturber le moins possible l'ordre initial.
		 */
		
		$inconnu = 0;
		
		$racine = [];
		foreach($chemins as $numChemin => $c)
		{
			$c = explode('/', $c);
			$ptrT = & $racine;
			foreach($c as $numBout => $bout)
			{
				// Pas encore passés par là? On crée la branche.
				if(!isset($ptrT[$bout]))
				{
					$ptrT[$bout] =
					[
						// Pour l'identifiant: les feuilles gardent l'indice qu'elles avaient dans le tableau de départ (des éléments pourraient s'y être liés par indice),
						// mais si on doit créer le nœud de zéro, on lui donne une clé négative pour espérer ne pas empiéter.
						$numBout == count($c) - 1 ? $numChemin : --$inconnu,
						implode('/', array_slice($c, 0, $numBout + 1)),
						[]
					];
				}
				$ptrT = & $ptrT[$bout][2];
			}
		}
		
		$retour = [];
		$this->_regrouperChemins_dérouler($racine, /*&*/ $retour);
		
		return $retour;
	}
	
	protected function _regrouperChemins_dérouler($enfants, & $dest)
	{
		foreach($enfants as $enfant)
		{
			$dest[$enfant[0]] = $enfant[1];
			$this->_regrouperChemins_dérouler($enfant[2], /*&*/ $dest);
		}
	}
	
	/**
	 * Transforme une liste:
	 *   a
	 *   a/b
	 *   a/b/c
	 *   a/b/d
	 *   a/e
	 * en:
	 *   a
	 *     b
	 *       c
	 *       d
	 *     e
	 */
	public function cheminsEnArbo($chemins)
	{
		$r = [];
		
		/* À FAIRE: pouvoir créer les intermédiaires (cf. $intermédiaires dans _compilerNœud_tExtractXMLField) tout en préservant les clés pour ne pas perdre les assoc. */
		
		$pile = [];
		foreach($chemins as $clé => $chemin)
		{
			while(count($pile) && substr($chemin, 0, strlen($pile[0])) != $pile[0])
				array_shift($pile);
			$r[$clé] = array_fill(0, count($pile), null);
			$r[$clé][] = count($pile) ? ltrim(substr($chemin, strlen($pile[0])), '/') : $chemin;
			array_unshift($pile, $chemin);
		}
		
		return $r;
	}
	
	protected function _blocTexte($nœud, $infos, $texte)
	{
		/* À FAIRE: permettre de choisir soit un mode tableau (une ligne par ligne), soit un bloc texte (juste une zone de texte: copiable-collable). */
		/* À FAIRE: savoir caler précisément la hauteur de ligne, pour savoir, dans un bloc texte, où arrive le connecteur pour chaque ligne.
		 * Alors on n'aura plus besoin du mode tableau. */
		
		$o = new Tableau($infos['nom'], explode("\n", $texte));
		$o->style = 'texte';
		$this->_nœuds[$infos['id']] = $o;
	}
	
	/*- Nœuds de mise en correspondance --------------------------------------*/
	
	protected function _compilerNœud_tExtractXMLField($nœud, $r)
	{
		return $this->_compilerNœud_XMLVersChamps($nœud, $r);
	}
	
	protected function _compilerNœud_tFileInputXML($nœud, $r) { return $this->_compilerNœud_XMLVersChamps($nœud, $r); }
	
	protected function _compilerNœud_XMLVersChamps($nœud, $r)
	{
		/* À FAIRE: mode alternatif tableau deux colonnes (plutôt que deux tableaux). */
		
		/* $arbo: pour avoir une représentation façon arbo XML; par ex., au lieu de:
		 *   id                → id
		 *   truc/bidule/chose → chose
		 *   truc/machin       → machin
		 * présenter en:
		 *   id                → id
		 *   truc
		 *     bidule
		 *       chose         → chose
		 *     machin          → machin
		 * /!\ bidule peut revenir, si un autre attribut provient de lui mais que dans la sortie on a voulu le placer sous machin.
		 */
		$arbo = 1;
		
		/* À FAIRE: LOOP_QUERY_BASE != "" */
		/* À FAIRE: XML_TEXT != "" */
		/* À FAIRE: XML_PREFIX != "" */
		/* À FAIRE: FLOW, REJECT, CHECK */
		
		// Si intermédiaires, on ne passe pas directement de truc/a à truc/b/c/d, on crée les intermédiaires truc/b, truc/b/c, pour mener tout doucement à truc/b/c/d.
		$intermédiaires = $arbo; // La représentation des intermédiaires est nécessaire pour passer par $this->cheminsEnArbo().
		$tri = 1; // Si pas tri, et que le MachinPath va taper un coup à droite, un coup à gauche, ça devient compliqué de s'y retrouver.
		
		$tTransfo = [];
		$tRés = [];
		$tr = [];
		
		$numT = $numR = -1;;
		$préc = [];
		foreach($r['MAPPING'] as $l)
		{
			$chem = $this->_déchaîne($l['QUERY']);
			if($intermédiaires)
			{
				$chem = explode('/', $chem);
				// On crée toutes les lignes intermédiaires représentant les nœuds parents du nôtre, s'ils ne sont pas communs avec notre prédécesseur.
				for($d = 0; isset($préc[$d]) && isset($chem[$d]) && $préc[$d] === $chem[$d]; ++$d) {}
				--$d;
				while(isset($chem[++$d]))
					$tTransfo[++$numT] = implode('/', array_slice($chem, 0, $d + 1));
			}
			else
				$tTransfo[++$numT] = $chem;
			$tRés[++$numR] = $l['SCHEMA_COLUMN'];
			$tr[$numR] = $numT;
			$préc = $chem;
		}
		
		if($tri)
			$tTransfo = $this->regrouperChemins($tTransfo);
			/* À FAIRE: trier $tRés en conséquence pour éviter les croisements de liens. */
		
		$titre = 'XMLPath '.$r['nom'];
		if(isset($r['FILENAME'])) $titre .= "\n".$this->_déchaîne($r['FILENAME']);
		$titre .= "\n".$this->_déchaîne($r['exprItér']);
		$oTransfo = new Tableau($titre, $tTransfo);
		$oRés = new Tableau($tRés);
		$oRés->style = 'd';
		$oRés->prédécesseurs[] = $oTransfo;
		foreach($tRés as $clé => $trou)
			$oRés->depuis[] = [ $oTransfo, $tr[$clé], $clé, 'attr' ];
		
		if($arbo)
			$oTransfo->d = $this->cheminsEnArbo($oTransfo->d);
		
		$this->_nœuds[$r['id']] = $oTransfo;
		$this->_nœuds[$r['id'].'.d'] = $oRés;
	}
	
	protected function _compilerNœud_tWriteJSONField($nœud, $r)
	{
		/* À FAIRE: exploiter les ORDER pour savoir dans quel ordre afficher les lignes. */
		/* À FAIRE: à quoi sert la LOOP? Est-ce un attribut clé primaire? */
		
		/* À FAIRE?: valider la cohérence de l'arbo? Pour le moment on fait confiance au producteur (Talend) pour fournir les bons ORDER qui classent les sous-éléments JSON sous leur contenant. */
		$oSrc = new Tableau('→ JSON '.$this->_déchaîne($r['nom']));
		$oDst = new Tableau();
		$oDst->prédécesseurs = [ $oSrc ];
		$oDst->style = 'd';
		$tout = array_merge($r['LOOP'], $r['ROOT']);
		foreach($tout as $posSource => $l)
		{
			$oDst->d[$l['ORDER']] = $l['PATH'];
			if(isset($l['COLUMN']) && $l['COLUMN'])
			{
				$oSrc->d[$posSource] = $l['COLUMN'];
				$oDst->depuis[] = [ $oSrc, $posSource, $l['ORDER'], 'attr' ];
			}
		}
		
		ksort($oDst->d);
		
		// Création de l'arborescence.
		$oDst->d = $this->cheminsEnArbo($oDst->d);
		
		$this->_nœuds[$r['id']] = $oSrc;
		$this->_nœuds[$r['id'].'.d'] = $oDst;
		
		/* À FAIRE: nom du tableau données à piocher dans la <connection> qui part du $nœud. */
	}
	
	protected function _compilerNœud_tMap($nœud, $r)
	{
		$tables = [];
		$champs = [];
		foreach($nœud->childNodes as $fils)
			if($fils->localName == 'nodeData')
				foreach($fils->childNodes as $petitFils)
				{
					if(!$petitFils instanceof DOMElement) continue;
					switch($petitFils->localName)
					{
						case 'varTables':
							// Une varTables, c'est un truc qui est à la fois input et output. Mais la plupart du temps la balise existe, vide, et on ne veut pas pondre un nœud pour ça.
							if($petitFils->hasChildNodes())
								break;
							else
								continue 2;
						case 'uiProperties':
							continue 2;
						case 'inputTables':
						case 'outputTables':
							// On traite de la même manière: les input peuvent elles-mêmes dépendre d'autres input.
							break;
						default:
							fprintf(STDERR, "# tMap: je ne sais pas gérer les sous-nœuds %s\n", $petitFils->localName);
							continue 2;
					}
					// Les tables de correspondances sont établies maintenant.
					$par = [];
					$vers = [];
					$filtrant = false;
					$expressif = false;
					$oVers = new Tableau($nom = $petitFils->getAttribute('name'));
					$oVers->style = 'd';
					foreach($petitFils->childNodes as $ppf)
					{
						if(!$ppf instanceof DOMElement) continue;
						$vers[] = $champ = $ppf->getAttribute('name');
						$champs[$nom.'.'.$champ] = [ $oVers, count($vers) - 1 ];
						$expr = trim($ppf->getAttribute('expression'));
						if(isset($expr) && strlen($expr))
						{
							if(($compar = $ppf->getAttribute('operator')))
							{
								$filtrant = true;
								/* À FAIRE: et si l'operator est différent de = écrit-on dans le bon sens? */
								/* NOTE: Symboles pour exprimer le filtre
								 * L'entonnoir n'existe pas https://stackoverflow.com/questions/37991395/unicode-character-for-funnel-to-signify-filtering
								 * Mais parmi les symboles mathématiques (https://en.wikipedia.org/wiki/Mathematical_operators_and_symbols_in_Unicode) certains sont jolis:
								 * ≣ strictement équivalent à
								 * ⊨ "satisfait", "force"
								 * ⊦ assertion
								 * ⋺ contient avec long trait horizontal
								 * ⊳ contient comme sous-groupe normal
								 */
								$expr = '⊳'.' '.$compar.' '.$expr;
							}
							else
							$expressif = true;
						}
						$par[] = $expr;
					}
					$oVers->d = $vers;
					if($filtrant && $expressif)
						fprintf(STDERR, "# Nœud à la fois à formule et à filtre. Résultats non garantis.\n");
					if($filtrant)
					{
						foreach($oVers->d as $numLigne => $ligne)
							$oVers->d[$numLigne] = [ $par[$numLigne], $ligne];
					}
					if($expressif)
					{
						$oPar = new Tableau($par);
						$oVers->prédécesseurs[] = $oPar;
						foreach($oVers->d as $num => $champ)
							$oVers->depuis[] = [ $oPar, $num, $num, 'attr' ];
						$tables[$r['id'].'.'.$nom] = $oPar;
					}
					$tables[$r['id'].'.'.$nom.'.d'] = $oVers;
					
				}
		$tousChamps = implode('|', array_keys($champs));
		$exprTousChamps = '/\b('.$tousChamps.')\b/';
		foreach($tables as $idTable => $table)
			if(substr($idTable, -2) != '.d')
			{
				$prédés = [];
				foreach($table->d as $num => $formule)
				{
					$réfs = [];
					preg_match_all($exprTousChamps, $formule, $r);
					foreach($r[0] as $réf)
						$réfs[$réf] = $réf; // Par indice pour ne garder qu'une seule entrée si la même source est citée plusieurs fois dans la formule.
					foreach($réfs as $réf)
					{
						$source = $champs[$réf];
						$table->depuis[] = [ $source[0], $source[1], $num, $formule == $réf ? 'attr' : 'compo' ]; // 'attr' si cible = source, 'compo' si cible = source + d'autres choses.
						$prédés[$source[0]->titre] = $source[0];
					}
				}
				foreach($prédés as $prédé)
					$table->prédécesseurs[] = $prédé;
			}
		
		// [] + [] manuel, pour garder l'ordre de création d'origine (permet de représenter les blocs dans l'ordre si aucune dépendance entre eux), tout en écrasant par la dernière valeur si l'ID est déjà utilisé.
		// Bon on pourrait aussi faire du array_diff_key() + $table, ou se dire qu'il n'y a pas de raison que Talend nous fournisse deux blocs de même ID, mais…
		foreach($tables as $id => $table)
			$this->_nœuds[$id] = $table;
	}
	
	/*- Nœuds de contexte ----------------------------------------------------*/
	
	protected function _compilerNœud_tFlowToIterate($nœud, $r)
	{
		if(!isset($r['GlobalMap']) || $r['GlobalMap'] !== 'true')
		{
			fprintf(STDERR, "# Je ne sais pas ingérer un tFlowToIterate sans DEFAULT_MAP.\n");
			return;
		}
		
		$o = new Forme('GM');
		$o->forme = 'nuage';
		$o->style = 'global';
		$o->notifPrédécesseur = [ $this, '_notif_tFlowToIterate' ];
		
		$this->_nœuds[$r['id']] = $o;
	}
	
	protected function _notif_tFlowToIterate($oDe, $oVers)
	{
		if(isset($oDe->titre))
			$oVers->depuis[] = [ $oDe, null, null, null, $oDe->titre.'.*' ];
	}
	
	protected function _compilerNœud_tSetGlobalVar($nœud, $infos)
	{
		$o = new Forme('GM');
		$o->forme = 'nuage';
		$o->style = 'global';
		
		$champs = [];
		$formus = [];
		// On adopte la typologie "Tableau formus → Tableau données" commune à pas mal de traitements:
		// ça nous rendra éligibles à la simplification de la partie formu si tous les champs sont calculés comme ${entrée.champ}.
		/* À FAIRE: phase simplification. */
		/* À FAIRE: simplification partielle: si tous les champs sont simplifiables sauf 1 (ex.: valeur fixe), ne garder du tableau formu que la valeur fixe injectée vers le champ, pour tous les autres champs se brancher directement au champ source correspondant. */
		foreach($infos['VARIABLES'] as $v)
		{
			$champs[] = $this->_déchaîne($v['KEY']);
			$formus[] = $this->_déchaîne($v['VALUE']);
		}
		$oc = new Tableau($champs);
		$oc->style = 'd';
		$o->prédécesseurs[] = $oc;
		/* À FAIRE: lier en bleu */
		$of = new Tableau($infos['nom'], $formus);
		$oc->prédécesseurs[] = $of;
		foreach($oc->d as $num => $champ)
			$oc->depuis[] = [ $of, $num, $num, 'attr' ];
		
		$this->_nœuds[$infos['id'].'.a'] = $of;
		$this->_nœuds[$infos['id']] = $oc;
		$this->_nœuds[$infos['id'].'.d'] = $o; // Alors en fait, dans ce sens (données vers nuage), d ça ne veut plus dire données, ça veut dire droite. Parce que la sortie de la constellation données → nuage est à droite, dans le nuage.
		
		/* À FAIRE: sur notifPrédécesseur(), simplifier si on est une liste de champs sur le même modèle ${rowX.champY}; on peut même faire sauter la colonne si toutes ses cases sont ainsi simplifiées. */
	}
	
	/*- Nœuds de persistance -------------------------------------------------*/
	
	protected function _compilerNœud_tSQLConnection($nœud, $infos)
	{
		/* À FAIRE?: valider que les tPostgresqlInput etc. qui s'y branchent utilisent les mêmes paramètres de connexion */
		$o = isset($this->_nœuds[$infos['id']]) ? $this->_nœuds[$infos['id']] : new Forme();
		$o->titre = $infos['nom']."\n".$this->_déchaîne($infos['HOST']).'/'.$this->_déchaîne($infos['DBNAME']);
		$o->forme = 'bdd';
		$this->_nœuds[$infos['id']] = $o;
	}
	
	protected function _compilerNœud_tPostgresqlConnection($nœud, $infos) { return $this->_compilerNœud_tSQLConnection($nœud, $infos); }
	protected function _compilerNœud_tOracleConnection($nœud, $infos) { return $this->_compilerNœud_tSQLConnection($nœud, $infos); }
	
	protected function _compilerNœud_tSQLInput($nœud, $infos)
	{
		/* Tableau de style texte plutôt que zone de texte: ça permet de faire arriver des liens en face de chaque ligne, pour indiquer où interviennent les tables. */
		/* À FAIRE: si on reconnaît des tables ou assimilées (vues, séquences), les matérialiser comme intermédiaires entre la base et nous. */
		$o = new Tableau($infos['nom'], explode("\n", $sql = trim($this->_déchaîne($infos['QUERY']))));
		$o->forme = 'bdd';
		$o->style = 'texte';
		$this->_nœuds[$infos['id']] = $o;
		
		$os = new Tableau($infos['>n'][0]);
		$os->style = 'd';
		$os->prédécesseurs[] = $o;
		$this->_nœuds[$infos['id'].'.d'] = $os;
		
		/* À FAIRE: gérer DBTABLE.TABLE, qui contient aussi le nom de la table. */
		$this->_lierBdd($o, $infos['CONNECTION'], $sql);
	}
	
	protected function _lierBdd($o, $idBdd, $tables)
	{
		/* Extraction des tables. */
		
		if(is_string($tables))
		{
			preg_match_all('/\bfrom\s+(\S+)/i', $tables, $tables, PREG_OFFSET_CAPTURE);
			/* À FAIRE: nextval(…) */
			/* À FAIRE: pas les from (select …) as bla */
			$tables = $tables[1];
			/* À FAIRE: traduire la position en numéro de ligne */
		}
		
		if(!isset($this->_nœuds[$idBdd]))
		{
			$ob = new Forme();
			$ob->forme = 'bdd';
			$this->_nœuds[$idBdd] = $ob;
		}
		$ob = $this->_nœuds[$idBdd];
		
		/* Représente-t-on des objets table intermédiaires? */
		
		if(is_array($tables))
		{
			$oTables = [];
			foreach($tables as $t)
			{
				if(is_array($t))
				{
					$nomTable = $t[0];
					/* À FAIRE: gérer la ligne */
				}
				else
				{
					$nomTable = $t;
				}
				/* À FAIRE: conserver le schéma pour information, non seulement en dessous (visuellement), mais aussi en interne pour éviter les fusions à tort:
				 * on le simplifie à l'affichage pour lisibilité, et pour pouvoir fusionner ma_table et schéma.ma_table, mais si d'aventure un de nos traitements déversait schéma1.ma_table vers schéma2.ma_table, il faudrait qu'on puisse instancier un objet table distinct (et signaler toutes les ambiguïtés passées).
				 * En ce cas l'instance initiale pourrait s'appeler $idBdd.'.*.'.$idTable, et une fois l'ambiguïté créée, le schéma serait explicitement donné.
				 * Ou alors n'effectuer la passe de fusion qu'une fois tout le modèle récupéré: plus facile pour être sûrs de ne pas avoir d'ambiguïtés, moins facile pour fusionner une fois chacun des nœuds inséré avec ses relations éparpillées dans le graphe.
				 */
				$nomTable = trim(preg_replace('/^([^.]+\.)+/', '', $nomTable), '"');
				$idTable = $idBdd.'.'.strtolower($nomTable);
				if(isset($this->_nœuds[$idTable]))
					$ot = $this->_nœuds[$idTable];
				else
				{
					$ot = $this->_nœuds[$idTable] = new Forme($nomTable);
					$ot->forme = 'table';
					if($this->basesÀDroite)
						$ob->prédécesseurs[] = $ot;
					else
						$ot->prédécesseurs[] = $ob;
					/* À FAIRE: styler le lien table - base comme relation de contenu à conteneur. */
				}
				$oTables[] = $ot;
			}
		}
		else
			$oTables = $ob;
		
		foreach($oTables as $ob)
		{
		if($this->basesÀDroite)
		{
			$ob->prédécesseurs[] = $o;
			$ob->depuis[] = [ $o, null, null, false ];
		}
		else
			$o->prédécesseurs[] = $ob;
		
		$o->depuis[] = [ $ob, null, null, 'd' ];
		}
	}
	
	protected function _compilerNœud_tPostgresqlInput($nœud, $infos) { return $this->_compilerNœud_tSQLInput($nœud, $infos); }
	protected function _compilerNœud_tOracleInput($nœud, $infos) { return $this->_compilerNœud_tSQLInput($nœud, $infos); }
	
	/*- Code -----------------------------------------------------------------*/
	
	/* N.B.: les tPostgresqlInput et autres SQL pourraient venir ici aussi. */
	
	protected function _compilerNœud_tJavaRow($nœud, $infos)
	{
		$code = $infos['inc'];
		$code = trim(preg_replace('#^\s*//.*#m', '', $code)); // Les // import java.util; laissés traîner par défaut ne nous intéressent pas.
		$code = $code ? [ $code ] : [];
		if(isset($infos['code']))
			$code[] = trim($infos['code']);
		$code = implode("\n", $code);
		$this->_blocTexte($nœud, $infos, $code);
	}
	
	protected function _compilerNœud_tJava($nœud, $infos) { return $this->_compilerNœud_tJavaRow($nœud, $infos); }
	
	/*- Nœuds informatifs ----------------------------------------------------*/
	/* Quelques nœuds dont on n'a rien à faire d'un point de vue fonctionnel, mais que l'on parcourt tout de même pour récupérer leurs identifiants (sur lesquels se branchent leurs successeurs) et éviter de casser la chaîne. */
	
	protected function _compilerNœud_tLogRow($nœud, $infos)
	{
		$o = new Forme($infos['nom']);
		$o->forme = '';
		$this->_nœuds[$infos['id']] = $o;
	}
	
	/*- Les liens ------------------------------------------------------------*/
	
	protected function _compilerLien($lien)
	{
		if(!($type = $lien->getAttributeNS(null, 'connectorName'))) { fprintf(STDERR, "# <connection> sans connectorName.\n"); return; }
		
		$infos = $this->_infosNœud($lien);
		$infos['type'] = $type;
		
		switch($type)
		{
			case 'FLOW':
				break;
			default:
				fprintf(STDERR, "# Je ne sais pas gérer les nœuds %s.\n", $type);
				return;
		}
		
		$de = $lien->getAttributeNS(null, 'source');
		$vers = $lien->getAttributeNS(null, 'target');
		
		if(!isset($this->_nœuds[$de])) { fprintf(STDERR, "# Lien depuis le nœud inconnu %s\n", $de); return; }
		$oDe = $this->_nœuds[$de.(isset($this->_nœuds[$de.'.d']) ? '.d' : '')];
		// Si le nœud précédent est un nœud données, il représente ce qui circule dans cette jonction: on lui en donne le titre.
		if(!isset($oDe->titre) && isset($infos['nom']))
			$oDe->titre = $infos['nom'];
		if(!isset($this->_nœuds[$vers])) { fprintf(STDERR, "# Lien depuis le nœud inconnu %s\n", $vers); return; }
		$oVers = $this->_nœuds[$vers.(isset($this->_nœuds[$vers.'.a']) ? '.a' : '')];
		
		$oVers->prédécesseurs[] = $oDe;
		if(isset($oVers->notifPrédécesseur))
			call_user_func($oVers->notifPrédécesseur, $oDe, $oVers);
	}
	
	protected $_nœuds;
}

?>
