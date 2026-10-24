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

namespace Gui\Xsif\MiseEnPage;

/* À FAIRE: une MiseEnPage qui passe par GraphViz dont c'est le boulot? */
/* À FAIRE: distinguer x et y d'axe principal et axe secondaire, pour permettre des graphes haut - bas plutôt que gauche - droite. */

class Simple
{
	public $marge = 1;
	public $tailleParDéfaut = 3;
	
	public function disposer($modèle, $client = null)
	{
		if(!isset($this->_client) || $client)
		{
			$marqueBlanche = isset($this->_client) ? $this->_client : false;
			$this->_client = isset($client) ? $client : $this->_idClient();
		}
		
		$this->_x = $this->_y = 0;
		$this->_lignes = [];
		
		/* Deux passes: un placement sur une grille, puis un calage en unités graphiques. */
		
		foreach($modèle as $n)
			$this->placer($n);
		
		$this->caler();
		
		/* À FAIRE: calcul et renvoi du plan de travail résultant. */
		
		if($marqueBlanche)
			if($marqueBlanche === false)
			unset($this->_client);
			else
				$this->_client = $marqueBlanche;
	}
	
	/**
	 * Placement relatif d'un nœud.
	 * 
	 * @return stdClass|Nœud La position avec ses membres .ligne et .col renseignés s'ils ont pu être calculés, sinon un Nœud en cours de calcul qui nous bloque en récursivité.
	 */
	public function placer($nœud)
	{
		if(isset($this->pos($nœud)->fait)) return $this->pos($nœud)->fait < 0 ? $nœud : $this->pos($nœud);
		
		// On se marque en cours de constitution, pour éviter les boucles infinies.
		$this->pos($nœud)->fait = -1;
		
		$col = 0; // Colonne la plus à gauche possible.
		$lignes = []; // Lignes de nos prédécesseurs: on essaiera de se mettre sur l'une d'elles.
		foreach($nœud->prédécesseurs as $p)
		{
			// Cassage de récursivité:
			// Si en guise de placement notre prédécesseur renvoie un Nœud plutôt qu'un placement, c'est qu'il est bloqué par le nœud en question.
			// - si c'est le nœud qu'on est en train de placer, alors c'est que nous sommes le premier d'une boucle de dépendances: on se place en ignorant ce prédécesseur qui est aussi notre successeur.
			// - sinon c'est que nous sommes éléments d'une boucle de dépendances, mais que nous n'en sommes pas la tête: on sort.
			if(!($pp = $this->placer($p)) || $pp === $nœud) continue;
			if(!isset($pp->fait) && isset($pp->pos)) { unset($this->pos($nœud)->fait); return $pp; } // Un Nœud nous bloque, mais ce n'est pas nous. On le remonte, et pour notre part on attend d'être rappelé ultérieurement.
			
			// Là normalement $pp est la position du prédécesseur.
			if($col < $pp->col + 1) $col = $pp->col + 1;
			$lignes[$pp->ligne] = true;
		}
		
		// Si personne ne nous veut à sa droite, on commence une nouvelle ligne!
		if(!count($lignes))
		{
			$lignes[count($this->_lignes)] = true;
			$this->_lignes[] = [];
		}
		
		// Parmi toutes les lignes possibles on prend celle où on serait le plus à gauche.
		// Sauf évidemment si cette position était inférieure à la contrainte posée par nos prédécesseurs: inutile de bouffer des places sur une ligne en laissant des trous, laissons-la à d'autres.
		$lIdéale = -1;
		foreach($lignes as $l => $trou)
		{
			// À choisir entre créer un trou sur une ligne sous-peuplée, et nous entasser sur une déjà trop longue, on prend la première solution. Donc pénalité aux lignes plus longues que ce qu'on voudrait.
			$diff = count($this->_lignes[$l]) - $col + 0.1;
			if($lIdéale < 0 || $diff < $idéal)
			{
				$idéal = $diff;
				$lIdéale = $l;
			}
		}
		/* À FAIRE: privilégier la ligne sur laquelle nous serons juste à droite d'un prédécesseur. */
		
		// Changement de colonne si on n'a pas obtenu l'idéal?
		if($col < count($this->_lignes[$lIdéale]))
			$col = count($this->_lignes[$lIdéale]);
		
		$this->pos($nœud)->fait = 1;
		$this->pos($nœud)->col = $col;
		$this->pos($nœud)->ligne = $lIdéale;
		
		// On matérialise les trous, pour simplifier le parcours plus tard (pouvoir reposer sur les count() pour savoir combien de colonnes par ligne).
		while(count($this->_lignes[$lIdéale]) < $col - 1)
			$this->_lignes[$lIdéale][] = null;
		$this->_lignes[$lIdéale][] = $nœud;
		
		return $nœud->pos[$this->_client];
	}
	
