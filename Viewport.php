<?php

class Viewport
{
	public $width = 0, $height = 0, $scale = 0, $center = null;
	
	private $_document = null;
	
	public $path = null;
	
	public function __construct($width, $height)
	{
		$this->width = $width;
		$this->height = $height;
	
		require_once('../lib/cleversvg/cleversvg.php');
		$autoloader = Zend_Loader_Autoloader::getInstance();
		$autoloader->pushAutoloader('csvgautoload', 'cs');
		
		$this->_document = new csDocument($width, $height);
	}
	
	public function autoScale()
	{
	}
	
	public function setPathStyle()
	{
	}
	
	protected function _transformPoint($fix)
	{
		$xo = $this->center->x;
		$yo = $this->center->y;
		$scale = $this->scale;
		$xp = $this->width / 2;
		$yp = $this->height / 2;
		
		$xc = ( ( $fix->x - $xo ) / $scale ) + $xp;
		$yc = ( ( $fix->y - $yo ) / ( $scale * -1 ) ) + $yp;
		
		return array($xc, $yc);
	}
	
	protected function _clipped($p)
	{
		return ($p[0] < 0 || $p[0] > $this->width ||
		$p[1] < 0 || $p[1] > $this->height);
	}
	
	protected function _renderPath()
	{		
		$lines = array();
		
		$markers = array();
		
		$points = array();
		$prevPt = null;
		
		for($i = 0; $i < count($this->path); $i++){
		
			$fx = $this->path[$i];
			
			// Filter low speed point, first and last.
			if($fx->speed < 15.0 || $i == 1 || $i == count($this->path) - 1){
				continue;
			}
			
			// Ajoute le point courant
			$p = $this->_transformPoint($fx);
			
			
			if($this->_clipped($p)){ // le pt est hors cadre
				
				if(! empty($points)){
					array_push($points, $p);
					array_push($lines, $points);
					$points = array();
				}
				
				$prevPt = $p;
				
				continue; // non il faut dŽbuter une nouvelle ligne
			}
			else{ // le pt est bon
				
				if(empty($points) && $prevPt){
					array_push($points, $prevPt);
					$prevPt = null;
					
					
				}
				array_push($points, $p);
				//array_push($markers, $p);
				
				if($fx->mark)
				{
					array_push($markers, $p);
				}
			}
		
			// Detect une pompe si on fait un tour complet
			// $circ = new csCircle(30, 30, 30, array('fill' => 'red'));
		}
		if(! empty($points)){
			array_push($lines, $points);
		}
		
		foreach($lines as $line){
			$dl = new csPolyline();
			$dl->setPointsArray($line);
			$dl->setStroke('red');
			$dl->setStrokeWidth(2);
			$this->_document->addElement($dl);
		}
		
		foreach($markers as $mk){
			$dc = new csCircle($mk[0], $mk[1], 2.5, array('fill' => 'blue'));
			$this->_document->addElement($dc);
		}
	}
	
	public function render()
	{
		$doc = $this->_document;
		
		$this->_renderPath();
		
		$str = $doc->toXML(true);
		$str = str_replace('<svg:', '<', $str);
		$str = str_replace('</svg:', '</', $str);
		return $str;
	}
	
	// Setup smoothing amount
	public function setSmoothing()
	{
	}
	
	protected function _findSmoothedPoint()
	{
		throw new Exception('Not implemetned');
	
		$pf = $igc->fixes[$i - 1];
		$nf = $igc->fixes[$i + 1];
			
			
		$xn = ( ( $nf->x - $xo ) / $scale ) + $xp;
		$yn = ( ( $nf->y - $yo ) / ( $scale * -1 ) ) + $yp;
	
		// **** Compute bezier mid point
		// trouver le pt de controle
		$sp = $fx->speed * 0.5; // distance parcouru en 3 seconde
	
		// vecteur tangente
		$vx = $nf->x - $pf->x;
		$vy = $nf->y - $pf->y;
		// norme * vitesse
		$vn = (float) sqrt($vx*$vx + $vy*$vy);
		$vnx = $xc + ($vx / $vn) * $sp;
		$vny = $yc - ($vy / $vn) * $sp; // on a le pt de controle de la courbe de bezier
	
		// on effectue l'interpolation maintenant, pour t=0.5
		$bx = 0.25 * ($xc + $xn) + 0.5 * $vnx;
		$by = 0.25 * ($yc + $yn) + 0.5 * $vny;
	
		array_push($points, array( $bx,  $by));
	}

}
