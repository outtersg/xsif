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

class Simple
{
	public $marge = 1;
	public $tailleParDéfaut = 3;
	
	public function disposer($modèle, $client)
	{
		$this->_client = $client;
		/* À FAIRE: si $client est null, récupérer la classe de notre appelant. */
		
		$this->_x = $this->_y = 0;
		$this->_lignes = [];
		
		/* Deux passes: un placement sur une grille, puis un calage en unités graphiques. */
		
		foreach($modèle as $n)
			$this->placer($n);
		
		foreach($modèle as $n)
			$this->caler($n);
		
		/* À FAIRE: calcul et renvoi du plan de travail résultant. */
	}
	
	public function placer($nœud)
	{
		if(isset($nœud->pos[$this->_client]['fait'])) return $nœud->pos[$this->_client]['fait'] < 0 ? null : $nœud->pos[$this->_client];
		
		// On se marque en cours de constitution, pour éviter les boucles infinies.
		$nœud->pos[$this->_client]['fait'] = -1;
		
		$col = 0; // Colonne la plus à gauche possible.
		$lignes = []; // Lignes de nos prédécesseurs: on essaiera de se mettre sur l'une d'elles.
		foreach($nœud->prédécesseurs as $p)
		{
			if(!($pp = $this->placer($p))) continue; // Si notre prédécesseur ne renvoie aucun placement, c'est qu'il est lui-même en cours de placement.
			if($col < $pp['col'] + 1) $col = $pp['col'] + 1;
			$lignes[$pp['ligne']] = true;
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
		
		$nœud->pos[$this->_client]['fait'] = 1;
		$nœud->pos[$this->_client]['col'] = $col;
		$nœud->pos[$this->_client]['ligne'] = $lIdéale;
		
		return $nœud->pos[$this->_client];
	}
	
	public function caler($nœud)
	{
		/* À FAIRE */
	}
	
	/** Quel module est en train de nous demander une mise en page? */
	protected $_client;
	
	protected $_x, $_y;
	/** Ici on colle les nœuds placés par ligne: ça nous permet de savoir où se calera le suivant. */
	protected $_lignes;
}

?>