	public function caler()
	{
		// Pour les ordonnées, pas compliqué, chaque ligne son couloir.
		// Tout au plus aligne-t-on verticalement ceux qui ont donné une taille.
		
		$nCols = 0;
		$y = $this->marge;
		foreach($this->_lignes as $l)
		{
			$hMax = 0;
			foreach($l as $n)
				if(isset($this->pos($n)->h) && $this->pos($n)->h > $hMax)
					$hMax = $this->pos($n)->h;
			if(!$hMax) $hMax = $this->tailleParDéfaut;
			foreach($l as $n)
				$this->pos($n)->y = $y + (isset($this->pos($n)->h) ? ($hMax - $this->pos($n)->h) / 2 : 0);
			$y += $hMax + $this->marge;
			
			if($nCols < count($l)) $nCols = count($l);
		}
		
		// Pour les abcisses, on tasse au maximum à gauche, tant que ça ne bouscule ni celui à notre gauche sur notre ligne, ni nos prédécesseurs des autres lignes.
		// On doit donc avancer colonne par colonne, et non ligne par ligne, car notre bloc l 2 c 3 peut être dépendant d'un l 5 c 4.
		
		for($c = -1; ++$c < $nCols;)
			foreach($this->_lignes as $l)
				if(isset($l[$c]))
				{
					// Qu'est-ce qui est avant nous?
					$avant = $l[$c]->prédécesseurs;
					for($cp = $c; --$cp >= 0;)
						if(isset($l[$cp]))
						{
							$avant[] = $l[$cp];
							break;
						}
					// Recherche de l'abcisse max.
					$xMax = 0;
					foreach($avant as $nav)
					{
						if(!isset($this->pos($nav)->x)) continue; // Un prédécesseur pas encore placé, car boucle entre nous. Il doit être sur une colonne postérieure.
						if(($x = $this->pos($nav)->x + (isset($this->pos($nav)->l) ? $this->pos($nav)->l : $this->tailleParDéfaut)) > $xMax) $xMax = $x;
					}
					$this->pos($l[$c])->x = $xMax + $this->marge; // On ajoute la marge: même sur la première colonne, ça aère, avec de l'espace à gauche.
				}
		
		/* À FAIRE: une fois la ligne constituée, on peut rejouer sur les espaces pour répartir régulièrement les blocs. */
	}
	
	/**
	 * Renvoie les coordonnées du nœud pour le client actuel.
	 */
	public function pos($nœud)
	{
		$idClient = $this->_idClient();
		
		if(!isset($nœud->pos[$idClient]))
			$nœud->pos[$idClient] = new \stdClass;
		return $nœud->pos[$idClient];
	}
	
	/**
	 * Récupère un identifiant pour le client actuel (l'appelant de notre fonction publique qui appelle cette fonction privée).
	 * L'identifiant est son nom de classe.
	 */
	protected function _idClient()
	{
		if(isset($this->_client))
			return $this->_client;
		
		$pile = debug_backtrace(1);
		return $pile[2]['class'];
	}
	
	/** Quel module est en train de nous demander une mise en page? */
	protected $_client;
	
	protected $_x, $_y;
	/** Ici on colle les nœuds placés par ligne: ça nous permet de savoir où se calera le suivant. */
	protected $_lignes;
}

?>
