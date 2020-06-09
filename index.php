<?php
/*
Automatic one-file-does-all PHP indexer

Copyright (C) 2004 Riku Lindblad

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.


USAGE:
  1) Untar the indexer:
       tar zxvf indexer-x.x.tar.gz
  2) Create the thumbnail directory in an appropriate location: 
       mkdir -m 777 thumbnails
     The thumbnail directory has to be writeable by the webserver
     process.
  3) $EDITOR index.php, and change the settings to your liking
  4) cd dir_to_index
  5) ln -s PATH/TO/INDEXER/index.php .
  6) Enjoy =)

Symlinking the file eases upgrades a lot, trust me.

Special per-directory files:
  .ignore
    List files to ignore (skip) in the file listing, one per line. 
    Shell patterns (*?[]) supported on PHP versions, which support
    fnmatch()
  dirstyle.css
    Overrides the default stylesheet for this directory
  header.html
    Stuff to display on top of the index
  footer.html
    Bottom of the index
  bottomfooter.html
    Bottom-most stuff
  indexsetup.php
    Configuration overrides for this directory (f.ex. disable/enable 
    thumbnailing)

Thumbnailing
------------

You can speed up the thumbnailing proces by running the index file from 
the commandline by saying 'php index.php' in the appropriate directory. 
Md5sums can also be created beforehand with the commandlinetool 'md5sum',
the file formats are identical.

 */

//--------------------------------------------------
// Configuration...
//--------------------------------------------------

$show_lastmod = true;     // last modified time
$show_filesize = true;    // file size
$show_files = true;       // show files
$show_dirs = true;        // show directories

$doThumbs = false;         // generate thumbnails in normal mode

$mode = 'normal';         // options: 'normal' & 'thumbnails'

// thumbnail directory, this doesn't have to be visible to the web
$thumbdir = '/tmp/thumbnails/';

// put any additional extensions in here
static $fileTypes = array(
  "html"     => array("html", "htm"),
  "image"    => array("gif", "jpg", "jpeg", "png", "tif", "tiff", "bmp", "art"),
  "text"     => array("asp", "c", "cfg", "cpp", "css", "csv", "conf", "cue", "diz", "h", "inf", "ini", "java", "js", "log", "nfo", "php", "phps", "pl", "rdf", "rss", "rtf", "sql", "txt", "vbs", "xml"),
  "binary"   => array("asf", "au", "avi", "bin", "class", "divx", "doc", "exe", "mov", "mpg", "mpeg", "mp3", "ogg", "ogm", "pdf", "ppt", "ps", "rm", "swf", "wmf", "wmv", "xls"),
  "archive"  => array("ace", "arc", "bz2", "cab", "gz", "lha", "jar", "rar", "sit", "tar", "tbz2", "tgz", "z", "zip", "zoo")
);

// this oneliner can be used to generate new images when needed:
// FILE=folder.gif; python -c "import base64;import sys;base64.encode(file('$FILE'), sys.stdout)"
// where folder.gif is the image to convert (python required)

// NOTE: Only GIF-images are supported at the moment!

