<?php
/*
 * Copyright (c) 2014 Guillaume Outters
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

define('XS', 'http://www.w3.org/2001/XMLSchema');
define('WSDL', 'http://schemas.xmlsoap.org/wsdl/');
define('SOAP', 'http://schemas.xmlsoap.org/wsdl/soap/');
define('SOAPE', 'http://schemas.xmlsoap.org/soap/encoding/');

class Chargeur
{
	// Espaces de nommage standards, qu'on ne prend pas la peine d'aller introspecter.
	public static $ESPACES_TYPES_STD = array
	(
		XS,
		WSDL,
		SOAPE,
	);
	
	protected $_cheminActuel;
	protected $_espaceCible = null;
	protected $_pileEspacesCible = array();
	protected $_types = array();
	protected $_fichiers = array();
	
	protected $_racine;
	
	public function cheminPropre($chemin)
	{
		// Pour une URL, on ne bosse que sur la partie chemin.
		
		if(preg_match('#^[a-z]+://[^/]+#', $chemin, $rés))
		{
			$préfixe = $rés[0];
			$chemin = substr($chemin, strlen($préfixe));
		}
		else
			$préfixe = '';
		
		// Un explode sur / ne doit pas considérer que // contient un élément de longueur 0.
		
		$chemin = preg_replace('#/+#', '/', $chemin);
		
		// Idem en début de chemin, on ne veut pas que "/.." soit considéré comme deux éléments de part et d'autre du / (vide, et ..): le .. absorberait alors le vide, ce qui n'a pas de sens.
		
		if(substr($chemin, 0, 1) == '/')
		{
			$préfixe .= '/';
			$chemin = substr($chemin, 1);
		}
		
		// En avant.
		
		$chemin = explode('/', $chemin);
		for($i = 0; $i < count($chemin); ++$i)
			switch($chemin[$i])
			{
				case '.': array_splice($chemin, $i, 1); --$i; break;
				case '..': if($i > 0) { array_splice($chemin, $i - 1, 2); $i -= 2; } break;
			}
		
		return $préfixe.implode('/', $chemin);
	}
	
	public function charge($chemin)
	{
		$ancienChemin = $this->_cheminActuel;
		if(isset($ancienChemin) && !preg_match('#^[a-z]+://#', $chemin) && substr($chemin, 0, 1) != '/')
			$chemin = dirname($ancienChemin).'/'.$chemin;
		$chemin = $this->cheminPropre($chemin);
		if (file_exists($chemin)) {
			$chemin = realpath($chemin);
		}
		
		if(($contenu = file_get_contents($chemin)) === false)
			throw new Exception('Impossible de charger '.$chemin);
		
		// Inutile de refaire un fichier déjà parcouru.
		// À FAIRE?: dans le cas d'un include dans un nœud intérieur, faudrait-il réinclure quand même son contenu à la manière d'un <group>?
		if(isset($this->_fichiers[$chemin]))
			return;
		$this->_fichiers[$chemin] = true;
		
		$this->_cheminActuel = $chemin;
		
		$doc = new DOMDocument();
		$doc->loadXML($contenu);
		
		$racine = $doc->documentElement;
		
		$this->_compile($racine);
		
		$this->_cheminActuel = $ancienChemin;
		
		// Si la racine par défaut n'est pas en première position, on la replace.
		if(isset($this->_racine) && ($cleTypes = array_keys($this->_types)) && $cleTypes[0] != $this->_racine)
		{
			$racine = $this->_types[$this->_racine];
			unset($this->_types[$this->_racine]);
			$this->_types = array($this->_racine => $racine) + $this->_types;
		}
		
		return $this->_types;
	}
	
	protected function _compile($noeud)
	{
//		echo "=== ".$noeud->tagName." ===\n";
		
		$poubelle = new stdClass;
		if($noeud->namespaceURI == XS || $noeud->namespaceURI == WSDL || $noeud->namespaceURI == SOAP)
			switch($noeud->localName)
			{
				/* Plutôt orientés WSDL. */
				case 'service':
					if(isset($this->_racine) && !($this->_racine instanceof Service))
						unset($this->_racine);
					$element = new Service;
					$this->_siloteSiNomme($noeud, $element);
					break;
				case 'address':
					$element = false;
					break;
				case 'port':
					$element = $this->_noeudEnInterneRef($noeud, 'binding', 'binding@');
					break;
				case 'binding':
					if(($ref = $this->_noeudEnRef($noeud, 'type')))
					{
						$element = new Service;
						$this->_siloteSiNomme($noeud, $element);
						$element->contenu[] = array('t' => 'portType@'.$ref);
					}
					$element = false;
					break;
				case 'input':
				case 'output':
				case 'fault':
					if(($ref = $this->_noeudEnRef($noeud, 'message')))
					{
						$element = new ParametresMethode;
						$element->contenu[] = array('t' => 'message@'.$ref, 'l' => $noeud->localName);
					}
					else
						$element = new stdClass;
					break;
				case 'header':
				case 'body':
					$element = false;
				case 'portType':
					$element = new Service;
					$this->_siloteSiNomme($noeud, $element);
					break;
				case 'operation':
					$element = new Methode;
					break;
				case 'message':
					$element = new Simple;
					$this->_siloteSiNomme($noeud, $element);
					break;
				case 'part':
					$element = $this->_noeudEnInterneRef($noeud, 'element', 'e@');
					$element || $element = $this->_noeudEnInterneRef($noeud, 'type');
					break;
				case 'types':
					$element = $poubelle;
					break;
				/* Partagés WSDL / XSD. */
				case 'schema':
				case 'definitions':
					if($noeud->hasAttributeNS(null, 'targetNamespace'))
					{
						$nouvelEspace = 1;
						$this->_pileEspacesCible[] = $this->_espaceCible;
						$this->_espaceCible = $noeud->getAttributeNS(null, 'targetNamespace');
					}
					$element = new Interne($noeud->localName, array());
					break;
				/* Plutôt orientés XSD. */
				case 'include':
				case 'import':
					foreach(array('schemaLocation', 'location') as $attrEmplacement)
						if($noeud->hasAttributeNS(null, $attrEmplacement))
							$this->charge($noeud->getAttributeNS(null, $attrEmplacement));
					return;
				case 'all': // De notre point de vue, un all c'est une sequence (la seule différence est que l'all n'impose pas d'ordre).
				case 'sequence': $element = new Sequence; break;
				case 'choice': $element = new Variante; break;
				case 'any': $element = new NImporteQuoi; break;
				case 'group':
					if(!($element = $this->_noeudEnRef($noeud, 'ref')))
					{
						$element = new Groupe;
						$this->_siloteSiNomme($noeud, $element);
					}
					break;
				case 'annotation': $element = new Interne($noeud->localName, $noeud->attributes); break;
				case 'documentation': $element = new Commentaire($noeud->localName, $noeud->attributes); break;
				case 'attribute':
				case 'element':
					$refEl = null;
					if(($elementRef = $this->_noeudEnRef($noeud, 'type')) || ($refEl = $this->_noeudEnRef($noeud, 'ref')))
					{
						$element = new Interne('ref', $noeud->attributes);
						if($noeud->localName == 'element')
							$element->element = true;
						if(isset($refEl))
						{
							$espace = explode('#', $refEl);
							// Une réf vers un type standard, c'est le type standard lui-même.
							if(!in_array($espace[0], Chargeur::$ESPACES_TYPES_STD))
								$elementRef = 'e@'.$refEl;
							else
								$elementRef = $refEl;
						}
						$element->ref = $elementRef;
						break;
					}
					// Sinon on continue en Interne.
				case 'enumeration':
				case 'complexContent':
					/* À FAIRE: le complexContent peut être mixed="true": incluant un mélange de textes et balises façon HTML? */
				case 'pattern':
				case 'maxLength':
				case 'minLength':
				case 'length':
				case 'maxInclusive':
				case 'minInclusive':
				case 'maxExclusive':
				case 'minExclusive':
				case 'totalDigits':
				case 'fractionDigits':
					$element = new Interne($noeud->localName, $noeud->attributes);
					// Un <element> (cf. case 'element' sans break ci-dessus) peut être nommé, auquel cas on l'expose.
					$this->_siloteSiNomme($noeud, $element);
					break;
				case 'extension':
				case 'restriction':
					$element = new Interne($noeud->localName, $noeud->attributes);
					$element->attr['base'] = $this->_noeudEnRef($noeud, 'base');
					break;
				case 'complexType':
				case 'simpleType':
					$element = $noeud->localName == 'complexType' ? new Complexe : new Simple;
					$this->_siloteSiNomme($noeud, $element);
					break;
			}
		
		if(!isset($element))
			throw new Exception($this->_cheminActuel.':'.$noeud->getLineNo().': impossible de compiler '.$noeud->localName);
		
		if($element === false) // L'élément a souhaité indiquer qu'il était bien pris en compte, mais qu'il doit être ignoré dans le résultat final (ainsi que tous ses enfants).
			return $element;
		
		if(in_array($noeud->localName, array('attribute')) && !isset($element->attr['#prio']))
			$element->attr['#prio'] = -1;
		
		if(isset($noeud->childNodes))
			foreach($noeud->childNodes as $fils)
				if($fils instanceof DomElement)
				{
					if(($filsCompile = $this->_compile($fils)))
					{
						if(!isset($element->contenu))
							$element->contenu = array();
						$element->contenu[] = $filsCompile;
					}
				}
				else if($fils instanceof DomText)
					if(method_exists($element, 'texte'))
						$element->texte($fils->wholeText);
		
		$r = array('t' => $element);
		
		if($noeud->hasAttributeNS(null, 'name'))
			$r['l'] = $noeud->getAttributeNS(null, 'name');
		
		if(($n = $this->_n($noeud)) !== null)
			$r['n'] = $n;
		
		if(is_object($element))
			$r = $this->_resoudreInternes($r, $noeud);
		
		if(isset($nouvelEspace))
			$this->_espaceCible = array_pop($this->_pileEspacesCible);
		
		return $r;
	}
	
	protected function _n($noeud)
	{
		$min = $noeud->hasAttributeNS(null, 'minOccurs') ? $noeud->getAttributeNS(null, 'minOccurs') : 1;
		$max = $noeud->hasAttributeNS(null, 'maxOccurs') ? $noeud->getAttributeNS(null, 'maxOccurs') : 1;
		if($min == $max)
			return $max == 1 ? null : $max;
		if($max == 'unbounded')
			return $min ? ($min == 1 ? '+' : $min.'+') : '*';
		if($max == 1)
			return '?';
		return $min.'..'.$max;
	}
	
	protected function _noeudEnInterneRef($noeud, $attrRef, $prefixe = '')
	{
		if(($elementRef = $this->_noeudEnRef($noeud, $attrRef)))
		{
			$element = new Interne('ref', array());
			$element->ref = $prefixe.$elementRef;
			return $element;
		}
		
		return null;
	}
	
	protected function _noeudEnRef($noeud, $attrRef)
	{
		if($noeud->hasAttributeNS(null, $attrRef))
		{
			$id = $noeud->getAttributeNS(null, $attrRef);
			return $this->_idEnRef($noeud, $id);
		}
	}
	
	protected function _idEnRef($noeud, $id)
	{
		$classe = explode(':', $id, 2);
			if(count($classe) == 2)
			{
				$espace = $classe[0];
				$classe = $classe[1];
			}
			else
			{
				$espace = null;
				$classe = $classe[0];
			}
			$espace = $noeud->lookupNamespaceURI($espace);
			return "$espace#$classe";
	}
	
	protected function _siloteSiNomme($noeud, $element)
	{
		if($noeud->hasAttributeNS(null, 'name'))
		{
			$nom = $noeud->getAttributeNS(null, 'name');
			$espace = $this->_espaceCible;
			$classe = $espace.'#'.$nom;
			// Dans l'espace WSDL, un même nom peut être partagé par plusieurs balises; la balise est donc différenciante.
			// De cet espace seul le part est assimilable à un type XSD.
			if($noeud->namespaceURI == WSDL)
				$classe = $noeud->localName.'@'.$classe;
			// Les éléments sont distingués des types par un préfixe e@ (pour la structure <element name="Truc" type="Truc"/>: le type Truc ne réfère évidemment pas à l'élément, mais à un type générique déclaré précédemment).
			else if($noeud->localName == 'element')
				$classe = 'e@'.$classe;
			$element->contenu = array();
			$this->_types[$classe] = $element;
			// Si c'est la première déclaration du fichier racine (la pile ne contient que le schéma du premier XSD), cette déclaration sera notre racine par défaut.
			if(count($this->_pileEspacesCible) == 1 && !isset($this->_racine))
				$this->_racine = $classe;
		}
	}
	
	protected function _exposeElement($nom, $element)
	{
		if(is_string($element))
		{
			$ref = $element;
			$element = new Complexe;
			$element->contenu[] = array('t' => $ref, 'commeExtension' => true, 'l' => $nom);
		}
		$element->nom = $nom;
		$this->_types['e@'.$this->_espaceCible.'#'.$nom] = $element;
	}
	
	protected function _resoudreInternes($r, $noeud)
	{
		$element = $r['t'];
		
		if(isset($element->contenu))
		{
			foreach($element->contenu as $num => $contenu)
			{
				if(($contenu['t'] instanceof Interne && $contenu['t']->type == 'annotation') || ($contenu['t'] === null && isset($contenu['doc']) && count($contenu) == 2))
				{
					if(isset($contenu['doc']))
					$r['doc'] = isset($r['doc']) ? $r['doc']."\n".$contenu['doc'] : $contenu['doc'];
					unset($r['t']->contenu[$num]);
					$retasser = true;
				}
				else
				if($contenu['t'] instanceof Interne)
				{
					if($contenu['t']->type == 'element' && $element instanceof Liste && isset($contenu['t']->contenu) && count($contenu['t']->contenu) == 1)
					{
						$sousContenu = $contenu['t']->contenu[0];
						if(!isset($sousContenu['n']) && is_string($sousContenu['t']) || $sousContenu['t'] instanceof Type)
							$r['t']->contenu[$num]['t'] = $sousContenu['t'];
					}
					if($contenu['t']->type == 'extension')
					{
						echo "extension non pris en compte\n";
						print_r($r);exit;
					}
					if($contenu['t']->type == 'complexContent')
					{
						echo "complexContent non pris en compte\n";
						print_r($r);exit;
					}
				}
			}
			if(isset($retasser))
				$element->contenu = array_merge($element->contenu);
		}
		if($element instanceof Simple && count(array_diff_key($r, array('t' => 1, 'l' => 1))) == 0 && count($element->contenu) == 1)
			$r = $element->contenu[0] + $r;
		if($element instanceof Commentaire)
		{
			if(isset($element->texte))
			$r['doc'] = isset($r['doc']) ? $r['doc']."\n".$element->texte : $element->texte;
			$r['t'] = null;
		}
		$élémentNonTypé = false;
		if($element instanceof Interne)
			switch($element->type)
			{
				case 'element':
					// Si notre élément peut être remplacé par son contenu (pas d'attributs en commun si ce n'est le type), on combine.
					// Ce peut être le résultat d'une compression antérieure (element > complexContent > restriction devenus un simple element, par exemple).
					if(isset($element->contenu) && count($element->contenu) == 1 && count(array_intersect_key($r, $element->contenu[0])) == 1)
					{
						$r = $element->contenu[0] + $r;
						if(($public = array_search($element, $this->_types, true)) !== false)
							$this->_types[$public] = $r['t'];
						$element = $r['t'];
						break;
					}
					else
						$élémentNonTypé = true;
					$r['element'] = true;
					// Et l'on passe pour laisser à 'ref' une chance de récupérer l'entrée, dans le cas où elle est vide et non typée (si elle l'avait été elle aurait été d'emblée transformée en 'ref').
				case 'ref':
					if (!isset($element->contenu) || !count($element->contenu))
					{
						$r['t'] = $élémentNonTypé ? XS.'#anySimpleType' : $element->ref; // https://stackoverflow.com/a/29846783/1346819
						if(isset($element->element))
							$r['element'] = true;
						if(isset($element->attr))
							$r['attr'] = $element->attr;
					}
					break;
				case 'annotation':
					if(count($element->contenu) == 1 && !isset($element->contenu[0]['t']))
					{
						if(isset($element->contenu[0]['doc']))
						$r['doc'] = $element->contenu[0]['doc'];
						unset($element->contenu);
					}
					break;
				case 'extension':
					if(!isset($element->contenu) || count($element->contenu) == 1)
					{
						$nouveau = new Liste;
						$nouveau->contenu[] = array('t' => $element->attr['base'], 'commeExtension' => true);
						if(isset($element->contenu))
						$nouveau->contenu = array_merge($nouveau->contenu, $element->contenu);
						$r['t'] = $nouveau;
					}
					break;
				case 'restriction':
					if(isset($element->contenu))
						{
							$enum = array();
						$taille = array();
						$plage = array();
						$decimaux = array();
							$exprs = array();
						$types = array();
						$nombre = array();
							foreach($element->contenu as $numSousElement => $sousElement)
								if($sousElement['t'] instanceof Interne)
								switch($sousElement['t']->type)
								{
									case 'enumeration':
									$enum[] = $sousElement['t']->attr['value'];
										break;
									case 'minLength':
										$val = $sousElement['t']->attr['value'];
										if(!$val) $val = null;
										$taille[$sousElement['t']->type] = $val; // On inscrit de toute façon la taille, histoire d'avoir un élément dans $taille.
										break;
									case 'maxLength':
									case 'length':
										$val = $sousElement['t']->attr['value'];
										$taille[$sousElement['t']->type] = $val;
										break;
									case 'maxInclusive':
									case 'minInclusive':
										$val = $sousElement['t']->attr['value'];
										$plage[$sousElement['t']->type] = $val;
										break;
									case 'maxExclusive':
									case 'minExclusive':
										$val = $sousElement['t']->attr['value'];
										$plage[$sousElement['t']->type] = $val;
										break;
									case 'totalDigits':
									case 'fractionDigits':
										$val = $sousElement['t']->attr['value'];
										$decimaux[$sousElement['t']->type] = $val;
										break;
									case 'pattern':
										$exprs[] = $sousElement['t']->attr['value'];
										break;
									default:
										echo "# Élément de restriction non rendu ".$sousElement['t']->type."\n";
										break 3;
								}
							else if($sousElement['t'] == SOAPE.'#arrayType' && isset($sousElement['attr']['arrayType']) && substr($sousElement['attr']['arrayType'], -2) == '[]')
							{
								$types[substr($sousElement['attr']['arrayType'], 0, -2)] = true;
								// Par défaut, un tableau est en *.
								if(!isset($nombre['minOccurs']))
									$nombre['minOccurs'] = 0;
								if(!isset($nombre['maxOccurs']))
									$nombre['maxOccurs'] = 'unbounded';
							}
								else
									break 2;
						if(count($types))
						{
							if(count($types) > 1)
							{
								echo "restriction sur plusieurs types à la fois\n";
								print_r($r);exit;
							}
							foreach($types as $nouveauType => $true) {}
							$type = $this->_idEnRef($noeud, $nouveauType);
							if($r['t']->attr['base'] != SOAPE.'#Array')
							{
								echo "conversion d'un ".$r['t']->attr['base']." en {$type}[]";
								print_r($r);exit;
							}
							$nouveau = new Sequence;
							/* À FAIRE: convertir proprement les minOccurs et maxOccurs (combiner éventuellement avec d'autres contraintes plus haut) plutôt que de forcer à "*". */
							$nouveau->contenu[] = array('t' => $type, 'n' => '*', 'l' => 'item', 'element' => true);
							$r['t'] = $nouveau;
						}
						if(count($enum))
						{
							$r['t'] = $r['t']->attr['base'];
							$r['text'] = '{'.implode(',', $enum).'}';
						}
						else if(count($decimaux))
						{
							$r['t'] = $r['t']->attr['base'];
							$nApres = isset($decimaux['fractionDigits']) ? $decimaux['fractionDigits'] : 0;
							$nAvant = $decimaux['totalDigits'] - $nApres;
							$r['text'] = '{'.$nAvant.($nApres ? '.'.$nApres : '').'}';
						}
						else if(count($plage))
						{
							$r['t'] = $r['t']->attr['base'];
							if(isset($plage['minExclusive']))
							{
								$min = $plage['minExclusive'];
								$minExcl = true;
							}
							else if(isset($plage['minInclusive']))
								$min = $plage['minInclusive'];
							if(isset($plage['maxExclusive']))
							{
								$max = $plage['maxExclusive'];
								$maxExcl = true;
							}
							else if(isset($plage['maxInclusive']))
								$max = $plage['maxInclusive'];
							if(isset($min) && isset($max))
								$r['text'] = (isset($minExcl) ? ']' : '[').$min.';'.$max.(isset($maxExcl) ? '[' : ']');
							else if(isset($min))
								$r['text'] = (isset($minExcl) ? '>' : '≥').$min;
							else
								$r['text'] = (isset($maxExcl) ? '<' : '≤').$max;
						}
						else if(count($taille))
						{
							$r['t'] = $r['t']->attr['base'];
							if(isset($taille['length']))
								$r['text'] = '{'.$taille['length'].'}';
							else if(!isset($taille['minLength']))
								$r['text'] = '['.$taille['maxLength'].']';
							else if(!isset($taille['maxLength']))
								$r['text'] = '{'.$taille['minLength'].'+}';
							else
								$r['text'] = '{'.$taille['minLength'].'..'.$taille['maxLength'].'}';
						}
						if(count($exprs))
						{
							if(!is_string($r['t']))
								$r['t'] = $r['t']->attr['base'];
							$r['text'] = (isset($r['text']) ? $r['text'].', ' : '').'~= '.implode(', ~= ', $exprs);
						}
					}
					else // Une restriction sans restriction, c'est le type de base, en fait.
						$r['t'] = $r['t']->attr['base'];
					break;
				case 'complexContent':
					// Les attributs parasites à valeur par défaut sautent.
					/* À FAIRE?: à un niveau plus haut? */
					if(isset($element->attr['mixed']) && $element->attr['mixed'] === 'false')
						unset($element->attr['mixed']);
					if(!count($element->attr) && count($element->contenu) == 1 && count($element->contenu[0]) == 1) // contenu[0] == 1 pour être sûr qu'il n'y a que le 't' =>.
						$r = $element->contenu[0];
					break;
				case 'schema':
					// Les element enfants du schema d'un XSD ont une visibilité globale (par exemple pour utilisation en tant qu'element dans les input d'une méthode SOAP).
					if(isset($element->contenu))
						foreach($element->contenu as $contenu)
							if(isset($contenu['element']))
								$this->_exposeElement($contenu['l'], $contenu['t']);
					break;
			}
		
		return $r;
	}
}

class Interne
{
	public $type;
	public $attr;
	public $contenu;
	public $ref;
	public $element;
	public $_enResolution;
	
	public function __construct($typeXS, $attributs)
	{
		$this->type = $typeXS;
		$this->attr = array();
		foreach($attributs as $attr)
		{
			if(!in_array($attr->namespaceURI, Chargeur::$ESPACES_TYPES_STD) && $attr->namespaceURI !== null)
				throw new Exception("L'attribut ".$attr->localName." doit appartenir à l'espace XML Schema.");
			$this->attr[$attr->localName] = $attr->value;
		}
	}
	
	public function pondre()
	{
		echo "Interne non pris en compte\n"; // Normalement tous les internes doivent avoir été résolus au moment de la ponte.
		print_r($this);exit;
	}
}

class Commentaire extends Type
{
	public $texte;
	
	public function texte($texte)
	{
		$this->texte = isset($this->texte) ? $this->texte.$texte : $texte;
	}
}

?>
