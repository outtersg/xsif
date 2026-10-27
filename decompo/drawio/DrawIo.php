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

/* À FAIRE?: font en inherit? */

class DrawIo
{
	const P_STANDARD = 0;
	const P_WEB = 1;
	const P_GOOGLE = 2;
	
	/**
	 * Stylage par défaut, sous forme d'un tableau { style: { classe: { propriété: … } }
	 */
	public $styles =
	[
		'' =>
		[
			'' =>
			[
				'strokeColor' => '#000000',
			],
			'Tableau' =>
			[
				'fillColor' => '#dae8fc',
				'strokeColor' => '#6c8ebf',
				'rounded' => 1,
				'roundedPerimeter' => 1,
			],
		],
		/* Attribut: pas un nœud complet. */
		'attr' =>
		[
			'Lien' =>
			[
				'strokeColor' => '#bfbfbf',
				// Quand on colle une palanquée de liens depuis les attributs d'un nœud, ils vont avoir tendance à se chevaucher. On atténue ce chevauchement en optant pour des courbes plutôt que des angles droits.
				'curved' => 1,
				'curveGeometry' => 1,
			],
		],
		/* Donnée brute, et non flux de travail. */
		'd' =>
		[
			'Tableau' =>
			[
				'fillColor' => 'white',
				'strokeColor' => '#000000',
				'rounded' => false,
				'roundedPerimeter' => false,
			],
		],
	];
	
	protected $police;
	/** Données à pondre. */
	protected $d;
	
	/** Dernier identifiant de nœud attribué. */
	protected $_id;
	/** Placeur */
	protected $_p;
	/** Sortie */
	protected $_s;
	
	public function police($police, $type = self::P_STANDARD)
	{
		switch($type)
		{
			case self::P_GOOGLE: $this->police = 'fontFamily='.urlencode($police).';fontSource=https%3A%2F%2Ffonts.googleapis.com%2Fcss%3Ffamily%3D'.urlencode($police).';'; break;
		}
	}
	
	public function pondreFichier($modèle)
	{
		$this->d = $modèle;
		
		require_once dirname(__FILE__).'/../../MiseEnPage/Simple.php';
		$placeur = new Gui\Xsif\MiseEnPage\Simple();
		$placeur->marge = 40;
		$placeur->tailleParDéfaut = 100;
		$this->_p = $placeur;
		
		$this->entrerFichier();
		$this->entrerDessin();
		$this->_id = 1;
		
		foreach($modèle as $bloc)
		{
			$fonction = preg_split('/[\\\\_]/', get_class($bloc));
			/* À FAIRE: un cache d'après les noms de fonction ici présents. */
			while(count($fonction))
			{
				$f = 'calc'.implode('', $fonction);
				if(method_exists($this, $f))
				{
					$this->$f($bloc);
					break;
				}
				array_shift($fonction);
			}
			if(!count($fonction))
					fprintf(STDERR, "# Je ne sais pas traiter les blocs de type %s\n", get_class($bloc));
			}
		
		$tailleDessin = $placeur->disposer($modèle);
		
		foreach($modèle as $bloc)
			if(isset($this->_p->pos($bloc)->aff))
				call_user_func($this->_p->pos($bloc)->aff, $bloc);
		
		foreach($modèle as $bloc)
		{
			foreach($bloc->prédécesseurs as $p)
				$this->affLien($p, $bloc);
			if(isset($bloc->depuis))
				foreach($bloc->depuis as $depuis)
				{
					$vers = $bloc;
					$style = null;
					if(is_array($depuis))
					{
						$de = $depuis[0];
						if(isset($depuis[1]))
							$de = [ $de, $depuis[1] ];
						if(isset($depuis[2]))
							$vers = [ $vers, $depuis[2] ];
						if(isset($depuis[3]))
							$style = $depuis[3];
					}
					else
						$de = $depuis;
					$this->affLien($de, $vers, $style);
				}
		}
		
		$this->sortirDessin();
		$this->sortirFichier();
	}
	
	public function _sortir($chaine)
	{
		if(isset($this->_s))
			fwrite($this->_s, $chaine);
		else
			echo $chaine;
	}
	