// thumbnail images encoded in base64
static $imagesEncoded = array(
  "archive"   => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI3lA+pxxgfUhNKPRAbhimu2kXiRUGeFwIlN47qdlnuarokbG46nV937UO9gDMHsMLAcSYU0GJSAAA7",
  "asc"       => "R0lGODlhBQADAIABAN3d3f///yH5BAEAAAEALAAAAAAFAAMAAAIFTGAHuF0AOw==",
  "binary"    => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI0lICZxgYBY0DNyfhAfROrxoVQBo5mpzFih5bsFLoX5iLYWK6xyur5ubPAbhPZrKhSKCmCAgA7",
  "desc"      => "R0lGODlhBQADAIABAN3d3f///yH5BAEAAAEALAAAAAAFAAMAAAIFhB0XC1sAOw==",
  "folder"    => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAIplI+JwKAJggzuiThl2wbnT3UgWHmjJp5Tqa5py7bhJc/mWW46Z/V+UgAAOw==",
  "html"      => "R0lGODlhEAAQAKIHABsb/2ho/4CA/0BA/zY2/wAAAP///////yH5BAEAAAcALAAAAAAQABAAAANEeFfcrVAVQ6thUdo6S57b9UBgSHmkyUWlMAzCmlKxAZ9s5Q5AjWqGwIAS8OVsNYJxJgDwXrHfQoVLEa7Y6+Wokjq+owQAOw==",
  "image"     => "R0lGODlhEAAQAKIEAK6urmRkZAAAAP///////wAAAAAAAAAAACH5BAEAAAQALAAAAAAQABAAAANCSCTcrVCJQetgUdo6RZ7b9UBgSHnkAKwscEZTy74pG9zuBavA7dOanu+H0gyGxN0RGdClKEjgwvKTlkzFhWOLISQAADs=",
  "text"      => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI0lICZxgYBY0DNyfhAfXcuxnWQBnoKMjXZ6qUlFroWLJHzGNtHnat87cOhRkGRbGc8npakAgA7",
  "download"  => "R0lGODlhBwAQAIABAAAAAP///yH5BAEAAAEALAAAAAAHABAAAAISjI+pywb6UkQzgHsPls3h2gUFADs=",
  "blank"     => "R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==",
  "unknown"   => "R0lGODlhEAAQAJECAAAAAP///////wAAACH5BAEAAAIALAAAAAAQABAAAAI1lICZxgYBY0DNyfhAfXcuxnkI1nCjB2lgappld6qWdE4vFtprR+4sffv1ZjwdkSc7KJYUQQEAOw==",
  "bigfolder" => "R0lGODlhIAAgAMQAAP///wAAACkxKWNjY5ycpb29vc7Ozt7e3v//7////wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAACH5BAAAAAAALAAAAAAgACAAxP///wAAACkxKWNjY5ycpb29vc7Ozt7e3v//7////wAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAXzICAGpGieaKoGROsShRsMdF0H6ggXhdEbhkPwcEAUEQjCYIbLoUi0FnKKVDIFKVZrObs1SU2nSQvb9X7B4cFKC5+6rTSxeKQml0uxHgAdbK8CgYF8fW1ueysvLlw2jSVOZC0xPD1WTF1tOSyTPD5AckRGa3iHI51oQnNGdmylb3AEq3ZTrYhPsLSkgoO2WUxLglmGvWIsjF7ET4rHhsh6kcuFjsWKZZ3SNq46LpxnQJbMrlrdnkCpoQa1WeSoarLqT5TloHRU8G+Sn+f1rKSasKrq9GOyp08Lgfb89TIYKyEgLMQY3nkYLFkhihCTvSKRUUQIADs=",
);
//--------------------------------------------------
// ...Configuration
//--------------------------------------------------

$verno = "2.3";

// report all errors
error_reporting(E_ALL);

// display all errors
ini_set('display_errors', TRUE);
// don't log errors to server log
ini_set('log_errors', FALSE);
// Warn about string concatenation with + instead of .
ini_set('warn_plus_overloading', TRUE);

//******************************************************************************
//*              NO NEED TO EDIT BELOW THIS POINT
//******************************************************************************

// setting overrides for this directory
@include("./indexsetup.php");

/**
 * generate images on the fly when requested (base64 decode)
 */
if (isset($_GET["getimage"]) and $_GET["getimage"] != "") {
  global $imagesEncoded;

  $imageDataEnc = $imagesEncoded[$_GET["getimage"]];
  if ($imageDataEnc) {
    $maxAge = 31536000; // one year
    doConditionalGet($_GET["getimage"], gmmktime(1, 0, 0, 1, 1, 2004));
    $imageDataRaw = base64_decode($imageDataEnc);
    Header("Content-Type: image/gif");
    Header("Content-Length: " . strlen($imageDataRaw));
    Header("Cache-Control: public, max-age=$maxAge, must-revalidate");
    Header("Expires: " . createHTTPDate(time() + $maxAge));
    echo $imageDataRaw;
  }

  die();
}

/**
 * Passthru thumbnails from thumb directory
 */
if (isset($_GET["getthumb"]) and $_GET["getthumb"] != "") {
  // thumbnail file
  $thumbfile = $thumbdir . $_GET["getthumb"] . ".jpg";

  $maxAge = 31536000; // one year
  doConditionalGet($_GET["getthumb"], gmmktime(1, 0, 0, 1, 1, 2004));
  Header("Content-Type: image/jpeg");
  Header("Content-Length: " . filesize($thumbfile));
  Header("Cache-Control: public, max-age=$maxAge, must-revalidate");
  Header("Expires: " . createHTTPDate(time() + $maxAge));
  fpassthru(fopen($thumbfile, 'rb'));

  die();
}

