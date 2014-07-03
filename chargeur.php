<?php

define('XS', 'http://www.w3.org/2001/XMLSchema');

class Chargeur
{
	protected $_espaceCible = null;
	protected $_pileEspacesCible = array();
	protected $_types = array();
	
	public function charge($chemin)
	{
		$doc = new DOMDocument();
		$doc->loadXML(file_get_contents($chemin));
		
		$racine = $doc->documentElement;
		
		$this->_compile($racine);
		
		return $this->_types;
	}
	
	protected function _compile($noeud)
	{
//		echo "=== ".$noeud->tagName." ===\n";
		
		if($noeud->namespaceURI == XS)
			switch($noeud->localName)
			{
				case 'schema':
					if($noeud->hasAttributeNS(null, 'targetNamespace'))
					{
						$nouvelEspace = 1;
						$this->_espaceCible = $noeud->getAttributeNS(null, 'targetNamespace');
						$this->_pileEspacesCible[] = $this->_espaceCible;
						
						$element = new stdClass;
					}
					break;
				case 'import':
					/* À FAIRE */
					return;
				case 'sequence': $element = new Sequence; break;
				case 'choice': $element = new Variante; break;
				case 'group':
					if(!($element = $this->_noeudEnRef($noeud, 'ref')))
					{
						$element = new Groupe;
						$this->_siloteSiNomme($noeud, $element);
					}
					break;
				case 'annotation': return;
				case 'element':
					if(($element = $this->_noeudEnRef($noeud, 'type')))
						break;
					// Sinon on continue en Interne.
				case 'enumeration':
				case 'complexContent':
				case 'pattern':
				case 'maxLength':
				case 'length':
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
		
		if(isset($noeud->childNodes))
			foreach($noeud->childNodes as $fils)
				if($fils instanceof DomElement)
					if(($filsCompile = $this->_compile($fils)))
						$element->contenu[] = $filsCompile;
		
		if(isset($nouvelEspace))
			$this->_espaceCible = array_pop($this->_pileEspacesCible);
		
		$r = array('t' => $element);
		
		if($noeud->hasAttributeNS(null, 'name'))
			$r['l'] = $noeud->getAttributeNS(null, 'name');
		
		if(($n = $this->_n($noeud)) !== null)
			$r['n'] = $n;
		
		if(is_object($element))
			$r = $this->_resoudreInternes($r);
		
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
		}
	}
	
	protected function _resoudreInternes($r)
	{
		$element = $r['t'];
		
		if(isset($element->contenu))
			foreach($element->contenu as $num => $contenu)
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
		if($element instanceof Simple && count(array_diff_key($r, array('t' => 1, 'l' => 1))) == 0 && count($element->contenu) == 1)
			$r = $element->contenu[0] + $r;
		if($element instanceof Interne)
			switch($element->type)
			{
				case 'element':
					if(count(array_diff_key($r, array('t' => 1, 'l' => 1))) == 0 && count($element->attr) == 1 && isset($element->attr['name']) && $element->attr['name'] == $r['l'] && count($element->contenu) == 1) // Peut être le résultat d'une compression antérieure (element > complexContent > restriction devenus un simple element, par exemple).
						$r = $element->contenu[0] + $r;
					break;
				case 'extension':
					if(isset($element->contenu) && count($element->contenu) == 1)
					{
						$nouveau = new Liste;
						$nouveau->contenu[] = array('t' => $element->attr['base'], 'commeExtension' => true);
						$nouveau->contenu = array_merge($nouveau->contenu, $element->contenu);
						$r['t'] = $nouveau;
					}
					break;
				case 'restriction':
					if(isset($element->contenu))
						if(count($element->contenu) == 1 && $element->contenu[0]['t'] instanceof Interne)
							switch($element->contenu[0]['t']->type)
							{
								case 'maxLength':
									$r['t'] = $r['t']->attr['base'];
									$r['text'] = '['.$element->contenu[0]['t']->attr['value'].']';
									break;
								case 'length':
									$r['t'] = $r['t']->attr['base'];
									$r['text'] = '{'.$element->contenu[0]['t']->attr['value'].'}';
									break;
							}
						else
						{
							$enum = array();
							foreach($element->contenu as $sousElement)
								if($sousElement['t'] instanceof Interne && $sousElement['t']->type == 'enumeration')
									$enum[] = $sousElement['t']->attr['value'];
								else
									break 2;
							$r['t'] = $r['t']->attr['base'];
							$r['text'] = '{'.implode(',', $enum).'}';
						}
					break;
				case 'complexContent':
					if(!count($element->attr) && count($element->contenu) == 1 && count($element->contenu[0]) == 1) // contenu[0] == 1 pour être sûr qu'il n'y a que le 't' =>.
						$r = $element->contenu[0];
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

?>