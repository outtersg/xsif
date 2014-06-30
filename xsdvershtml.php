<?php

class SortieHtml
{
	public function commencer()
	{
		$this->_sortir('<?xml version="1" encoding="UTF-8"?>
		<html>
			<head>
				<title>Diagramme</title>
				<style type="text/css">
					table, td
					{
						border-collapse: collapse;
						border: 1px solid grey;
					}
				</style>
			</head>
		<body>'."\n");
	}
	
	public function commencerTable($chaine)
	{
		$this->_sortir($chaine);
		$this->_ligneOuverte = false;
		$this->_ligneEcrite = false;
	}
	
	public function debutLigne($chaine)
	{
		$this->_finirLigne();
		$this->_commencerLigne();
		$this->_sortir($chaine);
	}
	
	public function ligne($chaine)
	{
		if($this->_ligneEcrite)
			$this->_finirLigne();
		$this->_commencerLigne();
		$this->_sortir($chaine);
		$this->_ligneEcrite = true;
	}
	
	public function finLigne($chaine)
	{
		$this->_sortir($chaine);
	}
	
	protected function _commencerLigne()
	{
		if(!$this->_ligneOuverte)
			$this->_sortir('<tr>');
		$this->_ligneOuverte = true;
		$this->_ligneEcrite = false;
	}
	
	protected function _finirLigne()
	{
		if($this->_ligneOuverte)
			$this->_sortir('</tr>'."\n");
		$this->_ligneOuverte = false;
		$this->_ligneEcrite = false;
	}
	
	public function finirTable($chaine)
	{
		$this->_finirLigne();
		$this->_sortir($chaine);
	}
	
	public function finir()
	{
		$this->_sortir('</body></html>');
	}
	
	public function _sortir($chaine)
	{
		echo $chaine;
	}
}

class Ecrivain
{
	public function __construct($modele)
	{
		$this->_modele = $modele;
		$this->_sortie = new SortieHtml;
	}
	
	public function ecrire($typeRacine)
	{
		$this->_sortie->commencer();
		$this->_resoudre($this->_modele[$typeRacine]);
		$r = array($typeRacine);
		$pondus = 0;
		while(count($r) > $pondus)
		{
			$this->_modele[$r[$pondus]]->pondre($r[$pondus], null, $this->_sortie, $r);
			++$pondus;
		}
		$this->_sortie->finir();
	}
	
	protected function _resoudre($arboModele)
	{
		if(isset($arboModele->contenu))
		{
			foreach($arboModele->contenu as $cle => $element)
			{
				if(is_string($element['t']))
				{
					$arboModele->contenu[$cle]['classe'] = $element['t'];
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
}

class Type
{
	public $contenu;
	
	public function nCellules($infosInvocation)
	{
		return $this->_maxCellulesFils() + (isset($infosInvocation['n']) ? 1 : 0);
	}
	
	protected function _maxCellulesFils()
	{
		if(!isset($this->contenu))
			return 1;
		
		$r = 0;
		foreach($this->contenu as $ligne)
			if(($r1 = $ligne['t']->nCellules($ligne)) > $r)
				$r = $r1;
		return $r;
	}
	
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire, $nCellules = 0)
	{
		if(!isset($this->contenu))
			return;
		
		foreach($this->contenu as $fils)
			$fils['t']->pondre($chemin.'.'.$fils['l'], $fils, $sortie, $pileResteAFaire, $nCellules - (isset($infosInvocation['n']) ? 1 : 0));
		if(isset($infosInvocation['n']))
			$sortie->finLigne('<td>'.$infosInvocation['n'].'</td>');
	}
}

class Simple extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire, $nCellules = 0)
	{
		$nCellulesReel = $nCellules - (isset($infosInvocation['n']) ? 1 : 0);
		$sortie->ligne('<td colspan="'.$nCellulesReel.'">'.htmlspecialchars($infosInvocation['l']).'</td>');
		if(isset($infosInvocation['n']))
			$sortie->finLigne('<td>'.$infosInvocation['n'].'</td>');
	}
}

class Complexe extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire, $nCellules = 0)
	{
		$nomClasse = explode('#', $chemin, 2);
		$nomClasse = $nomClasse[1];
		
		// Si on est appelés dans le cadre d'un autre, on s'inscrit uniquement comme libellé dans celui-ci, et on se met en file d'attente pour la "vraie" ponte.
		if(isset($infosInvocation))
		{
			$nCellulesReel = $nCellules - (isset($infosInvocation['n']) ? 1 : 0);
			$sortie->ligne('<td colspan="'.$nCellulesReel.'">'.htmlspecialchars($infosInvocation['l']).'</td>');
			if(isset($infosInvocation['n']))
				$sortie->finLigne('<td>'.$infosInvocation['n'].'</td>');
			$nomBloc = isset($infosInvocation['classe']) ? $infosInvocation['classe'] : $chemin;
			if(!in_array($nomBloc, $pileResteAFaire))
				$pileResteAFaire[] = $nomBloc;
			if(!isset($this->_modele[$nomBloc]))
				$this->_modele[$nomBloc] = $infosInvocation['t'];
			return;
		}
		
		$nCellules = $this->_maxCellulesFils();
		$sortie->commencerTable('<table>'."\n".'<tr><th colspan="'.$nCellules.'">'.htmlspecialchars($nomClasse).'</th></tr>'."\n");
		foreach($this->contenu as $fils)
			$fils['t']->pondre($chemin.'.'.(isset($fils['l']) ? $fils['l'] : get_class($fils['t'])), $fils, $sortie, $pileResteAFaire, $nCellules);
		$sortie->finirTable('</table>'."\n");
	}
}

class Sequence extends Type
{
}

class Variante extends Type
{
	public function nCellules($infosInvocation)
	{
		return 1 + parent::nCellules($infosInvocation);
	}
	
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire, $nCellules = 0)
	{
		$sortie->debutLigne('<td rowspan="'.count($this->contenu).'">∈</td>');
		parent::pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire, $nCellules - 1);
	}
}

class Groupe extends Type
{
	public function nCellules($infosInvocation)
	{
		return parent::nCellules($infosInvocation) + (isset($infosInvocation['n']) ? 1 : 0);
	}
	
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire, $nCellules = 0)
	{
		$nCellulesReel = $nCellules - (isset($infosInvocation['n']) ? 1 : 0);
		parent::pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire, $nCellulesReel);
		if(isset($infosInvocation['n']))
			$sortie->finLigne('<td>'.$infosInvocation['n'].'</td>');
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