/**
 * Create thumbnail from given source
 *
 * Returns URL to thumbnail, fails silently if source is corrupted
 */
function createThumb($src, $maxWidth = 100, $maxHeight = 100, $quality = 100)
{
  global $thumbdir;

  // thumb dir has to exist, return default image otherwise
  if (!file_exists($thumbdir) or !is_writable($thumbdir)) return getIcon('image');

  // if picture md5sum is in cache, use it, else calculate it
  $md5cache = getMD5Sums();
  if (array_key_exists($src, $md5cache)) {
    $thumbname = $md5cache[$src];
  } else {
    $thumbname = md5_file($src);
    // cache md5sum for future reference
    cacheMD5($src, $thumbname);
  }

  $dest = $thumbdir . $thumbname . ".jpg";

  // no need to create thumbs twice
  if (file_exists($dest)) return getThumbnail($thumbname);

  // path info
  $destInfo  = pathInfo($dest);

  // image src size
  $srcSize   = getImageSize($src);

  // image dest size $destSize[0] = width, $destSize[1] = height
  $srcRatio  = $srcSize[0] / $srcSize[1]; // width/height ratio
  $destRatio = $maxWidth / $maxHeight;
  if ($destRatio > $srcRatio) {
    $destSize[1] = $maxHeight;
    $destSize[0] = $maxHeight * $srcRatio;
  } else {
    $destSize[0] = $maxWidth;
    $destSize[1] = $maxWidth / $srcRatio;
  }

  // true color image
  $destImage = imageCreateTrueColor($destSize[0], $destSize[1]);

  // antialiasing requres GD2
  if (function_exists('imageAntiAlias')) imageAntiAlias($destImage, true);

  // src image
  switch ($srcSize[2]) {
    case 1: //GIF
      $srcImage = @imageCreateFromGif($src);
      break;

    case 2: //JPEG
      $srcImage = @imageCreateFromJpeg($src);
      break;

    case 3: //PNG
      $srcImage = @imageCreateFromPng($src);
      break;

    default:
      return "Unknown image type";
      break;
  }

  // resampling
  @imageCopyResampled($destImage, $srcImage, 0, 0, 0, 0, $destSize[0], $destSize[1], $srcSize[0], $srcSize[1]);

  // generating image (thumbs are always jpegs)
  switch ($srcSize[2]) {
    case 1:
    case 2:
    case 3:
      imageJpeg($destImage, $dest, $quality);
      break;
  }

  return getThumbnail($thumbname);
}

/**
 * only get if the source has been modified
 * this function is from http://simon.incutio.com/archive/2003/04/23/conditionalGet
 */
function doConditionalGet($file, $timestamp)
{
  $last_modified = createHTTPDate($timestamp);
  $etag = '"' . md5($file . $last_modified) . '"';
  // Send the headers
  Header("Last-Modified: $last_modified");
  Header("ETag: $etag");
  // See if the client has provided the required headers
  $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ?
    stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE']) :
    false;
  $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
    stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) :
    false;
  if (!$if_modified_since and !$if_none_match) {
    return;
  }
  // At least one of the headers is there - check them
  if ($if_none_match and $if_none_match != $etag) {
    return; // etag is there but doesn't match
  }
  if ($if_modified_since and $if_modified_since != $last_modified) {
    return; // if-modified-since is there but doesn't match
  }
  // Nothing has changed since their last request - serve a 304 and exit
  Header('HTTP/1.0 304 Not Modified');
  die();
}

/**
 * create a HTTP conformant date
 */
function createHTTPDate($time)
{
  return gmdate("D, d M Y H:i:s", $time) . " GMT";
}

/**
 * Determine a files' type based on its extension.
 */
function getFileType($filename)
{
  global $fileTypes;
  static $extensions = null;

  if ($extensions == null) {
    $extensions = array();
    foreach ($fileTypes as $keyType => $value) {
      foreach ($value as $ext) $extensions[$ext] = $keyType;
    }
  }

  $pi = pathinfo($filename);

  // directories cause warnings, prevent it
  if (!array_key_exists('extension', $pi)) return "unknown";

  $extension = $pi['extension'];
  $type = @$extensions[strtolower($extension)];
  if ($type == "") {
    return "unknown";
  } else {
    return $type;
  }
}

