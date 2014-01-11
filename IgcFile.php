<?php

/**
 * Require Zend lib, Shaw lib, gPoint lib
 * @author david
 *
 */
require_once('gPoint.php');

class IgcFile_Exception extends Exception{}

class IgcFix{
	public $pos, $mark, $alt, $alt_gps, $speed, $vert_speed, $vert_gps_speed, $date, $x, $y, $heading, $tx, $ty, $dist;
	
	public function __toString(){
		return sprintf('%s, alt %s, speed %s km/h, vario %s m/s <br />',
			//$this->pos->printLatLong(),
			$this->date->__toString(),
			$this->alt_gps,
			number_format($this->speed * 3.6, 1),
			number_format($this->vert_gps_speed, 2)
			);
	}
}

// @see http://carrier.csi.cam.ac.uk/forsterlewis/soaring/igc_file_format/igc_format_2008.html
class IgcFile
{
	public static function factory($mixed)
	{
		if(! is_string($mixed)){
			throw new IgcFile_Exception('Not implemented');
		}
		
		$igc = new IgcFile();
		$igc->parse($mixed);
		return $igc;
	}
	
	public function __construct()
	{
		
	}
	
	public function parse($string)
	{
		$lines = explode(PHP_EOL, $string);
		
		foreach($lines as $line){
			$line = trim($line);
			$type = substr($line, 0, 1);
			$line = substr($line, 1);
			
			$method = '_parse' . $type;
			if(method_exists($this, $method)){
				call_user_func(array($this, $method), $line);
			}
		}
		
		$this->_findAndApplyLambertParameters();
		$this->_computeFixSpeed();
		$this->_computeSmoothHeading();
		$this->_determineType();
	}
	
	private function _parseA($line)
	{}
	
	private function _parseI($line)
	{}
	
	private function _parseL($line)
	{}
	
	private function _parseC($line)
	{}
	
	public $fixes = array();
	protected $fixeAdditionnal = array();
	
	/**
	 * Return duration in seconds
	 */
	public function getDuration()
	{
		$first = reset($this->fixes);
		$last = end($this->fixes);
		
		return $last->date->diff($first->date, 1);
	}
	
	protected function _rsum($v, $w)
	{
	    $v += $w;
	    return $v;
	}
	
	private function _findAndApplyLambertParameters()
	{
		$origLong = null;
		$origLat = null;
		$parN = null;
		$parS = null;
		$merE = null;
		$merW = null;
		
		$falseEasting = 0;
		$falseNorthing = 0;
		
		for($i = 0; $i < count($this->fixes); $i++)
		{
			$c = $this->fixes[$i];
			if($i == 0){
				$parN = $parS = $c->pos->Lat();
				$merE = $merW = $c->pos->Long();
			}
			if($c->pos->Lat() < $parS){$parS = $c->pos->Lat();}
			if($c->pos->Lat() > $parN){$parN = $c->pos->Lat();}
			if($c->pos->Long() < $merW){$merW = $c->pos->Long();}
			if($c->pos->Long() > $merE){$merE = $c->pos->Long();}
		}
		
		$origLong = ($merW + $merE) / 2;
		$origLat = ($merN + $merS) / 2;
		
		$center = new gPoint();
		$center->setLongLat($merW, $origLat);
		$center->configLambertProjection($falseEasting, $falseNorthing, $origLong, $origLat, $parS, $parN);
		$center->convertLLtoLCC();
		
		for($i = 0; $i < count($this->fixes); $i++)
		{
			$c = $this->fixes[$i];
			$c->pos->configLambertProjection($falseEasting, $falseNorthing, $origLong, $origLat, $parS, $parN);
			$c->pos->convertLLtoLCC();
			$c->x = $c->pos->lccEasting;
			$c->y = $c->pos->lccNorthing;
		}
	}
	
