<?php

namespace Gui\Xsif\Modele\Diagramme;

#[\AllowDynamicProperties]
class Nœud
{
	/** Donnée. */
	public $d;
	public $prédécesseurs = [];
	
	public function __construct($donnée)
	{
		$this->d = $donnée;
	}
}

?>
