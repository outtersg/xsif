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
			],
			'CHECK' =>
			[
				'DEFAULT_MAP' => 'GlobalMap',
			],
		];
		
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
		
		if(!isset($r['nom']))
			$r['nom'] = $r['id'];
		else
			$r['nom'] .= ' ('.$r['id'].')';
		
		$r += $tableaux;
		
		/* À FAIRE: posX et posY pour une idée de la position relative des éléments (à reproduire en sortie si on n'a pas d'idée). */
		
		return $r;
	}
	
	protected function _déchaîne($chaîne)
	{
		/* À FAIRE: \" */
		$chaîne = preg_replace_callback('/"[^"]*"\+?|[^"]+\+?/', function($x) { $x = $x[0]; return substr($x, 0, 1) == '"' ? substr($x, 1, -1) : '${'.$x.'}'; }, $chaîne);
		return $chaîne;
	}
	
	protected function _compilerNœud_tExtractXMLField($nœud, $r)
	{
		/* À FAIRE: mode alternatif tableau deux colonnes (plutôt que deux tableaux). */
		/* À FAIRE: LOOP_QUERY_BASE != "" */
		/* À FAIRE: XML_TEXT != "" */
		/* À FAIRE: XML_PREFIX != "" */
		/* À FAIRE: FLOW, REJECT, CHECK */
		
		$tTransfo = [];
		$tRés = [];
		
		foreach($r['MAPPING'] as $l)
		{
			$tTransfo[] = $this->_déchaîne($l['QUERY']);
			$tRés[] = $l['SCHEMA_COLUMN'];
		}
		
		$oTransfo = new Tableau('XMLPath '.$r['nom']."\n".$this->_déchaîne($r['exprItér']), $tTransfo);
		/* À FAIRE: le nom du tableau "donnée" se trouve dans le <connection> qui sort de ce nœud. */
		$oRés = new Tableau($tRés);
		$oRés->style = 'd';
		$oRés->prédécesseurs[] = $oTransfo;
		foreach($tTransfo as $clé => $trou)
			$oRés->depuis[] = [ $oTransfo, $clé, $clé, 'attr' ];
		
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
		$pile = [];
		foreach($oDst->d as $clé => $chemin)
		{
			while(count($pile) && substr($chemin, 0, strlen($pile[0])) != $pile[0])
				array_shift($pile);
			$oDst->d[$clé] = array_fill(0, count($pile), null);
			$oDst->d[$clé][] = count($pile) ? ltrim(substr($chemin, strlen($pile[0])), '/') : $chemin;
			array_unshift($pile, $chemin);
		}
		
		$this->_nœuds[$r['id']] = $oSrc;
		$this->_nœuds[$r['id'].'.d'] = $oDst;
		
		/* À FAIRE: nom du tableau données à piocher dans la <connection> qui part du $nœud. */
	}
	
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
		
		$this->_nœuds[$r['id']] = $o;
	}
	
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
		$oDe = $this->_nœuds[$de];
		if(!isset($this->_nœuds[$vers])) { fprintf(STDERR, "# Lien depuis le nœud inconnu %s\n", $vers); return; }
		$oVers = $this->_nœuds[$vers];
		
		$oVers->prédécesseurs[] = $oDe;
	}
	
	protected $_nœuds;
}

?>
