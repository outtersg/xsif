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

require_once dirname(__FILE__).'/Modele/Diagramme/Tableau.php';

use Gui\Xsif\Modele\Diagramme\Tableau;

/** JSON en Tableau */
class Jet
{
	public $enTableau = false;
	public $enJson = true;
	
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
		if($this->enTableau)
		foreach($t as $numL => $l)
		{
			for($i = count($l); ++$i <= $nCols;)
				$t[$numL][] = null;
			$t[$numL][] = $this->v[$numL];
		}
		else
			foreach($t as $numL => $l)
			{
				$lc = '';
				foreach($l as $v)
					$lc .= isset($v) ? $v : "\t";
				if(isset($this->v[$numL]))
					$lc .= $this->v[$numL];
				$t[$numL] = $lc;
			}
		
		return $t;
	}
	
	public function _charger($niveau, $champ, $objet, $sépSuivant = '')
	{
		$l = [];
		for($i = $niveau; --$i >= 0;)
			$l[] = null;
		$descr = isset($champ) ? ($this->enJson ? '"'.$champ.'"' : $champ).': ' : '';
		
		if(is_object($objet) || is_array($objet))
		{
			if(is_array($objet)) /* À FAIRE: s'assurer que les clés sont séquentielles. */
			{
				$ouv = '['; $fer = ']'; $clés = false; $d = $objet;
			}
			else
			{
				$ouv = '{'; $fer = '}'; $clés = true; $d = get_object_vars($objet);
			}
			if($this->enJson && !count($d)) // Si on a un tableau vide, on ferme immédiatement.
				$ouv .= $fer.$sépSuivant;
			$l[] = $descr.$ouv;
			$this->c[] = $l;
			$this->v[] = null;
			$der = array_keys($d);
			$der = array_pop($der);
			foreach($d as $clé => $val)
				$this->_charger($niveau + 1, $clés ? $clé : null, $val, $clé != $der ? ',' : '');
			/* À FAIRE: en cas de tableau JSON lorsque !$this->enJson, on distingue mal le côté tableau: mieux vaudrait réintroduire quelque chose qui ressemble à un truc itérable. */
			if($this->enJson && count($d))
			{
				$l[count($l) - 1] = $fer.$sépSuivant;
				$this->c[] = $l;
				$this->v[] = null;
			}
		}
		else
		{
			$l[] = $descr;
			$this->c[] = $l;
			$this->v[] = $this->enJson ? json_encode($objet).$sépSuivant : $objet;
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
	switch($arg)
	{
		case '-t': $compo->enTableau = true; continue 2;
	}
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
