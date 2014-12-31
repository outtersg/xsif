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

class Chargeur
{
	protected $_cheminActuel;
	protected $_espaceCible = null;
	protected $_pileEspacesCible = array();
	protected $_types = array();
	protected $_fichiers = array();
	
	public function charge($chemin)
	{
		$ancienChemin = $this->_cheminActuel;
		if(isset($ancienChemin))
			$chemin = dirname($ancienChemin).'/'.$chemin;
		if (!file_exists($chemin)) {
			throw new Exception('Impossible de charger '.$chemin);
		}
		$chemin = realpath($chemin);
		
		// Inutile de refaire un fichier déjà parcouru.
		// À FAIRE?: dans le cas d'un include dans un nœud intérieur, faudrait-il réinclure quand même son contenu à la manière d'un <group>?
		if(isset($this->_fichiers[$chemin]))
			return;
		$this->_fichiers[$chemin] = true;
		
		$this->_cheminActuel = $chemin;
		
		$doc = new DOMDocument();
		$doc->loadXML(file_get_contents($chemin));
		
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
					$element = $this->_noeudEnInterneRef($noeud, 'binding');
					break;
				case 'binding':
					if(($ref = $this->_noeudEnRef($noeud, 'type')))
					{
						$element = new Service;
						$this->_siloteSiNomme($noeud, $element);
						$element->contenu[] = array('t' => $ref);
					}
					$element = false;
					break;
				case 'input':
				case 'output':
					if(($ref = $this->_noeudEnRef($noeud, 'message')))
					{
						$element = new ParametresMethode;
						$element->contenu[] = array('t' => $ref, 'l' => $noeud->localName);
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
					$element = $this->_noeudEnInterneRef($noeud, 'element');
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
					if($noeud->hasAttributeNS(null, 'schemaLocation'))
						$this->charge($noeud->getAttributeNS(null, 'schemaLocation'));
					return;
				case 'all': // De notre point de vue, un all c'est une sequence (la seule différence est que l'all n'impose pas d'ordre).
				case 'sequence': $element = new Sequence; break;
				case 'choice': $element = new Variante; break;
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
					if(($elementRef = $this->_noeudEnRef($noeud, 'type')))
					{
						$element = new Interne('ref', array());
						if($noeud->localName == 'element')
							$element->element = true;
						$element->ref = $elementRef;
						break;
					}
					// Sinon on continue en Interne.
				case 'enumeration':
				case 'complexContent':
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
			throw new Exception('Impossible de compiler '.$noeud->localName);
		
		if($element === false) // L'élément a souhaité indiquer qu'il était bien pris en compte, mais qu'il doit être ignoré dans le résultat final (ainsi que tous ses enfants).
			return $element;
		
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
			$r = $this->_resoudreInternes($r);
		
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
			$classe = explode(':', $noeud->getAttributeNS(null, $attrRef), 2);
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
		
		return null;
	}
	
	protected function _siloteSiNomme($noeud, $element)
	{
		if($noeud->hasAttributeNS(null, 'name'))
		{
			$nom = $noeud->getAttributeNS(null, 'name');
			$espace = $this->_espaceCible;
			$classe = $espace.'#'.$nom;
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
			$element->contenu[] = array('t' => $ref, 'commeExtension' => true);
		}
		$this->_types['e@'.$this->_espaceCible.'#'.$nom] = $element;
	}
	
	protected function _resoudreInternes($r)
	{
		$element = $r['t'];
		
		if(isset($element->contenu))
		{
			foreach($element->contenu as $num => $contenu)
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
					if($contenu['t']->type == 'element' && $element instanceof Liste && count($contenu['t']->contenu) == 1)
					{
						$sousContenu = $contenu['t']->contenu[0];
						if(!isset($sousContenu['n']) && is_string($sousContenu['t']) || $sousContenu['t'] instanceof Type)
							$r['t']->contenu[$num]['t'] = $sousContenu['t'];
					}
					if($contenu['t']->type == 'extension')
					{
						print_r($r);exit;
					}
					if($contenu['t']->type == 'complexContent')
					{
						print_r($r);exit;
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
		if($element instanceof Interne)
			switch($element->type)
			{
				case 'ref':
					if (!isset($element->contenu) || !count($element->contenu))
					{
						$r['t'] = $element->ref;
						if(isset($element->element))
							$r['element'] = true;
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
				case 'element':
					// Si notre élément peut être remplacé par son contenu (pas d'attributs en commun si ce n'est le type), on combine.
					// Ce peut être le résultat d'une compression antérieure (element > complexContent > restriction devenus un simple element, par exemple).
					if(count($element->contenu) == 1 && count(array_intersect_key($r, $element->contenu[0]) == 1))
						$r = $element->contenu[0] + $r;
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
							foreach($element->contenu as $sousElement)
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
									default:
										break 3;
								}
								else
									break 2;
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
					}
					else // Une restriction sans restriction, c'est le type de base, en fait.
						$r['t'] = $r['t']->attr['base'];
					break;
				case 'complexContent':
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
	public function __construct($typeXS, $attributs)
	{
		$this->type = $typeXS;
		$this->attr = array();
		foreach($attributs as $attr)
		{
			if($attr->namespaceURI != XS && $attr->namespaceURI !== null)
				throw new Exception("L'attribut ".$attr->localName." doit appartenir à l'espace XML Schema.");
			$this->attr[$attr->localName] = $attr->value;
		}
	}
	
	public function pondre()
	{
		print_r($this);exit;
	}
}

class Commentaire extends Type
{
	public function texte($texte)
	{
		$this->texte = isset($this->texte) ? $this->texte.$texte : $texte;
	}
}

?>