	/*- Fichier --------------------------------------------------------------*/
	
	public function entrerFichier()
	{
		$this->_sortir(<<<TERMINE
			<mxfile host="app.diagrams.net">
			  <diagram name="Page-1" id="hgOAppBo4JsTc-_X_mI3">
		TERMINE);
	}
	
	public function sortirFichier()
	{
		$this->_sortir(<<<TERMINE
			  </diagram>
			</mxfile>
		TERMINE);
	}
	
	/*- Dessin ---------------------------------------------------------------*/
	
	public function entrerDessin()
	{
		/* À FAIRE: connaître les dimensions de page. */
		$this->_sortir(<<<TERMINE
			<mxGraphModel dx="1414" dy="793" grid="1" gridSize="10" guides="1" tooltips="1" connect="1" arrows="1" fold="1" page="1" pageScale="1" pageWidth="827" pageHeight="1169" math="0" shadow="0">
				<root>
					<mxCell id="0" />
					<mxCell id="1" parent="0"/>
		TERMINE);
	}
	
	public function sortirDessin()
	{
		$this->_sortir(<<<TERMINE
				</root>
			</mxGraphModel>
		TERMINE);
	}
	
	/*- Nœuds ----------------------------------------------------------------*/
	
	public function calcTableau($tableau)
	{
		/* À FAIRE: titre multi-lignes */
		
        	$idt = ++$this->_id;
        	$y = 16;
        	$hLigne = 16;
		$largeurColonnes = [ null, null ];
		// Si les largeurs de colonne ne sont pas fixées, on approxime au nombre de caractères maximal, multiplié par un facteur, dans une certaine limite.
		$lCols = [];
		$brut = $tableau->d;
		$nCols = 0;
		foreach($brut as $clé => $ligne)
		{
			if(!is_array($ligne))
				$brut[$clé] = ($ligne = [ $ligne ]);
			if($nCols < count($ligne))
				$nCols = count($ligne);
		}
		foreach($brut as $ligne)
		{
			$col = -1;
			// Si c'est une ligne qui n'a pas toutes ses cases, on ne la compte pas.
			/* À FAIRE: être un peu plus subtils, et a posteriori, s'assurer qu'elle tient dans ses successeurs, sinon agrandir la dernière colonne. */
			if(count($ligne) < $nCols) continue;
			foreach($ligne as $case)
			{
				++$col;
				if(!isset($lCols[$col])) $lCols[$col] = 0;
				if(isset($case) && strlen($case) > $lCols[$col]) $lCols[$col] = strlen($case);
			}
		}
		$lTableau = 0;
		foreach($lCols as $col => $l)
		{
			if(isset($largeurColonnes[$col]))
				$lCols[$col] = $largeurColonnes[$col] > 0 ? $largeurColonnes[$col] : -$largeurColonnes[$col] * $hLigne;
			else
			{
				$lCols[$col] *= $hLigne * 0.5; /* À FAIRE: décoder en dur cette largeur d'1/2 hauteur par caractère. */
				if($lCols[$col] > 120) $lCols[$col] = 120; /* À FAIRE: décoder en dur. */
				if($lCols[$col] < $hLigne) $lCols[$col] = $hLigne;
			}
			$lTableau += $lCols[$col];
		}
		$hTotale = $y + count($brut) * $hLigne;
		
		$coord = $this->_p->pos($tableau);
		$coord->aff = [ $this, 'affTableau' ];
		$coord->vars = [ $lCols, $lTableau, $hTotale, $y, $hLigne, $idt, $idt, $y ];
		$coord->l = $lTableau;
		$coord->h = $hTotale;
		$coord->id = $this->_id;
		$coord->accroches = [ 'O' => [ 0, 0, 0, 0, $y / 2 ], 'E' => [ 1, 0, 0, 0, $y / 2 ], 'N' => [ 0, 0.5 ], 'S' => [ 1, 0.5 ] ];
		$coord->sous = [ $this, 'sousTableau' ];
	}
	
	public function sousTableau($tableau, $coord, $sous)
	{
		$r = new \stdClass;
		if(is_int($sous) && !isset($coord->ids[$sous]))
			$sous = array_keys($coord->ids)[$sous];
		$r->id = $coord->ids[$sous];
		
		return $r;
	}
	
