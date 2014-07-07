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

/* xsif
 * Représentation en diagrammes d'un .xsd
 * xs comme XML Schema, et if pour exprimer la représentation en arbre, de façon élégante et compacte (parce que l'if est, de tous les arbres que je connaisse, celui qui possède le nom le plus concis, et aux couleurs raffinées vert sombre et rouge délicat, ainsi que marron et ocre pour le tronc. Et puis il y en a dans le jardin de mes parents et j'aime bien).
 */

class SortieHtml
{
	protected $_attrId = 'id';
	protected $_attrsTable = '';
	protected $_attrsColDroite = '';
	
	public function __construct($chemin = null)
	{
		$this->_chemin = $chemin;
	}
	
	public function commencer()
	{
		if(isset($this->_chemin))
			$this->_s = fopen($this->_chemin, 'w');
		
		$this->_lignes = array();
		$this->_blocs = array();
		
		$this->_commencer();
	}
	
	protected function _commencer()
	{
		$this->_sortir('<?xml version="1" encoding="UTF-8"?>
		<html>
			<head>
				<title>Diagramme</title>
				<style type="text/css">
					table, td
					{
						border-collapse: collapse;
					}
					td
					{
						border: 1px solid #7F3F00;
						padding-left: 0.5em;
						padding-right: 0.5em;
					}
					table
					{
						border: 3px double #7F3F00;
						background: #FFFFDF;
					}
					td i
					{
						padding-left: 0.5em;
						font-size: 80%;
						color: #BF5F00;
					}
				</style>
			</head>
		<body>'."\n");
	}
	
	public function commencerBloc($nom, $identifiant = null)
	{
		$this->_blocs[$this->_blocActuel = isset($identifiant) ? $identifiant : $nom] = $numBloc = count($this->_blocs); // Le nom permettra par la suite de retrouver le numéro.
		
		$this->_sortir('<table'.$this->_attrsTable.'>'."\n");
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
	
	public function ligne($chaine, $enTete = false, $supplement = null)
	{
		if($this->_ligneEcrite)
			$this->_finirLigne();
		$this->_commencerLigne();
		if(!isset($this->_premiereLigneBlocs[$this->_blocActuel]))
		{
			$id = count($this->_lignes);
			$this->_premiereLigneBlocs[$this->_blocActuel] = $id;
			$this->_lignes[count($this->_lignes)] = $this->_blocActuel;
			$chaineId = ' '.$this->_attrId.'="e'.$id.'"';
		}
		else
			$chaineId = '';
		$this->_ligne($chaine, $chaineId, $supplement);
		$this->_ligneEcrite = true;
		$this->_lignes[count($this->_lignes)] = $this->_blocActuel; // Le numéro permettra de retrouver le bloc.
	}
	
	public function _ligne($chaine, $chaineIdSiEntete, $supplement = null)
	{
		$balise = $chaineIdSiEntete ? 'th' : 'td';
		$this->_ajouter('<'.$balise.' colspan="@'.count($this->_marges).'"'.$chaineIdSiEntete.'>'.htmlspecialchars($chaine).($supplement ? '<i>'.htmlspecialchars($supplement).'</i>' : '').'</'.$balise.'>');
	}
	
	public function lien($versBloc)
	{
		$this->_liens[] = array(count($this->_lignes) - 1, $versBloc); // Ligne actuelle.
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
 			$this->_ajouter('<td '.$this->_attrId.'="l'.(count($this->_lignes) - 1).'"'.$this->_attrsColDroite.'></td>'); // L'ancre est ajoutée comme dernière colonne invisible de la table, afin de toujours se trouver à droite, même des marges droite.
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
	
	protected function _finir()
	{
		$this->_sortir('</body></html>');
	}
	
	public function finir()
	{
		$this->_finir();
		if(isset($this->_s))
			fclose($this->_s);
	}
	
	public function _sortir($chaine)
	{
		if(isset($this->_s))
			fwrite($this->_s, $chaine);
		else
		echo $chaine;
	}
}

class SortieGraphviz extends SortieHtml
{
	protected $_attrId = 'port';
	protected $_attrsTable = ' cellborder="1" cellspacing="0" border="0" bgcolor="#FFFFDF" color="#7F3F00"';
	protected $_attrsColDroite = ' width="0" cellpadding="0" border="0"';
	
	protected function _commencer()
	{
		$this->_sortir('
digraph Schema
{
	rankdir = "LR";
	edge [ fontname="Lato" fontsize=12 ];
	node [ shape=none fontname="Lato" fontsize=12 ];
');
	}
	
	public function commencerBloc($nom, $identifiant = null)
	{
		$nomBloc = 'b'.count($this->_blocs);
		$this->_sortir($nomBloc.' [ label=< ');
		parent::commencerBloc($nom, $identifiant);
	}
	
	public function _ligne($chaine, $chaineIdSiEntete, $supplement = null)
	{
		$chaineTitre = htmlspecialchars($chaine);
		if($chaineIdSiEntete)
		{
			$chaineTitre = '<font color="#FFFFFF"><b>&nbsp;&nbsp;&nbsp;'.$chaineTitre.'&nbsp;&nbsp;&nbsp;</b></font>';
			$attrsTd = $chaineIdSiEntete.' bgcolor="#7F3F00"';
		}
		else
			$attrsTd = ' align="left"';
		$this->_ajouter('<td colspan="@'.count($this->_marges).'"'.$attrsTd.'>'.$chaineTitre.($supplement ? '&nbsp;<font point-size="9.6" color="#BF5F00"><i>'.htmlspecialchars($supplement).'</i></font>' : '').'</td>');
	}
	
	public function finirBloc()
	{
		parent::finirBloc();
		$this->_sortir('> ]'."\n");
	}
	
	protected function _finir()
	{
		if(isset($this->_liens))
		foreach($this->_liens as $lien)
			if(isset($this->_premiereLigneBlocs[$lien[1]])) // Certains blocs ont pu être occultés par un filtre.
			$this->_sortir('b'.$this->_blocs[$this->_lignes[$lien[0]]].':l'.$lien[0].':e -> b'.$this->_blocs[$lien[1]].':e'.$this->_premiereLigneBlocs[$lien[1]].':w'."\n"); // Toujours d'une ligne vers un bloc.
		$this->_sortir('}');
	}
}

class Ecrivain
{
	public function __construct($modele)
	{
		$this->_modele = $modele;
	}
	
	public function filtre($filtre)
	{
		if(0 + $filtre) // Nombre de niveaux à afficher.
			$this->_niveauMax = 0 + $filtre;
	}
	
	public function ecrire($typeRacine = null, $detaillerLesSimples = false, $cheminSortie = null)
	{
		if(!isset($cheminSortie))
			$this->_sortie = new SortieGraphviz;
		else
			$this->_sortie = new SortieGraphviz($cheminSortie);
		
		$this->detaillerLesSimples = $detaillerLesSimples;
		
		if(!isset($typeRacine))
		{
			$nomTypes = array_keys($this->_modele);
			$typeRacine = $nomTypes[0];
		}
		if(strpos($typeRacine, '#') === false)
		{
			$nomTypes = array_keys($this->_modele);
			// On cherche dans l'espace de nom par défaut (espace de nom du premier élément).
			$premierType = explode('#', $nomTypes[0], 2);
			$typeRacineEssaye = $premierType[0].'#'.$typeRacine;
			// Sinon on parcourt tous les types: avec un peu de chance un seul espace de nom embarque un tel type, en ce cas, point d'ambiguïté, donc on le choisira.
			if(isset($this->_modele[$typeRacineEssaye]))
				$typeRacine = $typeRacineEssaye;
			else
			{
				$typesRacineEssayes = array();
				foreach($nomTypes as $nomType)
				{
					$typeEssaye = explode('#', $nomType, 2);
					if($typeRacine == $typeEssaye[1])
						$typesRacineEssayes[$nomType] = true;
				}
				$typesRacineEssayes = array_keys($typesRacineEssayes);
				switch(count($typesRacineEssayes))
				{
					case 0: break;
					case 1: $typeRacine = $typesRacineEssayes[0]; break;
					default: throw new Exception('Plusieurs types portent le nom '.$typeRacine.': '.implode(', ', $typesRacineEssayes)); break;
				}
			}
		}
		
		if(!isset($this->_modele[$typeRacine]))
			throw new Exception('Type inexistant dans le modèle: '.$typeRacine);
		
		$this->_niveauActuel = 0;
		
		$this->_sortie->commencer();
		$this->_resoudre($this->_modele[$typeRacine]);
		$this->resteAFaire = array($typeRacine);
		$pondus = 0;
		while(count($this->resteAFaire) > $pondus)
		{
			$this->_modele[$this->resteAFaire[$pondus]]->pondre($this->resteAFaire[$pondus], null, $this->_sortie, $this);
			++$pondus;
		}
		$this->_sortie->finir();
	}
	
	public function niveau($idBloc, $niveau = null)
	{
		// Fixation.
		if(isset($niveau))
		{
			if(isset($this->_niveaux[$idBloc]) && $this->_niveaux[$idBloc] < $niveau)
				return;
			$this->_niveaux[$idBloc] = $niveau;
			return;
		}
		// Obtention.
		if(!isset($this->_niveaux[$idBloc])) // Si l'on ne possède pas encore d'indication de niveau pour un bloc, c'est qu'il n'a jamais été appelé depuis quelqu'autre, donc est probablement racine.
			$this->_niveaux[$idBloc] = 0;
		return $this->_niveaux[$idBloc];
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
	
	public function pondre($chemin, $infosInvocation, $sortie, $registre)
	{
		if(!isset($infosInvocation['classe']))
			$classeSimple = null;
		else
		{
			$classeNS = explode('#', $infosInvocation['classe'], 2);
			$classeSimple = array_pop($classeNS);
		}
		if(isset($infosInvocation['n']))
			$sortie->commencerMarge($infosInvocation['n']);
		$sortie->ligne($infosInvocation['l'], false, $classeSimple.(isset($infosInvocation['text']) ? $infosInvocation['text'] : '')); // text: Type.EXTension
		if(isset($infosInvocation['n']))
			$sortie->finirMarge();
	}
}

class Simple extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, $registre)
	{
		if($registre->detaillerLesSimples && isset($this->contenu) && count($this->contenu) == 1 && isset($this->contenu[0]['t']) && $this->contenu[0]['t'] instanceof Simple)
		{
			$nouvelleInvocation = array_intersect_key($this->contenu[0], array('t' => 1, 'classe' => 1)) + $infosInvocation + $this->contenu[0];
			return $this->contenu[0]['t']->pondre($chemin, $nouvelleInvocation, $sortie, $registre);
		}
		return parent::pondre($chemin, $infosInvocation, $sortie, $registre);
	}
}

class Complexe extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, $registre)
	{
		$nomClasse = explode('#', $chemin, 2);
		$nomClasse = $nomClasse[1];
		
		// Si on est utilisé comme extension d'une autre classe, on lui fournit notre contenu comme s'il était sien.
		if(isset($infosInvocation) && isset($infosInvocation['commeExtension']))
		{
			$pseudoListe = new Liste;
			$pseudoListe->contenu = $this->contenu;
			return $pseudoListe->pondre($chemin, $infosInvocation, $sortie, $registre);
		}
		// Si on est appelés dans le cadre d'un autre, on s'inscrit uniquement comme libellé dans celui-ci, et on se met en file d'attente pour la "vraie" ponte.
		if(isset($infosInvocation))
		{
			parent::pondre($chemin, $infosInvocation, $sortie, $registre);
			
			$nomBloc = isset($infosInvocation['classe']) ? $infosInvocation['classe'] : $chemin;
			if(!in_array($nomBloc, $registre->resteAFaire))
				$registre->resteAFaire[] = $nomBloc;
			if(!isset($registre->_modele[$nomBloc]))
				$registre->_modele[$nomBloc] = $infosInvocation['t'];
			$registre->niveau($nomBloc, $registre->_niveauActuel + 1);
			$sortie->lien($nomBloc);
			return;
		}
		
		if(isset($registre->_niveauMax) && $registre->niveau($chemin) >= $registre->_niveauMax)
			return;
		
		$niveauActuel = $registre->_niveauActuel;
		$registre->_niveauActuel = $registre->niveau($chemin);
		
		$sortie->commencerBloc($nomClasse, $chemin);
		$sortie->ligne($nomClasse, true);
		$pseudoListe = new Liste;
		$pseudoListe->contenu = $this->contenu;
		$pseudoListe->pondre($chemin, array(), $sortie, $registre);
		$sortie->finirBloc();
		
		$registre->_niveauActuel = $niveauActuel;
	}
}

class Liste extends Type
{
	public function pondre($chemin, $infosInvocation, $sortie, $registre)
	{
		if(!isset($this->contenu))
			return;
		
		if(isset($infosInvocation['n']))
			$sortie->commencerMarge($infosInvocation['n']);
		foreach($this->contenu as $fils)
			$fils['t']->pondre($chemin.'.'.(isset($fils['l']) ? $fils['l'] : get_class($fils['t'])), $fils, $sortie, $registre);
		if(isset($infosInvocation['n']))
			$sortie->finirMarge();
	}
}

class Sequence extends Liste
{
}

class Variante extends Liste
{
	public function pondre($chemin, $infosInvocation, $sortie, $registre)
	{
		$sortie->commencerMarge('∈', true); // Essayer aussi les caractères 2261, 2263, 2999, 2E3D, FE19.
		parent::pondre($chemin, $infosInvocation, $sortie, $registre);
		$sortie->finirMarge();
	}
}

class Groupe extends Liste
{
}

/*
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
*/

require_once dirname(__FILE__).'/chargeur.php';

$sources = array();
$detailSimples = false;
$niveaux = null;
for($i = 0; ++$i < count($argv);)
	switch($argv[$i])
	{
		case '-n': $niveaux = $argv[++$i]; break;
		case '-r': $racines[] = $argv[++$i]; break;
		case '-ds': $detailSimples = true; break;
		default: $sources[] = $argv[$i]; break;
	}

$c = new Chargeur;
foreach($sources as $source)
$modele = $c->charge($source);

$e = new Ecrivain($modele);
if(isset($niveaux))
	$e->filtre($niveaux);
if(!isset($racines))
	$e->ecrire(null, $detailSimples);
else foreach($racines as $racine)
	$e->ecrire($racine, $detailSimples, count($racines) > 1 ? dirname($source).'/'.preg_replace('/[^#]*#/', '', $racine).'.dot' : null);

?>