/**
 * Return a nicely formatted string with the file size
 */
function getFileSize($filename)
{
  if (is_dir($filename))
    return "";
  else if (is_file($filename)) {
    $size = filesize($filename);
    $sizes = array('B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB');
    $ext = $sizes[0];
    for ($i = 1; (($i < count($sizes)) and ($size >= 1024)); $i++) {
      $size = $size / 1024;
      $ext  = $sizes[$i];
    }
    return round($size, 2) . " " . $ext;
  }
}

/**
 * get last modification date of the given file/dir
 */
function getFileModtime($file)
{
  return date("Y-m-d", filemtime($file));
}

/**
 * get icon for given file type
 */
function getIcon($fileType)
{
  return $_SERVER["PHP_SELF"] . "?getimage=$fileType";
}

/**
 * get thumbnail image from md5sum
 */
function getThumbnail($md5)
{
  return $_SERVER["PHP_SELF"] . "?getthumb=$md5";
}

/**
 * Write filename and md5sum to a standard-format md5sum-file
 *
 * File format: <md5sum><2 whitespace><filename>
 */
function cacheMD5($file, $md5)
{
  global $thumbdir;

  $fp = fopen($thumbdir . "md5sums", 'a');
  fwrite($fp, $md5 . "  " . $file . "\n");
  fclose($fp);
}


/**
 * Load MD5 cache from current directory
 */
function getMD5Sums()
{
  global $thumbdir;

  $file = @file($thumbdir . "md5sums");

  if (!$file) return array();

  $md5sums = array();

  foreach ($file as $line) {
    list($md5, $fname) = explode("  ", $line);
    $md5sums[rtrim($fname)] = $md5;
  }

  return $md5sums;
}

/**
 * load dir into an array
 */
function getDirArray($path)
{
  $fileNames = array();
  $handle = opendir($path);
  while (false !== ($file = readdir($handle)))
    $fileNames[] = $file;
  closedir($handle);

  return $fileNames;
}

/**
 * Filter out ignored files/directories
 */
function filterFilelist($file)
{
  global $ignores;

  if (function_exists('fnmatch')) {
    foreach ($ignores as $pattern) {
      if (fnmatch($pattern, $file)) return false;
    }
    return true;
  } else {
    // revert to the old method
    if (in_array($file, $ignores))
      return false;
    else
      return true;
  }
}

// get directory content and filter ignored files from it
$dircontent = getDirArray(".");

// basic alphabetical sort for the directory content
natsort($dircontent);

// get ignores for this directory
$ignores = @file(".ignore");
// no ignores found, just use the defaults
if (!$ignores) {
  $ignores = array();
} else {
  // trim all user defined ignores
  $tmp = array();
  foreach ($ignores as $ign) {
    array_push($tmp, trim($ign));
  }
  $ignores = $tmp;
}
// ..and add some default ignores on top
array_push($ignores, ".");          // this directory
array_push($ignores, ".ignore");    // the ignore file
array_push($ignores, "index.php");  // this file
array_push($ignores, "*~");         // backup files

// filter ignored files from directory content
$dircontent = array_filter($dircontent, "filterFilelist");

// -- start (X)HTML --
?>
<!DOCTYPE html>
<html>

<head>
  <meta charset="UTF-8" />
  <title>Index of <?php echo htmlentities(dirname($_SERVER["PHP_SELF"])); ?></title>
  <?php
  // if a specific diretory style has been specified, use it, else use the default css
  if (file_exists("./dirstyle.css")) {
  ?>
    <link rel="stylesheet" href="dirstyle.css">
  <?php
  } else {
  ?>
    <style type="text/css">
      body {
        background: #000000;
        margin: 3ex;
        color: #FFFFFF;
        font-size: medium;
        font-family: "Courier", monospace;
      }

      a:link,
      a:active,
      a:visited {
        background: none;
        color: white;
        text-decoration: none;
      }

      a:hover,
      tr:hover {
        background-color: #808080;
      }

      h1 {
        font-size: larger;
      }

      /* Index header */
      th {
        color: #FFFFFF;
        background: #404040;
      }

      /* copyright text at the bottom of the page */
      .copy {
        font-size: 8pt;
      }

      td.thumbnail img {
        border: 0;
      }

      td.size {
        text-align: right;
        white-space: pre;
        padding-left: 5ex;
      }

      td.date {
        padding-left: 5ex;
      }

      div.thumbnail {
        display: inline;
        background: #222;
        text-align: center;
      }
    </style>
  <?php
  }
  ?>