	/**
	 * Renvoie le code pour les points d'accroche à un nœud.
	 * Deux modes:
	 * - soit la liste complète des points d'accroche, façon "points=[[x,y],[x,y]]"
	 * - soit, si $point est précisé, un seul des points d'accroche. Normalisation: points cardinaux (N, NNE, NE, ENE, E, etc.)
	 * 
	 * @param $nœud
	 * @param $point Index du point unique dont on veut les coordonnées
	 * @param $préfixe Préfixe sous lequel sortir la liste (ex.: si $préfixe = "entry", la liste sera sortie sous la forme "entryX=…;entryY=…;etc."
	 * 
	 * @return string Chaîne à coller dans le style du nœud rendu.
	 */
	public function affAccroches($nœud, $point = null, $préfixe = null)
	{
		$coord = $this->_p->pos($nœud);
		/* À FAIRE: accroches par défaut. */
		if(!isset($coord->accroches)) return '';
		if(!isset($point))
		return 'points=['.implode(',', array_map(function($l) { return '['.implode(',', $l).']'; }, $coord->accroches)).'];';
		
		if
		(
			(isset($coord->accroches[$point]) && ($point = $coord->accroches[$point]))
		)
		{
			$clés = [ 'x', 'y', 'perimeter', 'dx', 'dy' ];
			$valeurs = $point + [ null, null ];
			return implode('', array_map(function($clé, $valeur) use($préfixe) { return $valeur || in_array($clé, [ 'x', 'y' ]) ? $préfixe.ucfirst($clé).'='.$valeur.';' : ''; }, $clés, $valeurs));
		}
	}
	
	public function affTableau($tableau)
	{
		/* À FAIRE: titre de table */
		
		$brut = $tableau->d;
		$coord = $this->_p->pos($tableau);
		list($lCols, $lTableau, $hTotale, $hTitre, $hLigne, $idt, $idtt, $y) = $coord->vars;
		
		$this->_sortir(<<<TERMINE
			<mxCell id="xsif-$idt" parent="1" style="shape=table;startSize=$y;container=1;collapsible=0;childLayout=tableLayout;fixedHeader=1;{$this->affAccroches($tableau)}tableRender=collapsed;{$this->style(isset($tableau->style) ? $tableau->style : '', $tableau)}{$this->police}" value="Table" vertex="1">
					<mxGeometry height="$hTotale" width="$lTableau" x="{$coord->x}" y="{$coord->y}" as="geometry" />
			</mxCell>
		TERMINE);
		$coord->ids = [];
		foreach($brut as $numLigne => $ligne)
        	{
        	if(!is_array($ligne)) $ligne = [ $ligne ];
			$coord->ids[$numLigne] =
        		$ide = ++$this->_id; // ID Extérieur
			$this->_sortir(<<<TERMINE
				<mxCell id="xsif-$ide" parent="xsif-$idt" style="shape=tableRow;horizontal=0;startSize=0;swimlaneHead=0;swimlaneBody=0;strokeColor=inherit;collapsible=0;dropTarget=0;fillColor=none;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fixedHeader=1;" value="" vertex="1">
					<mxGeometry height="$hLigne" width="$lTableau" y="$y" as="geometry" />
				</mxCell>
			TERMINE);
			$x = 0;
			$col = -1;
			foreach(is_array($ligne) ? $ligne : [ $ligne ] as $numCol => $case)
			{
				++$col;
        		$idi = ++$this->_id; // ID Intérieur
        		$case = isset($case) ? htmlentities(htmlentities($case)) : '';
        		$xCond = $x ? ' x="'.$x.'"' : '';
        		$lCol = $lCols[$col];
        		// Si on est en colspan implicite (dernière colonne de la ligne mais encore de la place après nous), on s'octroie en plus la largeur des suivants.
        		if($col == count($ligne) - 1 && count($ligne) < count($lCols))
        			for($colErre = count($lCols); --$colErre > $col;)
        				$lCol += $lCols[$colErre];
				$this->_sortir(<<<TERMINE
					<mxCell id="xsif-$idi" parent="xsif-$ide" style="shape=partialRectangle;connectable=0;strokeColor=inherit;overflow=hidden;pointerEvents=1;html=1;align=left;{$this->police}" value="$case" vertex="1">
						<mxGeometry height="$hLigne" width="{$lCol}"$xCond as="geometry">
							<mxRectangle height="$hLigne" width="{$lCol}" as="alternateBounds" />
						</mxGeometry>
					</mxCell>
			TERMINE);
				$x += $lCol;
        	}
			$y += $hLigne;
		}
	}
	
