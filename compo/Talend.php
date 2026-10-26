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

require_once dirname(__FILE__).'/../Modele/Diagramme/Tableau.php';

use Gui\Xsif\Modele\Diagramme\Tableau;

class Talend
{
	public function charger($fichier)
	{
		$contenu = file_get_contents($fichier);
		$doc = new DOMDocument();
		$doc->loadXML($contenu);
		
		$racine = $doc->documentElement;
		
		$this->_compiler($racine);
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
		
		print_r($r);
		return $r;
	}
	
	protected function _compilerNœud_tWriteJSONField($nœud, $r)
	{
		return $r;
	}
	
	protected $_nœuds;
}

?>
