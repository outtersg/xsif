<?php

class Ecrivain
{
	public function __construct($modele)
	{
		$this->_modele = $modele;
	}
	
	public function sortir($chaine)
	{
		echo $chaine;
	}
	
	public function ecrire($typeRacine)
	{
		$this->_resoudre($this->_modele[$typeRacine]);
		$r = array($typeRacine);
		$pondus = 0;
		while(count($r) > $pondus)
		{
			$this->_pondre($this->_modele[$typeRacine], $r[$pondus]);
			++$pondus;
		}
	}
	
	protected function _resoudre($arboModele)
	{
		if(isset($arboModele->contenu))
		{
			foreach($arboModele->contenu as $cle => $element)
			{
				if(is_string($element['t']))
				{
					if(!isset($this->_modele[$element['t']]))
						$this->_modele[$element['t']] = new Simple($element['t']);
					$arboModele->contenu[$cle]['t'] = $this->_modele[$element['t']];
				}
				$this->_resoudre($arboModele->contenu[$cle]['t']);
			}
			
			// Par défaut, tout est séquence.
			
			if(count($arboModele->contenu) == 1 && $arboModele->contenu[0]['t'] instanceof Sequence)
				$arboModele->contenu = $arboModele->contenu[0]['t']->contenu;
		}
	}
	
	protected function _pondre($arboModele, & $pileRestesAFaire)
	{
		$arboModele->pondre($this, $pileRestesAFaire);
	}
}

class Type
{
	public $contenu;
	
	public function nCellules($infosInvocation)
	{
		$r = 1;
		if(isset($infosInvocation['n']))
			++$r;
		return $r;
	}
	
	protected function _maxCellulesFils()
	{
		$r = 0;
		foreach($this->contenu as $ligne)
			if(($r1 = $ligne['t']->nCellules($ligne)) > $r)
				$r = $r1;
		return $r;
	}
	
	public function pondre($sortie, & $pileResteAFaire)
	{
		
	}
}

class Simple extends Type
{
}

class Complexe extends Type
{
	public function pondre($sortie, & $pileResteAFaire)
	{
		$nCellules = $this->_maxCellulesFils();
		$sortie->sortir('<table><tr><th colspan="'.$nCellules.'">'.htmlspecialchars('Nom type').'</th></tr>'."\n");
		foreach($this->contenu as $fils)
			$fils['t']->pondre($sortie, & $pileResteAFaire);
		$sortie->sortir('</table>'."\n");
	}
}

class Sequence extends Type
{
	public function nCellules($infosInvocation)
	{
		return $this->_maxCellulesFils();
	}
}

class Variante extends Type
{
	public function nCellules($infosInvocation)
	{
		return 1 + $this->_maxCellulesFils();
	}
	
	public function pondre($sortie, & $pileResteAFaire)
	{
		$sortie->sortir('<tr><td rowspan="'.count($this->_contenu).'">∈</td>');
		
	}
}

class Groupe extends Type
{
	public function nCellules($infosInvocation)
	{
		return $this->_maxCellulesFils() + (isset($infosInvocation['n']) ? 1 : 0);
	}
}

$variante = new Variante;
$variante->contenu[] = array('t' => new Sequence);
$variante->contenu[0]['t']->contenu[] = array('l' => 'equipe', 't' => 'local#Equipe', 'n' => '1..n');
$variante->contenu[0]['t']->contenu[] = array('l' => 'equipeDeChefs', 't' => 'local#Equipe');

$typeRacine = new Complexe;
$typeRacine->contenu[] = array('t' => $variante);

$personne = new Groupe;
$personne->contenu[] = array('l' => 'titre', 't' => 'xsd#string', 'n' => '?');
$personne->contenu[] = array('l' => 'prenom', 't' => 'xsd#string');
$personne->contenu[] = array('l' => 'nom', 't' => 'xsd#string');

$typeEquipe = new Complexe;
$typeEquipe->contenu[] = array('t' => new Sequence);
$typeEquipe->contenu[0]['t']->contenu[] = array('l' => 'optionnel', 't' => $personne, 'n' => '1..n');
$typeEquipe->contenu[0]['t']->contenu[] = array('l' => 'attribut', 't' => 'xsd#string', 'n' => '*');

$modele = array
(
	'local#Racine' => $typeRacine,
	'local#Equipe' => $typeEquipe,
);

$e = new Ecrivain($modele);
$e->ecrire('local#Racine');

?>