	public function affLien($de, $vers, $style = null)
	{
		$id = ++$this->_id;
		$c0 = $this->_p->pos($de);
		$c1 = $this->_p->pos($vers);
		$this->_sortir(<<<TERMINE
			<mxCell id="xsif-$id" edge="1" parent="1" source="xsif-{$c0->id}" style="edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;{$this->affAccroches($de, 'E', 'exit')}{$this->affAccroches($vers, 'O', 'entry')}defaultLabelBackgroundColor=page;{$this->style($style, 'Lien')}" target="xsif-{$c1->id}">
				<mxGeometry relative="1" as="geometry" />
			</mxCell>
		TERMINE);
	}
	
	public function style($style, $classe = null, $départ = [])
	{
		$classes = [];
		if(isset($classe))
		{
			/* À FAIRE: itérer sur les classes parentes. */
			/* À FAIRE?: gérer aussi les traits. */
			if(is_object($classe))
				$classe = get_class($classe);
			$classe = strtr($classe, '\\', '/');
			$classes[$classe] = true;
			$classes[basename($classe)] = true;
		}
		$classes[''] = true;
		$styles = [];
		if(isset($style))
			$styles[$style] = true;
		$styles[''] = true;
		
		foreach($classes as $classe => $trou)
			foreach($styles as $style => $trou)
				if(isset($this->styles[$style][$classe]))
					$départ += $this->styles[$style][$classe];
		$r = '';
		foreach($départ as $clé => $val)
			if($val !== false && isset($val))
			$r .= $clé.'='.$val.';';
		return $r;
	}
/*
<!-- Suite -->
        <mxCell id="xsif-21" parent="1" style="shape=table;startSize=16;container=1;collapsible=0;childLayout=tableLayout;fixedHeader=1;tableRender=collapsed;rounded=0;swimlaneLine=1;" value="Table" vertex="1">
          <mxGeometry height="64" width="60" x="384" y="125" as="geometry" />
        </mxCell>
        <mxCell id="xsif-22" parent="xsif-21" style="shape=tableRow;horizontal=0;startSize=0;swimlaneHead=0;swimlaneBody=0;strokeColor=inherit;collapsible=0;dropTarget=0;fillColor=none;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fixedHeader=1;" value="" vertex="1">
          <mxGeometry height="16" width="60" y="16" as="geometry" />
        </mxCell>
        <mxCell id="xsif-23" parent="xsif-22" style="shape=partialRectangle;connectable=0;overflow=hidden;pointerEvents=1;html=1;align=left;" value="Les oiseaux &amp;lt;= éléphants" vertex="1">
          <mxGeometry height="16" width="60" as="geometry">
            <mxRectangle height="16" width="60" as="alternateBounds" />
          </mxGeometry>
        </mxCell>
        <mxCell id="xsif-24" parent="xsif-21" style="shape=tableRow;horizontal=0;startSize=0;swimlaneHead=0;swimlaneBody=0;strokeColor=inherit;collapsible=0;dropTarget=0;fillColor=none;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fixedHeader=1;" value="" vertex="1">
          <mxGeometry height="16" width="60" y="32" as="geometry" />
        </mxCell>
        <mxCell id="xsif-25" parent="xsif-24" style="shape=partialRectangle;connectable=0;overflow=hidden;pointerEvents=1;html=1;align=left;" value="Là non plus" vertex="1">
          <mxGeometry height="16" width="60" as="geometry">
            <mxRectangle height="16" width="60" as="alternateBounds" />
          </mxGeometry>
        </mxCell>
        <mxCell id="xsif-26" parent="xsif-21" style="shape=tableRow;horizontal=0;startSize=0;swimlaneHead=0;swimlaneBody=0;strokeColor=inherit;collapsible=0;dropTarget=0;fillColor=none;points=[[0,0.5],[1,0.5]];portConstraint=eastwest;fixedHeader=1;" value="" vertex="1">
          <mxGeometry height="16" width="60" y="48" as="geometry" />
        </mxCell>
        <mxCell id="xsif-27" parent="xsif-26" style="shape=partialRectangle;connectable=0;overflow=hidden;pointerEvents=1;html=1;align=left;" value="Oups" vertex="1">
          <mxGeometry height="16" width="60" as="geometry">
            <mxRectangle height="16" width="60" as="alternateBounds" />
          </mxGeometry>
        </mxCell>
        <mxCell id="xsif-28" edge="1" parent="1" source="xsif-12" style="edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0.039;entryY=0.5;entryDx=0;entryDy=0;defaultLabelBackgroundColor=page;entryPerimeter=0;dashed=1;dashPattern=1 2;" target="xsif-22">
          <mxGeometry relative="1" x="-0.0309" as="geometry" />
        </mxCell>
        <mxCell id="xsif-29" edge="1" parent="1" source="xsif-15" style="edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;defaultLabelBackgroundColor=page;dashed=1;dashPattern=1 2;" target="xsif-24">
          <mxGeometry relative="1" as="geometry" />
        </mxCell>
        <mxCell id="xsif-30" edge="1" parent="1" source="xsif-18" style="edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;defaultLabelBackgroundColor=page;dashed=1;dashPattern=1 2;" target="xsif-26">
          <mxGeometry relative="1" as="geometry" />
        </mxCell>
        <mxCell id="xsif-35" edge="1" parent="1" source="xsif-11" style="endArrow=classic;html=1;rounded=0;defaultLabelBackgroundColor=page;edgeStyle=orthogonalEdgeStyle;exitX=0;exitY=0.25;exitDx=0;exitDy=0;entryX=0;entryY=0.25;entryDx=0;entryDy=0;" target="xsif-1" value="">
          <mxGeometry relative="1" as="geometry">
            <mxPoint x="290" y="270" as="sourcePoint" />
            <mxPoint x="390" y="270" as="targetPoint" />
          </mxGeometry>
        </mxCell>
        <mxCell id="xsif-36" parent="xsif-35" style="shape=image;imageAspect=0;verticalLabelPosition=bottom;verticalAlign=top;image=https://tmp.outters.eu/3instances.svg;" value="" vertex="1">
          <mxGeometry height="33" relative="1" width="9" as="geometry">
            <mxPoint x="-4.5" as="offset" />
          </mxGeometry>
        </mxCell>
        <mxCell id="xsif-37" parent="1" style="shape=image;imageAspect=0;aspect=fixed;verticalLabelPosition=bottom;verticalAlign=top;image=https://tmp.outters.eu/database-table.svg;fontStyle=0" value="MaPetiteTable" vertex="1">
          <mxGeometry height="48" width="46" x="500" y="320" as="geometry" />
        </mxCell>
        <mxCell id="xsif-38" parent="1" style="strokeWidth=1;html=1;shape=mxgraph.flowchart.database;strokeColor=#00007F;fillColor=#7F7FFF;aspect=fixed;" value="&amp;lt;hote&amp;gt;/&amp;lt;base&amp;gt;" vertex="1">
          <mxGeometry height="40" width="40" x="620" y="324" as="geometry" />
        </mxCell>
        <mxCell id="xsif-39" edge="1" parent="1" source="xsif-37" style="edgeStyle=orthogonalEdgeStyle;rounded=0;orthogonalLoop=1;jettySize=auto;html=1;exitX=1;exitY=0.5;exitDx=0;exitDy=0;entryX=0;entryY=0.5;entryDx=0;entryDy=0;entryPerimeter=0;defaultLabelBackgroundColor=page;endArrow=none;endFill=0;strokeColor=#7F7F7F;" target="xsif-38">
          <mxGeometry relative="1" as="geometry" />
        </mxCell>
*/
}

?>
