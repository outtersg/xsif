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

/* JEDI: Jsonpath En Draw.Io
/* À FAIRE: différentes façons de préciser la transfo:
 * 1. JSONPath
 * 2. Un JSON d'exemple mettant dans chaque attribut le chemin de sa source
 * 3. Deux JSON, les mentions uniques retrouvées de part et d'autre indiquent quoi est reversé où.
 */
/* À FAIRE: un format vrai JSON, en ajoutant les } et ] fermantes et les , qui permettent de copier-coller le contenu du tableau pour avoir un JSON complet valide. */

require_once dirname(__FILE__).'/Modele/Diagramme/Tableau.php';

use Gui\Xsif\Modele\Diagramme\Tableau;

/** JSON en Tableau */
class Jet
{
	public function chargerFichier($chemin)
	{
		return $this->charger(json_decode(file_get_contents($chemin)));
	}
	
	public function charger($objet)
	{
		$this->c = [];
		$this->v = [];
		$this->_charger(0, null, $objet);
		
		$nCols = 0;
		$t = $this->c;
		foreach($t as $l)
			if(count($l) > $nCols)
				$nCols = count($l);
		foreach($t as $numL => $l)
		{
			for($i = count($l); ++$i <= $nCols;)
				$t[$numL][] = null;
			$t[$numL][] = $this->v[$numL];
		}
		
		return $t;
	}
	
	public function _charger($niveau, $champ, $objet)
	{
		/* À FAIRE: un mode déguillemetté, sur les noms de champ et les v[]. */
		$l = [];
		for($i = $niveau; --$i >= 0;)
			$l[] = null;
		$descr = isset($champ) ? '"'.$champ.'": ' : '';
		
		if(is_object($objet))
		{
			$l[] = $descr.'{';
			$this->c[] = $l;
			$this->v[] = null;
			foreach(get_object_vars($objet) as $clé => $val)
				$this->_charger($niveau + 1, $clé, $val);
		}
		else if(is_array($objet))
		{
			$l[] = $descr.'[';
			$this->c[] = $l;
			$this->v[] = null;
			foreach($objet as $clé => $val)
				$this->_charger($niveau + 1, null, $val);
		}
		else
		{
			$l[] = $descr;
			$this->c[] = $l;
			$this->v[] = json_encode($objet);
		}
	}
	
	// Champs et valeurs.
	public $c, $v;
}

$compo = new Jet();
array_shift($argv);
$tout = [];
foreach($argv as $arg)
{
	$t0 = $compo->chargerFichier($arg);
$t0 = new Tableau($t0);
$t0->style = 'd';
	$tout[] = $t0;
}

require_once dirname(__FILE__).'/decompo/drawio/DrawIo.php';

$e = new DrawIo();
$e->police('Lato', DrawIo::P_GOOGLE);
$e->pondreFichier($tout);

?>
