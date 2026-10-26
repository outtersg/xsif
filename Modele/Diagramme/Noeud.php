<?php

namespace Gui\Xsif\Modele\Diagramme;

#[\AllowDynamicProperties]
class Nœud
{
	/** Donnée. */
	public $d;
	public $prédécesseurs = [];
	
	/** Coordonnées. Chaque module est libre de gérer comme il veut tout le fatras qu'il met à une clé qui lui est propre. */
	public $pos = [];
	
	public function __construct($titre = null, $donnée = null)
	{
		if(!is_string($titre) && !isset($donnée)) { $donnée = $titre; $titre = null; }
		$this->titre = $titre;
		$this->d = $donnée;
	}
}

?>
