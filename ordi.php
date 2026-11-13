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
/* À FAIRE: --contenu pour fournir un pseudo-contenu pour illustrer les structures en arborescence. */

require_once dirname(__FILE__).'/compo/Talend.php';

$fichiers = [];
$optionsNœuds = [];
array_shift($argv);
foreach($argv as $pos => $arg)
	if(preg_match('/^--(xml|json)=(.*)/', $arg, $rex))
		foreach(explode(',', $rex[2]) as $nœud)
			$optionsNœuds[$nœud][$rex[1]] = true;
	else
		$fichiers[] = $arg;

$compo = new Talend();
$modèle = $compo->charger($fichiers, $optionsNœuds);

require_once dirname(__FILE__).'/decompo/drawio/DrawIo.php';

$e = new DrawIo();
$e->police('Lato', DrawIo::P_GOOGLE);
$e->pondreFichier($modèle);

?>