	private function _computeSmoothHeading()
	{
		for($i = 0; $i < count($this->fixes); $i++)
		{
			$c = $this->fixes[$i];
			$n = $p = null;
			if($i > 0){
				$p = $this->fixes[$i - 1];
			}
			if($i < count($this->fixes) - 1){
				$n = $this->fixes[$i + 1];
			}
			if(! $n) $n = $c;
			if(! $p) $p = $c;
			
			$tx = $n->x - $p->x;
			$ty = $n->y - $p->y;
			$norm = (float) sqrt($tx*$tx + $ty*$ty);
			
			if($norm == 0.0){
				continue;
			}
			
			$tx = $tx / $norm;
			$ty = $ty / $norm;
			
			$c->tx = $tx;
			$c->ty = $ty;
			
			if($tx == 0.0){
				$head = ($ty > 0.0 ? 180.0 : 0.0);
			}
			else{
				$head = atan($ty / $tx) * 180.0 / pi();
				if($tx > 0.0){
					$head = 90.0 - $head;
				}
				else{
					$head = 270.0 - $head;
				}
			}
			if($head >= 360.0){
				$head -= 360.0;
			}
			
			// Compute distance for previous point
			if($p && $c){
				// determine c parameter, point $c is on the tangent :)
				$cp = -1.0 * ($ty * $c->x - $tx * $c->y);
				$dist = -1.0 * ($ty * $p->x - $tx * $p->y + $cp);
				$c->dist = $dist;
			}
		}
	}
	
	private function _dist($x, $y, $xp, $yp){
		$dx = $x - $xp;
		$dy = $y - $yp;
		return sqrt($dx * $dx + $dy * $dy);
	}
	
	private function _determineType()
	{
		$f = $this->fixes;
		
		// plus simple, moyenne mobile sur les 4 dernier points...
		$p = null;
		for($i = 4; $i < count($f) - 3; $i++)
		{
			$c = $f[$i];
			$xs = 0; for($j = -2; $j < 3; $j++){$xs += $f[$i + $j]->x;}
			$ys = 0; for($j = -2; $j < 3; $j++){$ys += $f[$i + $j]->y;}
			$c->mx = $xs / 5;
			$c->my = $ys / 5;
		}
		
		for($i = 4; $i < count($f) - 3; $i++)
		{
			$c = $f[$i];
			
			$speed = array();
				
			
				$p = $f[$i - 1];
				$dp = $this->_dist($c->mx, $c->my, $p->mx, $p->my);
				$sp = $c->date->diff($p->date, 1);
				$speed[] =  $dp / $sp;
	
				$n = $this->fixes[$i + 1];
				$dp = $this->_dist($c->mx, $c->my, $n->mx, $n->my);
				$sn = (float) ($n->date->diff($c->date, 1));
				$speed[] =  $dn / $sn;

			
				
			$c->mspeed = array_reduce($speed, array($this, '_rsum')) / count($speed);
			
			if($c->mspeed < 7){
				$c->mark = true;
			}
		}
	}
	
	private function _computeFixSpeed()
	{
		for($i = 0; $i < count($this->fixes); $i++)
		{
			$c = $this->fixes[$i];
			$speed = array();
			$vspeed = array();
			$vgspeed = array();
			
			if($i > 0){
				$p = $this->fixes[$i - 1];
				$dp = $c->pos->distanceFrom($p->pos->Long(), $p->pos->Lat());
				$sp = $c->date->diff($p->date, 1);
				$speed[] =  $dp / $sp;
				$vspeed[] = ($c->alt - $p->alt) / $sp;
				$vgspeed[] = ($c->alt_gps - $p->alt_gps) / $sp;
				
			}
			
			if($i < count($this->fixes) - 1){
				$n = $this->fixes[$i + 1];
				$dn = $c->pos->distanceFrom($n->pos->Long(), $n->pos->Lat());
				$sn = (float) ($n->date->diff($c->date, 1));
				$speed[] =  $dn / $sn;
				$vspeed[] = (float)($n->alt - $c->alt) / $sn;
				$vgspeed[] = (float)($n->alt_gps - $c->alt_gps) / $sn;
			}
			
			$c->speed = array_reduce($speed, array($this, '_rsum')) / count($speed);
			$c->vert_speed = array_reduce($vspeed, array($this, '_rsum')) / (float)count($vspeed);
			$c->vert_gps_speed = array_reduce($vgspeed, array($this, '_rsum')) / (float)count($vgspeed);
		}
	}
	
