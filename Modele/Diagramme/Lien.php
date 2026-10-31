<?php

/* À FAIRE: les réseaux, à plusieurs sources ou destinations. */
class Lien
{
	/* Indices pour la variante tableau des Lien */
	const DE = 0;
	const VERS = 1;
	const STYLE = 2;
	const LIBELLÉ = 3;
	
	/** Source du lien. Nœud, ou [ Nœud, <numéro de sortie du nœud> ] */
	public $de;
	/** Destination du lien. Nœud, ou [ Nœud, <numéro d'entrée dans le nœud> ] */
	public $vers;
	/** Suite de mots, façon classes CSS. */
	public $style;
	/** Texte libre. */
	public $libellé;
}

?>
