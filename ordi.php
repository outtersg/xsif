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

/* ordi
 * Organigrammes Représentés sous Draw.Io
 */

class Sortie
{
	public function _sortir($chaine)
	{
		if(isset($this->_s))
			fwrite($this->_s, $chaine);
		else
			echo $chaine;
	}
	
	protected $_s;
}

class Écrivain
{
	public function __construct($modèle)
	{
		$this->d = $modèle;
	}
	
	public function écrire()
	{
		include dirname(__FILE__).'/decompo/drawio/Tableau.php';
	}
	
	/** Dernier identifiant de nœud attribué. */
	protected $_id = 0;
	/** Données à pondre. */
	protected $d;
}

$corresp = new \stdClass;
$corresp->corresp =
[
	'Les oiseaux < les éléph' => 'idem',
	'ouille' => 'Là non plus',
	'Oups' => 'Oups',
	'et "pas plus haut que le verre"' => 'pour les guillemets',
];
$modèle = [ $corresp ];

$e = new Écrivain($modèle);
$e->écrire();

?>