	private function _parseB($line)
	{
		$fix = new IgcFix();
		
		// Date
		$time = substr($line, 0, 6);
		$time = $this->_date_parse_from_format('HHiiss', $time);
		$dt = clone $this->header['date'];
		$dt->setTime($time['hour'], $time['minute'], $time['second']);
		$fix->date = $dt;
		
		// Point
		if(preg_match('/^(\d{2})(\d{2})(\d{3})([N|S])$/', substr($line, 6, 8), $matches)){
			$lat = ((float) $matches[1] + (float)((float)$matches[2] + (float)$matches[3] / 1000) / 60 ) * ($matches[4] == 'N' ? 1 : -1);
		}
		else throw new Exception('shit');
		if(preg_match('/^(\d{3})(\d{2})(\d{3})([E|W])$/', substr($line, 14, 9), $matches)){
			$long = ((float) $matches[1] + (float)((float)$matches[2] + (float)$matches[3] / 1000) / 60 ) * ($matches[4] == 'E' ? 1 : -1);
		}
		else throw new Exception('shit');
		$pt = new gPoint();
		$pt->setLongLat($long, $lat);
		$fix->pos = $pt;
		
		// Altitude
		$fix->is3d = (substr($line, 23, 1) == 'A');
		
		$fix->alt = (int) substr($line, 24, 5);
		$fix->alt_gps = (int) substr($line, 29, 5);
		
		array_push($this->fixes, $fix);
	}
	
	private function _parseH($line)
	{
		$rec = strtolower(substr($line, 0, 4));
		$line = substr($line, 4);
		switch($rec){
			case 'fdte':
				$date = $this->_date_parse_from_format('ddmmyy', $line);
				$dt = new Shaw_DateTime();
				$dt->setDate(2000 + $date['year'], $date['month'], $date['day']);
				$this->addHeader('date', $dt);
				break;
			case 'ffxa':
				$this->addHeader('fix_accuracy', (int) $line);
				break;
			case 'fplt':
				$this->addHeader('pilot', $this->_extract_string($line));
				break;
			case 'fgty':
				$this->addHeader('glider_type', $this->_extract_string($line));
				break;
			case 'fcid':
				$this->addHeader('competition_name', $this->_extract_string($line));
				break;
			case 'fccl':
				$this->addHeader('competition_class', $this->_extract_string($line));
				break;
			default:
				// $this->addHeader($rec, $line);
				break;
		}
	}
	
	private function _extract_string($line)
	{
		$line = substr($line, stripos($line, ':') + 1);
		return $line;
	}
	
	// @see http://php.net/manual/fr/function.date-parse-from-format.php
	private function _date_parse_from_format($format, $date) {
		$dMask = array(
			'H'=>'hour',
			'i'=>'minute',
			's'=>'second',
			'y'=>'year',
			'm'=>'month',
			'd'=>'day'
		);
		$format = preg_split('//', $format, -1, PREG_SPLIT_NO_EMPTY);
		$date = preg_split('//', $date, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($date as $k => $v) {
			if ($dMask[$format[$k]]) $dt[$dMask[$format[$k]]] .= $v;
		}
		return $dt;
	}
	
	public $header = array();
	
	protected function addHeader($name, $value)
	{
		if(isset($this->header[$name])){
			if(is_array($this->header[$name])){
				array_push($this->header[$name], $value);
			}
			else{
				$cst = $this->header[$name];
				$this->header[$name] = array($cst, $value);
			}
		}
		else{
			$this->header[$name] = $value;
		}
		return $this;
	}
}