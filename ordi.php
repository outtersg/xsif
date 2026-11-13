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

/* À FAIRE: auto-détection du type d'entrée (ou -t <type> pour aider). */
/* À FAIRE: --xml (sans =nœud) pour demander un format par défaut en XML (et --struct ou --tableau pour revenir au format tableau). */

require_once dirname(__FILE__).'/compo/Talend.php';

function exprPseudoContenu($unSurDeux)
{
	$r = [];
	
	for($i = -2; ($i += 2) < count($unSurDeux);)
	{
		$expr = $unSurDeux[$i];
		$val = $unSurDeux[$i + 1];
		$expr = strtr($expr, [ '*' => '[^/]*' ]);
		$expr = '#(?:^|/)'.$expr.'(?:/|$)#';
		$r[$expr] = $val;
	}
	
	return $r;
}

$fichiers = [];
$optionsNœuds = [];
array_shift($argv);
foreach($argv as $pos => $arg)
	if(preg_match('/^--(xml|json)=(.*)/', $arg, $rex))
		foreach(explode(',', $rex[2]) as $nœud)
			$optionsNœuds[$nœud][$rex[1]] = true;
	else if(preg_match('/^--contenu(\W)/', $arg, $rex)) // Le caractère séparant --contenu de la suite sera le délimiteur
	{
		if(!isset($optionsNœuds['_pseudoContenu']))
			$optionsNœuds['_pseudoContenu'] = [];
		$optionsNœuds['_pseudoContenu'] += exprPseudoContenu(explode($rex[1], substr($arg, strlen($rex[0]))));
	}
	else
		$fichiers[] = $arg;

$compo = new Talend();
$modèle = $compo->charger($fichiers, $optionsNœuds);

require_once dirname(__FILE__).'/decompo/drawio/DrawIo.php';

$e = new DrawIo();
$e->police('Lato', DrawIo::P_GOOGLE);
$e->pondreFichier($modèle);

?>