</head>

<body>

  <?php
  // if no header is specified, display the directory name
  if (!@include("./header.html")) {
    echo "<header><h1>Index of " . htmlentities(dirname($_SERVER["PHP_SELF"])) . "</h1></header>";
  }

  // Mode selection	
  switch ($mode) {
    default:
    case 'normal':
      // table headers
      echo '<table cellspacing="0"><tr><th colspan="2">File</th>';
      if ($show_filesize)
        echo "<th>Size</th>";
      if ($show_lastmod)
        echo "<th>Date</th>";
      echo "</tr>\n";

      foreach ($dircontent as $file) {
        echo '<tr class="fileitem">';
        // directories
        if (is_dir($file) and $show_dirs) {
          echo '<td><img src="' . getIcon('folder') . '" alt="[DIR]" /></td><td class="icon"><a href="' . $file . '/">' . $file . '</a></td>';
        }
        // files
        else if (is_file($file) and $show_files) {
          $type = getFileType($file);
          if ($type == 'image' and $doThumbs) {
            echo '<td class="thumbnail"><a href="' . $file . '"><img src="' . createThumb($file) . '" style="width:20px;height:20px;" alt="[THUMB]" /></a></td>';
          } else {
            echo '<td><img src="' . getIcon($type) . '" alt="[FILE]" /></td>';
          }
          echo '<td class="file"><a href="' . $file . '">' . $file . '</a></td>';
        }

        // both
        if (is_file($file) or is_dir($file)) {
          if ($show_filesize)
            echo '<td class="size">' . getFileSize($file) . "</td>";
          if ($show_lastmod)
            echo '<td class="date">' . getFileModTime($file) . '</td>';
        }

        echo "</tr>\n";
      }

      echo '</table>';
      break;


    case 'thumbnails':

      echo "<table>\n";
      echo "<tr>\n";

      $colCount = 0;
      foreach ($dircontent as $file) {
        if (is_dir($file)) {
          echo '<td style="background:#222;text-align:center;">';
          if ($file == "..")
            echo '<a href="' . $file . '/"><img src="' . getIcon('bigfolder') . '" alt="" style="color:white;background:none;font-size:12px;"/><br/>Up one level</a>';
          else
            echo '<a href="' . $file . '/"><img src="' . getIcon('bigfolder') . '" alt="" /><br/>' . $file . '</a>';
          echo "</td>\n";
        } else {
          $type = getFileType($file);
          switch ($type) {
            case 'image':
              echo '<td style="background:#222;text-align:center;"><a href="' . $file . '" style="border:0;font-size:12px;"><img src="' . createThumb($file) . '" alt="[Thumb]" /><br/>';
              echo $file;
              echo "</a></td>\n";
              break;
            default:
              // a non-image, subtract one from column index
              // to prevent it from getting fux0red
              $colCount--;
              break;
          }
        }
        $colCount++;
        if ($colCount >= 5) {
          echo "</tr>\n<tr>\n";
          $colCount = 0;
        }
      }

      echo "</tr>\n";
      echo "</table>\n";
      break;
    case 'thumbnails2':

      foreach ($dircontent as $file) {
        if (is_dir($file)) {
          echo '<div class="thumbnail">';
          if ($file == "..")
            echo '<a href="' . $file . '/"><img src="' . getIcon('bigfolder') . '" alt="" style="color:white;background:none;font-size:12px;"/><br/>Up one level</a>';
          else
            echo '<a href="' . $file . '/"><img src="' . getIcon('bigfolder') . '" alt="" /><br/>' . $file . '</a>';
          echo "</div>\n";
        } else {
          $type = getFileType($file);
          switch ($type) {
            case 'image':
              echo '<div class="thumbnail"><a href="' . $file . '" style="border:0;font-size:12px;"><img src="' . createThumb($file) . '" alt="[Thumb]" /><br/>';
              echo $file;
              echo "</a></div>\n";
              break;
            default:
              break;
          }
        }
      }
      break;
  }
  @include("./footer.html");
  ?>

  <footer>
    <p class="copy"><a href="https://github.com/lepinkainen/indexer">PHP Indexer <?php echo $verno; ?></a></p>
  </footer>

  <?php @include("./bottomfooter.html"); ?>

</body>

</html>