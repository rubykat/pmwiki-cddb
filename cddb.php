<?php if (!defined('PmWiki')) exit();
/*
 * Copyright 2007 Kathryn Andersen
 * 
 * This program is free software; you can redistribute it and/or modify it
 * under the Gnu Public Licence or the Artistic Licence.
 */ 

/** \file cddb.php
 * \brief use CDDB files as sources for PmWiki pages
 *
 * See Also: http://www.pmwiki.org/wiki/Cookbook/CDDB
 *
 * This script enables one's local CDDB collection of data to be
 * directly used as PmWiki pages.
 *
 *  $CdDbDirectory = '/home/fred/.cddb';
 *  $CdDbGroup = 'CdDb';
 *
 * To activate this script, copy it into the cookbook/ directory, then add
 * the following line to your local/config.php:
 *
 *      include_once("$FarmD/cookbook/cddb.php");
 * 
*/

$RecipeInfo['CdDb']['Version'] = '0.01';

global $CdDbDirectory;
global $FarmD;
SDV($CdDbDirectory, "$FarmD/cddb");
SDV($HandleAuth['cddb'], 'read');

## class CdDbPageStore holds objects that store pages
## via the native filesystem in CDDB format.
class CdDbPageStore extends PageStore {
  var $dirfmt;
  var $attr;
  var $cddbGroup;
  function CdDbPageStore($cddbGroup='CdDb', $cddbDir='', $a=NULL) { 
    global $CdDbDirectory;
    $this->cddbGroup = ($cddbGroup ? $cddbGroup : 'CdDb');
    $this->dirfmt = ($cddbDir ? $cddbDir : $CdDbDirectory);
    $this->attr = (array)$a;
    $this->PageStore( $CdDbDirectory );
  }
  function pagefile($pagename) {
      $dir = $this->dirfmt;
      if ($pagename > '') {
	  $pagename = str_replace('/', '.', $pagename);
	  $name = $pagename;
	  if (strpos($pagename, '.'))
	  {
	  	$parts = explode('.', $pagename);
		$name = $parts[1];
	  }
	  $file = strtolower($name);
	  return "$dir/$file";
      }
      return '';
  }
  function filepage($file) {
	$pagename = $file;
  	return ucfirst($pagename);
  }
  function exists($pagename) {
      global $DefaultName;
      if (!$pagename) return false;

      // In CdDb group?
      $group = PageVar($pagename, '$Group');    
      if ( $group != $this->cddbGroup )
      {
	  return false;
      }

      // get page name
      $name = PageVar($pagename, '$Name');    

      // Special page?
      if ( $name==$DefaultName || $name=="GroupHeader" ) return true;

      $pagefile = $this->pagefile($name);
      return ($pagefile && file_exists($pagefile));
  }

  function read($pagename, $since=0) {
      global $DefaultName;
      $group = PageVar($pagename, '$Group');    
      $name = PageVar($pagename, '$Name');    
      if ( $group != $this->cddbGroup )
      {
	  return false;
      }

      // Homepage?
      if ($name==$DefaultName ) {
	  $page = ReadPage( 'Site.CdDbHomePageDefault');
	  $page['name'] = $pagename;
	  return $page;
      }

      // GroupHeader?
      if ($name=="GroupHeader" ) {
	  $page = ReadPage( 'Site.CdDbGroupHeaderDefault');
	  $page['name'] = $pagename;
	  return $page;
      }

      $fore = '(:';
      #$fore = ':';
      $aft = ":)\n";
      #$aft = "\n";

      $pagefile = $this->pagefile($name);
      if ($pagefile && ($fp=@fopen($pagefile, "r"))) {
	  $numtracks = 0;
	  $description = '';
	  $title = '';
	  $tracks = array();
	  $tracks_ext = array();
	  $page = $this->attr;
	  while (!feof($fp)) {
	      $line = fgets($fp, 4096);
	      while (substr($line, -1, 1) != "\n" && !feof($fp)) 
	      { $line .= fgets($fp, 4096); }
	      $line = rtrim($line);
	      if (!$line) continue;
	      if (strpos($line, '=') == FALSE) continue;

	      // render the fields as page text variables
	      @list($k,$v) = explode('=', $line, 2);
	      if (!$k) continue;
	      if (!$v) continue;
	      if ($k == 'DTITLE')
	      {
	      	$title .= $v;
	      }
	      else if ($k == 'EXTD')
	      {
	      	$description .= preg_replace('/\\\n/', ' ', $v);
	      }
	      else if (preg_match('/^EXTT(\d+)/', $k, $m))
	      {
		  $tracks_ext[$m[1]] .= preg_replace('/\\\n/', ' ', $v);
	      }
	      else if (preg_match('/^TTITLE(\d+)/', $k, $m))
	      {
		  $tracks[$m[1]] = $v;
		  $numtracks++;
	      }
	      else {
		  $page['text'] .= $fore . $k . ':' . $v . $aft;
	      }
	  }
	  fclose($fp);
	  if ($title)
	  {
	      	  $parts = explode(' / ', $title);
		  $artist = preg_replace('/&/', ' and ', $parts[0]);
		  $artist = preg_replace('/[^-\w\s]/', '', $artist);
		  $artist = preg_replace('/  /', ' ', $artist);
		  $album = ucfirst($parts[1]);
		  $page['text'] .= $fore . 'Artist' . ':' . $artist . $aft;
		  $page['text'] .= $fore . 'Album' . ':' . $album . $aft;
		  $page['text'] .= '(:title ' . $album . ":)\n";
		  $page['title'] = $album;
	  }
	  $page['text'] .= $fore . 'NumTracks' . ':' . $numtracks . $aft;
	  if ($description)
	  {
	  	$page['text'] .= "(:description $description:)\n";
	  	$page['description'] .= "$description\n";
	  }
	  if ($tracks)
	  {
	      $page['text'] .= $fore . 'AllTracks' . ":\n#" . join("\n#", $tracks) . $aft;
	  }
	  for ($i=0; $i < $numtracks; $i++)
	  {
	  	if ($tracks[$i])
		{
		    $page['text'] .= $fore . 'Track' . $i . ':' . $tracks[$i] . $aft;
		}
		if ($tracks_ext[$i])
		{
		    $page['text'] .= $fore . 'TrackExt' . $i . ':' . $tracks_ext[$i] . $aft;
		}
	  }
      }
      return @$page;
  }
  function ls($pats=NULL) {
      global $GroupPattern, $NamePattern;
      $pats=(array)$pats; 
      array_push($pats, "/^$GroupPattern\.$NamePattern$/");

      $out = array();
      // only one directory, no subdirs
      $dir = $this->dirfmt;
      $dfp = @opendir($dir); if (!$dfp) { continue; }
      $o = array();
      while ( ($pagefile = readdir($dfp)) !== false) {
	  if ($pagefile{0} == '.') continue;
	  if (is_dir("$dir/$pagefile"))
	  {
	      continue;
	  }
	  else {
	      $o[] = $this->cddbGroup . '.' . $this->filepage($pagefile);
	  }
      }
      closedir($dfp);
      $out = array_merge($out, MatchPageNames($o, $pats));
      return $out;
  }
}

