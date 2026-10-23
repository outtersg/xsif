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

require_once dirname(__FILE__).'/Modele/Diagramme/Tableau.php';

$modèle =
[
	new Gui\Xsif\Modele\Diagramme\Tableau
	(
	[
	'Les oiseaux < les éléph' => 'idem',
	'ouille' => 'Là non plus',
	'Oups' => 'Oups',
	'et "pas plus haut que le verre"' => 'pour les guillemets',
	],
	),
	new Gui\Xsif\Modele\Diagramme\Tableau
	(
	[
		[ 'Les oiseaux <= éléphants', 'Un rien' ],
		[ 'Là non plus', 'Tout va bien' ],
		[ 'Oups', 'Dernière case' ],
	],
	),
];
$modèle[] = clone $modèle[0];
$modèle[2]->prédécesseurs[] = $modèle[1];

require_once dirname(__FILE__).'/decompo/drawio/DrawIo.php';

$e = new DrawIo();
$e->police('Lato', DrawIo::P_GOOGLE);
$e->pondreFichier($modèle);

?>
