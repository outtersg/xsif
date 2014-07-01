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
	
	public function commencerBloc()
	{
		$this->_sortir('<table>'."\n");
		$this->_ligneOuverte = false;
		$this->_ligneEcrite = false; // A-t-on écrit le contenu de cette ligne du bloc (reste éventuellement les marges)?
		$this->_marges = array();
		$this->_numLigne = 0;
		$this->_contenuBloc = '';
	}
	
	protected function _ajouter($chaine)
	{
		if(!isset($this->_contenuBloc))
			$this->_sortir($chaine);
		else
			$this->_contenuBloc .= $chaine;
	}
	
	public function commencerMarge($chaine, $aGauche = false)
	{
		// Les marges sont pondues avant la ligne.
		if($this->_ligneEcrite) // Donc si on est en cours d'écriture pour une, c'est qu'on prépare la marge pour la suivante.
			$this->_finirLigne();
		$this->_commencerLigne();
		
		$this->_marges[] = array('contenu' => $chaine, 'ligne' => $this->_numLigne);
		
		if($aGauche)
			$this->_pondreMarge(count($this->_marges) - 1);
	}
	
	protected function _pondreMarge($numMarge)
	{
		$this->_ajouter('<td rowspan="');
		$this->_marges[$numMarge]['insert'] = strlen($this->_contenuBloc);
		$this->_ajouter('">'.htmlspecialchars($this->_marges[$numMarge]['contenu']).'</td>');
		unset($this->_marges[$numMarge]['contenu']);
	}
	
	public function finirMarge()
	{
		// On s'assure que le HTML a été déjà préparé, hein.
		if(isset($this->_marges[count($this->_marges) - 1]['contenu']))
			$this->_pondreMarge(count($this->_marges) - 1);
		
		$marge = array_pop($this->_marges);
		$nLignes = $this->_numLigne - $marge['ligne'] + 1;
		
		if($nLignes == 1) // rowspan="1", la mention est inutile.
			$decalage = - strlen(' rowspan=""');
		else
			$decalage = strlen($nLignes);
		foreach($this->_marges as & $autreMarge)
			if(isset($autreMarge['insert']) && $autreMarge['insert'] >= $marge['insert']) // Peut arriver si sur la même ligne on été posées une marge droite puis une gauche: la fermeture de la gauche intervient avant celle de la droite, décalant l'endroit où l'on inscrira le rowspan de la marge droite.
				$autreMarge['insert'] += $decalage;
		
		if($nLignes == 1)
			$this->_contenuBloc = substr($this->_contenuBloc, 0, $marge['insert'] + $decalage + 1).substr($this->_contenuBloc, $marge['insert'] + 1);
		else
			$this->_contenuBloc = substr($this->_contenuBloc, 0, $marge['insert']).$nLignes.substr($this->_contenuBloc, $marge['insert']);
	}
	
	public function ligne($chaine, $enTete = false)
	{
		$balise = $enTete ? 'th' : 'td';
		
		if($this->_ligneEcrite)
			$this->_finirLigne();
		$this->_commencerLigne();
		$this->_ajouter('<'.$balise.' colspan="@'.count($this->_marges).'">'.htmlspecialchars($chaine).'</'.$balise.'>');
		$this->_ligneEcrite = true;
	}
	
	protected function _commencerLigne()
	{
		if(!$this->_ligneOuverte)
		{
			$this->_ajouter('<tr>');
			++$this->_numLigne;
			$this->_ligneOuverte = true;
			$this->_ligneEcrite = false;
		}
	}
	
	protected function _finirLigne()
	{
		if($this->_ligneOuverte)
		{
			// On pond les marges droite pas encore écrite (en HTML, le td rowspan multiple est écrit avec la première ligne qu'il couvre).
			for($numMarge = count($this->_marges); --$numMarge >= 0 && isset($this->_marges[$numMarge]['contenu']);)
				$this->_pondreMarge($numMarge);
			$this->_ajouter('</tr>'."\n");
			$this->_ligneOuverte = false;
			$this->_ligneEcrite = false;
		}
	}
	
	public function finirBloc()
	{
		$this->_finirLigne();
		$this->_ajouter('</table>'."\n");
		$contenuBloc = $this->_contenuBloc;
		$this->_contenuBloc = null;
		
		// Les colspan du bloc sont maintenant recalculés en fonction de la ligne sur laquelle il y a le plus de marges (son colspan vaudra alors 1).
		
		preg_match_all('# colspan="@([0-9]*)"#', $contenuBloc, $r);
		$max = 0;
		foreach($r[1] as $num)
			if($num > $max)
				$max = $num;
		$this->_maxColspan = $max + 1;
		$contenuBloc = preg_replace_callback('# colspan="@([0-9]*)"#', array($this, '_remplColspan'), $contenuBloc);
		
		$this->_sortir($contenuBloc);
	}
	
	public function _remplColspan($res)
	{
		$nouveau = $this->_maxColspan - $res[1];
		return $nouveau == 1 ? '' : ' colspan="'.$nouveau.'"';
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
	
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire)
	{
		if(!isset($this->contenu))
			return;
		
		if(isset($infosInvocation['n']))
			$sortie->commencerMarge($infosInvocation['n']);
		foreach($this->contenu as $fils)
			$fils['t']->pondre($chemin.'.'.$fils['l'], $fils, $sortie, $pileResteAFaire);
		if(isset($infosInvocation['n']))
			$sortie->finirMarge();
	}
}

class Simple extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire)
	{
		if(isset($infosInvocation['n']))
			$sortie->commencerMarge($infosInvocation['n']);
		$sortie->ligne($infosInvocation['l']);
		if(isset($infosInvocation['n']))
			$sortie->finirMarge();
	}
}

class Complexe extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire)
	{
		$nomClasse = explode('#', $chemin, 2);
		$nomClasse = $nomClasse[1];
		
		// Si on est appelés dans le cadre d'un autre, on s'inscrit uniquement comme libellé dans celui-ci, et on se met en file d'attente pour la "vraie" ponte.
		if(isset($infosInvocation))
		{
			if(isset($infosInvocation['n']))
				$sortie->commencerMarge($infosInvocation['n']);
			$sortie->ligne($infosInvocation['l']);
			if(isset($infosInvocation['n']))
				$sortie->finirMarge();
			
			$nomBloc = isset($infosInvocation['classe']) ? $infosInvocation['classe'] : $chemin;
			if(!in_array($nomBloc, $pileResteAFaire))
				$pileResteAFaire[] = $nomBloc;
			if(!isset($this->_modele[$nomBloc]))
				$this->_modele[$nomBloc] = $infosInvocation['t'];
			return;
		}
		
		$sortie->commencerBloc();
		$sortie->ligne($nomClasse, true);
		foreach($this->contenu as $fils)
			$fils['t']->pondre($chemin.'.'.(isset($fils['l']) ? $fils['l'] : get_class($fils['t'])), $fils, $sortie, $pileResteAFaire);
		$sortie->finirBloc();
	}
}

class Sequence extends Type
{
}

class Variante extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire)
	{
		$sortie->commencerMarge('∈', true); // Essayer aussi les caractères 2261, 2263, 2999, 2E3D, FE19.
		parent::pondre($chemin, $infosInvocation, $sortie, & $pileResteAFaire);
		$sortie->finirMarge();
	}
}

class Groupe extends Type
{
}

$variante = new Variante;
$variante->contenu[] = array('t' => new Sequence);
$variante->contenu[0]['t']->contenu[] = array('l' => 'equipe', 't' => 'local#Equipe', 'n' => '+');
$variante->contenu[0]['t']->contenu[] = array('l' => 'equipeDeChefs', 't' => 'local#Equipe');

$typeRacine = new Complexe;
$typeRacine->contenu[] = array('t' => $variante);

$personne = new Groupe;
$personne->contenu[] = array('l' => 'titre', 't' => 'xsd#string', 'n' => '?');
$personne->contenu[] = array('l' => 'prenom', 't' => 'xsd#string');
$personne->contenu[] = array('l' => 'nom', 't' => 'xsd#string');

$typeEquipe = new Complexe;
$typeEquipe->contenu[] = array('t' => new Sequence);
$typeEquipe->contenu[0]['t']->contenu[] = array('l' => 'gusse', 't' => $personne, 'n' => '+');
$typeEquipe->contenu[0]['t']->contenu[] = array('l' => 'attribut', 't' => 'xsd#string', 'n' => '*');

$modele = array
(
	'local#Racine' => $typeRacine,
	'local#Equipe' => $typeEquipe,
);

$e = new Ecrivain($modele);
$e->ecrire('local#Racine');

?>
