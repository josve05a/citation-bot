<?php
// $Id$
//TODO: $account_suffix is not declared, gives "Notice: Undefined variable"
ini_set("user_agent", "Citation_bot$account_suffix; citations@tools.wmflabs.org");
define('HOME', dirname(__FILE__) . '/');

// SVN revision ID for this file. FIXME: not using SVN anymore.
function expandFnsRevId() {
  return (int) trim(substr('$Id$', 19, 4));
}

function quiet_echo($text, $alternate_text = '') {
  global $html_output;
  if ($html_output >= 0)
    echo $text;
  else
    echo $alternate_text;
}

define("editinterval", 10);
define("PIPE_PLACEHOLDER", '%%CITATION_BOT_PIPE_PLACEHOLDER%%');
define("comment_placeholder", "### Citation bot : comment placeholder %s ###");
define("to_en_dash", "--?|\&mdash;|\xe2\x80\x94|\?\?\?"); // regexp for replacing to ndashes using mb_ereg_replace
define("blank_ref", "<ref name=\"%s\" />");
define("reflist_regexp", "~{{\s*[Rr]eflist\s*(?:\|[^}]+?)+(<ref[\s\S]+)~u");
define("en_dash", "\xe2\x80\x93"); // regexp for replacing to ndashes using mb_ereg_replace
define("wikiroot", "https://test.wikipedia.org/w/index.php?"); //FIXME in prod
define("api", "https://test.wikipedia.org/w/api.php"); //FIXME in prod
define("bibcode_regexp", "~^(?:" . str_replace(".", "\.", implode("|", Array(
                    "http://(?:\w+.)?adsabs.harvard.edu",
                    "http://ads.ari.uni-heidelberg.de",
                    "http://ads.inasan.ru",
                    "http://ads.mao.kiev.ua",
                    "http://ads.astro.puc.cl",
                    "http://ads.on.br",
                    "http://ads.nao.ac.jp",
                    "http://ads.bao.ac.cn",
                    "http://ads.iucaa.ernet.in",
                    "http://ads.lipi.go.id",
                    "http://cdsads.u-strasbg.fr",
                    "http://esoads.eso.org",
                    "http://ukads.nottingham.ac.uk",
                    "http://www.ads.lipi.go.id",
                ))) . ")/.*(?:abs/|bibcode=|query\?|full/)([12]\d{3}[\w\d\.&]{15})~");
//define("doiRegexp", "(10\.\d{4}/([^\s;\"\?&<])*)(?=[\s;\"\?&]|</)");
#define("doiRegexp", "(10\.\d{4}(/|%2F)[^\s\"\?&]*)(?=[\s\"\?&]|</)"); //Note: if a DOI is superceded by a </span>, it will pick up this tag. Workaround: Replace </ with \s</ in string to search.

require_once(HOME . "credentials/doiBot.login");
# Snoopy's ini files should be modified so the host name is en.wikipedia.org.
require_once('Snoopy.class.php');
require_once("DOItools.php");
require_once("Template.php");
require_once("Parameter.php");
require_once("objects.php");
require_once("wikiFunctions.php");

//Commented out because they seem to be not used or not functional
//includeIfNew("citewatchFns");
//require_once("expand.php");

//require_once(HOME . "credentials/mysql.login");
/* mysql.login is a php file containing:
  define('MYSQL_DBNAME', ...);
  define('MYSQL_SERVER', ...);
  define('MYSQL_PREFIX', ...);
  define('MYSQL_USERNAME', ...);
  define('MYSQL_PASSWORD', ...);
*/

require_once(HOME . "credentials/crossref.login");
/* crossref.login is a PHP file containing:
  <?php
  define('CROSSREFUSERNAME','martins@gmail.com');
  define('JSTORPASSWORD', ...);
  define('GLOBALPASSWORD', ...);
  define('JSTORUSERNAME', 'citation_bot');
  define('NYTUSERNAME', 'citation_bot');
*/

$crossRefId = CROSSREFUSERNAME;
$isbnKey = "268OHQMW";
$alphabet = array("", "a", "b", "c", "d", "e", "f", "g", "h", "i", "j", "k", "l", "m", "n", "o", "p", "q", "r", "s", "t", "u", "v", "w", "x", "y", "z");
mb_internal_encoding('UTF-8'); // Avoid ??s

//Common replacements
global $doiIn, $doiOut, $pcDecode, $pcEncode, $dotDecode, $dotEncode;
$doiIn = array("[", "]", "<", ">", "&#60;!", "-&#62;", "%2F");
$doiOut = array("&#x5B;", "&#x5D;", "&#60;", "&#62;", "<!", "->", "/");

$pcDecode = array("[", "]", "<", ">");
$pcEncode = array("&#x5B;", "&#x5D;", "&#60;", "&#62;");

$spurious_whitespace= array(""); // regexp for replacing spurious custom whitespace

$dotEncode = array(".2F", ".5B", ".7B", ".7D", ".5D", ".3C", ".3E", ".3B", ".28", ".29");
$dotDecode = array("/", "[", "{", "}", "]", "<", ">", ";", "(", ")");

// Common mistakes that aren't picked up by the levenshtein approach
$common_mistakes = array
(
  "albumlink"       =>  "titlelink",
  "artist"          =>  "others",
  "authorurl"       =>  "authorlink",
  "authorn"         =>  "author2",
#  "authors"         =>  "author",
  "co-author"       =>  "author2",
  "co-authors"      =>  "author2",
  "coauthors"       =>  "author2",
  "dio"             =>  "doi",
  "director"        =>  "others",
  "display-authors" =>  "displayauthors",
  "display_authors" =>  "displayauthors",
  "ed"              =>  "editor",
  "ed2"             =>  "editor2",
  "ed3"             =>  "editor3",
  "editorlink1"     =>  "editor1-link",
  "editorlink2"     =>  "editor2-link",
  "editorlink3"     =>  "editor3-link",
  "editorlink4"     =>  "editor4-link",
  "editor1link"     =>  "editor1-link",
  "editor2link"     =>  "editor2-link",
  "editor3link"     =>  "editor3-link",
  "editor4link"     =>  "editor4-link",
  "editor-first1"    =>  "editor1-first",
  "editor-first2"    =>  "editor2-first",
  "editor-first3"    =>  "editor3-first",
  "editor-first4"    =>  "editor4-first",
  "editor-last1"    =>  "editor1-last",
  "editor-last2"    =>  "editor2-last",
  "editor-last3"    =>  "editor3-last",
  "editor-last4"    =>  "editor4-last",
  "editorn"         =>  "editor2",
  "editorn-link"    =>  "editor2-link",
  "editorn-last"    =>  "editor2-last",
  "editorn-first"   =>  "editor2-first",
  "firstn"          =>  "first2",
  "ibsn"            =>  "isbn",
  "ibsn2"           =>  "isbn",
  "lastn"           =>  "last2",
  "part"            =>  "issue",
  "no"              =>  "issue",
  "No"              =>  "issue",
  "No."             =>  "issue",
  "notestitle"      =>  "chapter",
  "nurl"            =>  "url",
  "origmonth"       =>  "month",
  "p"               =>  "page",
  "p."              =>  "page",
  "pmpmid"          =>  "pmid",
  "pp"              =>  "pages",
  "pp."             =>  "pages",
  "publisherid"     =>  "id",
  "titleyear"       =>  "origyear",
  "translator"      =>  "others",
  "translators"     =>  "others",
  "vol"             =>  "volume",
  "Vol"             =>  "volume",
  "Vol."            =>  "volume",
  "website"         =>  "url",
);

//Optimisation
#ob_start(); //Faster, but output is saved until page finshed.
ini_set("memory_limit", "256M");

//TODO: none of these indices are declared, all give "Notice: undefined index"
$fastMode = $_REQUEST["fast"];
$slow_mode = $_REQUEST["slow"];
$user = $_REQUEST["user"];
$bugFix = $_REQUEST["bugfix"];
$crossRefOnly = $_REQUEST["crossrefonly"] ? true : $_REQUEST["turbo"];
$edit = $_REQUEST["edit"];

if ($edit || $_GET["doi"] || $_GET["pmid"])
  $ON = true;

$editSummaryStart = ($bugFix ? "Double-checking that a [[User talk:Citation bot|bug]] has been fixed. " : "Citations: ");

ob_end_flush();

quiet_echo("\n Establishing connection to Wikipedia servers with username " . USERNAME . "... ");
logIn(USERNAME, PASSWORD);
quiet_echo("\n Fetching parameter list ... ");
// Get a current list of parameters used in citations from WP
$page = $bot->fetch(api . "?action=query&prop=revisions&rvprop=content&titles=User:Citation_bot/parameters|Module:Citation/CS1/Whitelist&format=json");
$json = json_decode($bot->results, true);
$parameter_list = (explode("\n", $json["query"]["pages"][82740]["revisions"][0]["*"])); //FIXME, this is 26899494 on enwiki
preg_match_all("~\['([^']+)'\] = true~", $json["query"]["pages"][70802]["revisions"][0]["*"], $match); //FIXME, this is 39013723 on enwiki
foreach($match[1] as $parameter_name) {
  if (strpos($parameter_name, '#') !== FALSE) {
    for ($i = 1; $i < 100; $i++) {
      $replacement_name = str_replace('#', $i, $parameter_name);
      if (array_search($replacement_name, $parameter_list) === FALSE) {
        $parameter_list[] = $replacement_name;
      }
    }
  } else {
    if (array_search($parameter_name, $parameter_list) === FALSE) {
      $parameter_list[] = $parameter_name;
    }
  }
}

uasort($parameter_list, "ascii_sort");
quiet_echo("done.");

################ Functions ##############

function udbconnect($dbName = MYSQL_DBNAME, $server = MYSQL_SERVER) {
  // if the bot is trying to connect to the defunct toolserver
  if ($dbName == 'yarrow') {
    return ('\r\n # The maintainers have disabled database support.  This action will not be logged.');
  }

  // fix redundant error-reporting
  $errorlevel = ini_set('error_reporting','0');
  // connect
  $db = mysql_connect($server, MYSQL_USERNAME, MYSQL_PASSWORD) or die("\n!!! * Database server login failed.\n This is probably a temporary problem with the server and will hopefully be fixed soon.  The server returned: \"" . mysql_error() . "\"  \nError message generated by /res/mysql_connect.php\n");
  // select database
  if ($db && $server == "sql") {
     mysql_select_db(str_replace('-','_',MYSQL_PREFIX . $dbName)) or print "\nDatabase connection failed: " . mysql_error() . "";
  } else if ($db) {
     mysql_select_db($dbName) or die(mysql_error());
  } else {
    die ("\nNo DB selected!\n");
  }
  // restore error-reporting
  ini_set('error-reporting',$errorlevel);
  return ($db);
}

function updateBacklog($page) {
  # "-[#TODO unhandled DB request]-";
  return (NULL);
  $sPage = addslashes($page);
  $id = addslashes(articleId($page));
  $db = udbconnect("yarrow");
  $result = mysql_query("SELECT page FROM citation WHERE id = '$id'") or print (mysql_error());
  $result = mysql_fetch_row($result);
  $sql = $result ? "UPDATE citation SET fast = '" . date("c") . "', revision = '" . revisionID()
          . "' WHERE page = '$sPage'" : "INSERT INTO citation VALUES ('"
          . $id . "', '$sPage', '" . date("c") . "', '0000-00-00', '" . revisionID() . "')";
  $result = mysql_query($sql) or print (mysql_error());
  mysql_close($db);
}

function countMainLinks($title) {
  // Counts the links to the mainpage
  global $bot;
  if (preg_match("/\w*:(.*)/", $title, $title))
    $title = $title[1]; //Gets {{PAGENAME}}
  $url = "http://en.wikipedia.org/w/api.php?action=query&bltitle=" . urlencode($title) . "&list=backlinks&bllimit=500&format=yaml";
  $bot->fetch($url);
  $page = $bot->results;
  if (preg_match("~\n\s*blcontinue~", $page))
    return 501;
  preg_match_all("~\n\s*pageid:~", $page, $matches);
  return count($matches[0]);
}

function logIn($username, $password) {
  global $bot; // Snoopy class loaded in DOItools.php
  // Set POST variables to retrieve a token
  $submit_vars["format"] = "json";
  $submit_vars["action"] = "login";
  $submit_vars["lgname"] = $username;
  $submit_vars["lgpassword"] = $password;
  // Submit POST variables and retrieve a token
  $bot->submit(api, $submit_vars);
  $first_response = json_decode($bot->results);
  $submit_vars["lgtoken"] = $first_response->login->token;
  // Store cookies; resubmit with new request (which hast token added to post vars)
  foreach ($bot->headers as $header) {
    if (substr($header, 0, 10) == "Set-Cookie") {
      $cookies = explode(";", substr($header, 12));
      if ($cookies) foreach ($cookies as $oCook) {
        $cookie = explode("=", $oCook);
        $bot->cookies[trim($cookie[0])] = $cookie[1];
      }
    }
  }

  $bot->submit(api, $submit_vars);
  $login_result = json_decode($bot->results);
  if ($login_result->login->result == "Success") {
    quiet_echo("\n Using account " . $login_result->login->lgusername . ".");
    // Add other cookies, which are necessary to remain logged in.
    $cookie_prefix = "testwiki"; //FIXME in prod
    $bot->cookies[$cookie_prefix . "UserName"] = $login_result->login->lgusername;
    $bot->cookies[$cookie_prefix . "UserID"] = $login_result->login->lguserid;
    $bot->cookies[$cookie_prefix . "Token"] = $login_result->login->lgtoken;
    return true;
  } else {
    exit("\nCould not log in to Wikipedia servers.  Edits will not be committed.\n"); // Will not display to user (not sure this is true)
    global $ON;
    $ON = false;
    return false;
  }
}

function inputValue($tag, $form) {
  //Gets the value of an input, if the input's in the right format.
  preg_match("~value=\"([^\"]*)\" name=\"$tag\"~", $form, $name);
  if ($name)
    return $name[1];
  preg_match("~name=\"$tag\" value=\"([^\"]*)\"~", $form, $name);
  if ($name)
    return $name[1];
  return false;
}

function format_title_text($title) {
  $title = capitalize_title($title, TRUE);
  $title = html_entity_decode($title, null, "UTF-8");
  $title = (mb_substr($title, -1) == ".")
            ? mb_substr($title, 0, -1)
            :(
              (mb_substr($title, -6) == "&nbsp;")
              ? mb_substr($title, 0, -6)
              : $title
            );
  $title = preg_replace('~[\*]$~', '', $title);
  $iIn = array("<i>","</i>", '<title>', '</title>',
              "From the Cover: ", "|");
  $iOut = array("''","''",'','',
                "", '{{!}}');
  $in = array("&lt;", "&gt;"	);
  $out = array("<",		">"			);
  return(str_ireplace($iIn, $iOut, str_ireplace($in, $out, capitalize_title($title)))); // order IS important!
}

function parameters_from_citation($c) {
  // Comments
  global $comments, $comment_placeholders;
  $i = 0;
  while(preg_match("~<!--.*?-->~", $c, $match)) {
    $comments[] = $match[0];
    $comment_placeholders[] = sprintf(comment_placeholder, $i);
    $c = str_replace($match[0], $comment_placeholders[$i++], $c);
  }
  while (preg_match("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", $c)) {
    $c = preg_replace("~(?<=\{\{)([^\{\}]*)\|(?=[^\{\}]*\}\})~", "$1" . PIPE_PLACEHOLDER, $c);
  }
  // Split citation into parameters
  $parts = preg_split("~([\n\s]*\|[\n\s]*)([\w\d-_]*)(\s*= *)~", $c, -1, PREG_SPLIT_DELIM_CAPTURE);
  $partsLimit = count($parts);
  if (strpos($parts[0], "|") > 0
          && strpos($parts[0], "[[") === FALSE
          && strpos($parts[0], "{{") === FALSE
  ) {
    $p["unused_data"][0] = substr($parts[0], strpos($parts[0], "|") + 1);
  }
  for ($partsI = 1; $partsI <= $partsLimit; $partsI += 4) {
    $value = $parts[$partsI + 3];
    $pipePos = strpos($value, "|");
    if ($pipePos > 0 && strpos($value, "[[") === false & strpos($value, "{{") === FALSE) {
      // There are two "parameters" on one line.  One must be missing an equals.
      switch (strtolower($parts[$partsI + 1])) {
        case 'title': 
          $value = str_replace('|', '&#124;', $value);
          break;
        case 'url':
          $value = str_replace('|', '%7C', $value);
          break;
        default:
        $p["unused_data"][0] .= " " . substr($value, $pipePos);
        $value = substr($value, 0, $pipePos);
      }
    }
    // Load each line into $p[param][0123]
    $weight += 32;
    $p[strtolower($parts[$partsI + 1])] = Array($value, $parts[$partsI], $parts[$partsI + 2], "weight" => $weight); // Param = value, pipe, equals
  }
  return $p;
}

function reassemble_citation($p, $sort = false) {
  global $comments, $comment_placeholders, $pStart, $modifications;
  // Load an exemplar pipe and equals symbol to deduce the parameter spacing, so that new parameters match the existing format
  foreach ($p as $oP) {
    $pipe = $oP[1] ? $oP[1] : null;
    $equals = $oP[2] ? $oP[2] : null;
    if ($pipe)
      break;
  }
  if (!$pipe) $pipe = "\n | ";
  if (!$equals) $equals = " = ";
  if ($sort) {
    echo "\n (sorting parameters)";
    uasort($p, "bubble_p");
  }

  foreach ($p as $param => $v) {
    $val = trim(str_replace($comment_placeholders, $comments, $v[0]));
    if ($param == 'unused_data') {
      $cText .= ($v[1] ? $v[1] : $pipe) . $val;
    } elseif ($param) {
      $this_equals = ($v[2] ? $v[2] : $equals);
      if (trim($v[0]) && preg_match("~[\r\n]~", $this_equals)) {
        $this_equals = preg_replace("~[\r\n]+\s*$~", "", $this_equals);
        $nline = "\r\n";
      } else {
        $nline = null;
      }
      $cText .= ( $v[1] ? $v[1] : $pipe)
              . $param
              . $this_equals
              . str_replace(array(PIPE_PLACEHOLDER, "\r", "\n"), array("|", "", " "), $val)
              . $nline;
    }
    if (is($param)) {
      $pEnd[$param] = $v[0];
    }
  }
  if ($pEnd) {
    foreach ($pEnd as $param => $value) {
      if (!$pStart[$param]) {
        $modifications["additions"][$param] = true;
      } elseif ($pStart[$param] != $value) {
        $modifications["changes"][$param] = true;
      }
    }
  }
  return $cText;
}


function noteDoi($doi, $src) {
  quiet_echo("<h3 style='color:coral;'>Found <a href='http://dx.doi.org/$doi'>DOI</a> $doi from $src.</h3>");
}

// Error codes:
// 404 is a working DOI pointing to a page not found;
// 200 is a broken DOI, found in the source of the URL
// Broken DOIs are only logged if they can be spotted in the URL page specified.

function loadParam($param, $value, $equals, $pipe, $weight) {
  global $p;
  $param = strtolower(trim(str_replace("DUPLICATE DATA:", "", $param)));
  if ($param == "unused_data") {
    $value = trim(str_replace("DUPLICATE DATA:", "", $value));
  }
  if (is($param)) {
    if (substr($param, strlen($param) - 1) > 0 && trim($value) != trim($p[$param][0])) {
      // Add one to last1 to create last2
      $param = substr($param, 0, strlen($param) - 1) . (substr($param, strlen($param) - 1) + 1);
    } else {
      // Parameter already exists
      if ($param != "unused_data" && $p[$param][0] != $value) {
        // If they have different values, best keep them; if not: discard the exact duplicate!
        $param = "DUPLICATE DATA: $param";
      }
    }
  }
  $p[$param] = Array($value, $equals, $pipe, "weight" => ($weight + 3) / 4 * 10); // weight will be 10, 20, 30, 40 ...
}

function cite_template_contents($type, $id) {
  $page = get_template_prefix($type);
  $replacement_template_name = $page . wikititle_encode($id);
  $text = getRawWikiText($replacement_template_name);
  if (!$text) {
    return false;
  } else {
    return extract_parameters(extract_template($text, "cite journal"));
  }
}

function create_cite_template($type, $id) {
  $page = get_template_prefix($type);
  return expand($page . wikititle_encode($id), true, true, "{{Cite journal\n | $type = $id \n}}<noinclude>{{Documentation|Template:cite_$type/subpage}}</noinclude>");
}

function get_template_prefix($type) {
  return "Template: Cite "
  . ($type == "jstor" ? ("doi/10.2307" . wikititle_encode("/")) : $type . "/");
  // Not sure that this works:
  return "Template: Cite $type/";
  // Do we really need to handle JSTORs differently?
  // The below code errantly produces cite jstor/10.2307/JSTORID, not cite jstor/JSTORID.
  return "Template: Cite "
  . ($type == "jstor" ? ("jstor/10.2307" . wikititle_encode("/")) : $type . "/");
}

function standardize_reference($reference) {
  $whitespace = Array(" ", "\n", "\r", "\v", "\t");
  return str_replace($whitespace, "", $reference);
}

// $comments should be an array, with the original comment content.
// $placeholder will be prepended to the comment number in the sprintf to comment_placeholder's %s.
function replace_comments($text, $comments, $placeholder = "") {
  foreach ($comments as $i => $comment) {
    $text = str_replace(sprintf(comment_placeholder, $placeholder . $i), $comment, $text);
  }
  return $text;
}

// This function may need to be called twice; the second pass will combine <ref name="Name" /> with <ref name=Name />.
function combine_duplicate_references($page_code) {
  
  $original_encoding = mb_detect_encoding($page_code);
  $page_code = mb_convert_encoding($page_code, "UTF-8");
  
  if (preg_match_all("~<!--[\s\S]*?-->~", $page_code, $match)) {
    $removed_comments = $match[0];
    foreach ($removed_comments as $i => $content) {
      $page_code = str_replace($content, sprintf(comment_placeholder, "sr$i"), $page_code);
    }
  }
  // Before we start with the page code, find and combine references in the reflist section that have the same name
  if (preg_match(reflist_regexp, $page_code, $match)) {
    if (preg_match_all('~(?P<ref1><ref\s+name\s*=\s*'
            . '(?<quote1>["\']?+)(?P<name>[^/>]+)(?P=quote1)'
            . '(?:\s[^/>]+)?\s*>[\p{L}\P{L}]+</\s*ref>)'
            . '[\p{L}\P{L}]+'
            . '(?P<ref2><ref\s+name\s*=\s*(?P<quote2>["\']?+)(?P=name)\b(?P=quote2)(?:\s[^/>]+)?\s*>'
              . '.+</\s*ref>)~isuU', $match[1], $duplicates)) {
      foreach ($duplicates['ref2'] as $i => $to_delete) {
        print "\n\n$to_delete\n\n===";
        if ($to_delete == $duplicates['ref1'][$i]) {
          $mb_start = mb_strpos($page_code, $to_delete) + mb_strlen($to_delete);
          $page_code = mb_substr($page_code, 0, $mb_start)
                  . str_replace($to_delete, '', mb_substr($page_code, $mb_start));
        } else {
          $page_code = str_replace($to_delete, '', $page_code);
        }
        echo "\n  * deleted duplicate reference: $to_delete";
      }
    } 
  }
  // Now look at the rest of the page:
  preg_match_all("~<ref\s*name\s*=\s*(?P<quote>[\"']?)([^>]+)(?P=quote)\s*/>~", $page_code, $empty_refs);
  // match 1 = ref names
  if (preg_match_all("~<ref(\s*name\s*=\s*(?P<quote>[\"']?)([^>]+)(?P=quote)\s*)?>"
                  . "(([^<]|<(?![Rr]ef))+?)</ref>~i", $page_code, $refs)) {
    // match 0 = full ref; 1 = redundant; 2= used in regexp for backreference;
    // 3 = ref name; 4 = ref content; 5 = redundant
    foreach ($refs[4] as $ref) {
      $standardized_ref[] = standardize_reference($ref);
    }
    // Turn essentially-identical references into exactly-identical references
    foreach ($refs[4] as $i => $this_ref) {
      if (false !== ($key = array_search(standardize_reference($this_ref), $standardized_ref))
              && $key != $i) {
        $full_original[] = ">" . $refs[4][$key] . "<"; // be careful; I hope that this is specific enough.
        $duplicate_content[] = ">" . $this_ref . "<";
      }
      print_r($duplicate_content); print_r($full_original);
      $page_code = str_replace($duplicate_content, $full_original, $page_code);
    }
  } else {
    // no matches, return input
    echo "\n - No references found.";
    return mb_convert_encoding(replace_comments($page_code, $removed_comments, 'sr'), $original_encoding);
  }

  // Reset
  $full_original = null;
  $duplicate_content = null;
  $standardized_ref = null;

  // Now all references that need merging will have identical content.  Proceed to do the replacements...
  if (preg_match_all("~<ref(\s*name\s*=\s*(?P<quote>[\"']?)([^>]+)(?P=quote)\s*)?>"
                  . "(([^<]|<(?!ref))+?)</ref>~i", $page_code, $refs)) {
    $standardized_ref = $refs[4]; // They were standardized above.
    
    foreach ($refs[4] as $i => $content) {
      if (false !== ($key = array_search($refs[4][$i], $standardized_ref))
              && $key != $i) {
        $full_original[] = $refs[0][$key];
        $full_duplicate[] = $refs[0][$i];
        $name_of_original[] = $refs[3][$key];
        $name_of_duplicate[] = $refs[3][$i];
        $duplicate_content[] = $content;
        $name_for[$content] = $name_for[$content] ? $name_for[$content] : ($refs[3][$key] ? $refs[3][$key] : ($refs[3][$i] ? $refs[3][$i] : null));
      }
    }
    $already_replaced = Array(); // so that we can use FALSE and not NULL in the check...
    if ($full_duplicate) {
      foreach ($full_duplicate as $i => $this_duplicate) {
        if (FALSE === array_search($this_duplicate, $already_replaced)) {
          $already_replaced[] = $full_duplicate[$i]; // So that we only replace the same reference once
          echo "\n   - Replacing duplicate reference $this_duplicate. \n     Reference name: "
          . ( $name_for[$duplicate_content[$i]] ? $name_for[$duplicate_content[$i]] : "Autogenerating." ); // . " (original: $full_original[$i])";
          $replacement_template_name = $name_for[$duplicate_content[$i]] ? $name_for[$duplicate_content[$i]] : get_name_for_reference($duplicate_content[$i], $page_code);
          // First replace any empty <ref name=Blah content=none /> or <ref name=Blah></ref> with the new name
          $ready_to_replace = preg_replace("~<ref\s*name\s*=\s*(?P<quote>[\"']?)"
                  . preg_quote($name_of_duplicate[$i])
                  . "(?P=quote)(\s*/>|\s*>\s*</\s*ref>)~"
                  , "<ref name=\"" . $replacement_template_name . "\"$2"
                  , $page_code);
          if ($name_of_original[$i]) {
            // Don't replace the original template!
            $original_ref_end_pos = mb_strpos($ready_to_replace, $full_original[$i]) + mb_strlen($full_original[$i]);
            $code_upto_original_ref = mb_substr($ready_to_replace, 0, $original_ref_end_pos);
          } elseif ($name_of_duplicate[$i]) {
            // This is an odd case; in a fashion the simplest.
            // In effect, we switch the original and duplicate over,..
            $original_ref_end_pos = 0;
            $code_upto_original_ref = "";
            $already_replaced[] = $full_original[$i];
            $this_duplicate = $full_original[$i];
          } else {
            // We need add a name to the original template, and not to replace it
            $original_ref_end_pos = mb_strpos($ready_to_replace, $full_original[$i]);
            $code_upto_original_ref = mb_substr($ready_to_replace, 0, $original_ref_end_pos) // Sneak this in to "first_duplicate"
                    . preg_replace("~<ref(\s+name\s*=\s*(?P<quote>[\"']?)" . preg_quote($name_of_original[$i])
                            . "(?P=quote)\s*)?>~i", "<ref name=\"$replacement_template_name\">", $full_original[$i]);
            $original_ref_end_pos += mb_strlen($full_original[$i]);
          }
          // Then check that the first occurrence won't be replaced
          $page_code = $code_upto_original_ref . str_replace($this_duplicate,
                    sprintf(blank_ref, $replacement_template_name), mb_substr($ready_to_replace, $original_ref_end_pos));
          global $modifications;
          $modifications["combine_references"] = true;
        }
      }
    }
  }

  $page_code = replace_comments($page_code, $removed_comments, 'sr');
  echo ($already_replaced) ? "\n - Combined duplicate references." : "\n   - No duplicate references to combine." ;
  return $page_code;
}


function trim_identifier($id) {
  $cruft = "[\.,;:><\s]*";
  preg_match("~^$cruft(?:d?o?i?:)?\s*(.*?)$cruft$~", $id, $match);
  return $match[1];
}

function name_references($page_code) {
  echo " naming";
  if (preg_match_all("~<ref>[^\{<]*\{\{\s*(?=[cC]it|[rR]ef).*</ref>~U", $page_code, $refs)) {
    foreach ($refs[0] as $ref) {
      $ref_name = get_name_for_reference($ref, $page_code);
      if (substr($ref_name, 0, 4) != "ref_") {
        // i.e. we have used an interesting reference name
        $page_code = str_replace($ref, str_replace("<ref>", "<ref name=\"$ref_name\">", $ref), $page_code);
      }
      echo ".";
    }
  }
  return $page_code;
}

function remove_accents($input) {
  $search = explode(",", "�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,�,e,i,�,u");
  $replace = explode(",", "c,ae,oe,a,e,i,o,u,a,e,i,o,u,a,e,i,o,u,y,a,e,i,o,u,a,e,i,o,u");
  return str_replace($search, $replace, $input);
}

function under_two_authors ($text) {
  return !(strpos($text, ';') !== FALSE  //if there is a semicolon
          || substr_count($text, ',') > 1  //if there is more than one comma
          || substr_count($text, ',') < substr_count(trim($text), ' ')  //if the number of commas is less than the number of spaces in the (whitespace-stripped) string
          );
}

// returns the surname of the authors.
function authorify($author) {
  $author = preg_replace("~[^\s\w]|\b\w\b|[\d\-]|\band\s+~", "", normalize_special_characters(html_entity_decode(urldecode($author), ENT_COMPAT, "UTF-8")));
  $author = preg_match("~[a-z]~", $author) ? preg_replace("~\b[A-Z]+\b~", "", $author) : strtolower($author);
  return $author;
}

function sanitize_string($str) {
  // ought only be applied to newly-found data.
  $dirty = array ('[', ']', '|', '{', '}');
  $clean = array ('&#91;', '&#93;', '&#124;', '&#123;', '&#125;');
  return trim(str_replace($dirty, $clean, preg_replace('~[;.,]+$~', '', $str)));
}

function prior_parameters($par, $list=array()) {
  array_unshift($list, $par);
  if (preg_match('~(\D+)(\d+)~', $par, $match)) {
    switch ($match[1]) {
      case 'first': case 'initials': case 'forename':
        return array('last' . $match[2], 'surname' . $match[2]);
      case 'last': case 'surname': 
        return array('first' . ($match[2]-1), 'forename' . ($match[2]-1), 'initials' . ($match[2]-1));
      default: return array($match[1] . ($match[2]-1));
    }
  }
  switch ($par) {
    case 'title':       return prior_parameters('author', array_merge(array('author', 'authors', 'author1', 'first1', 'initials1'), $list) );
    case 'journal':       return prior_parameters('title', $list);
    case 'volume':       return prior_parameters('journal', $list);
    case 'issue': case 'number':       return prior_parameters('volume', $list);
    case 'page' : case 'pages':       return prior_parameters('issue', $list);

    case 'pmid':       return prior_parameters('doi', $list);
    case 'pmc':       return prior_parameters('pmid', $list);
    default: return $list;
  }
}

// Function from http://stackoverflow.com/questions/1890854
// Modified to expect utf8-encoded string
function normalize_special_characters($str) {
  $str = utf8_decode($str);
  # Quotes cleanup
  $str = ereg_replace(chr(ord("`")), "'", $str);        # `
  $str = ereg_replace(chr(ord("�")), "'", $str);        # �
  $str = ereg_replace(chr(ord("�")), ",", $str);        # �
  $str = ereg_replace(chr(ord("`")), "'", $str);        # `
  $str = ereg_replace(chr(ord("�")), "'", $str);        # �
  $str = ereg_replace(chr(ord("�")), "\"", $str);        # �
  $str = ereg_replace(chr(ord("�")), "\"", $str);        # �
  $str = ereg_replace(chr(ord("�")), "'", $str);        # �

  $unwanted_array = array('�' => 'S', '�' => 's', '�' => 'Z', '�' => 'z', '�' => 'A', '�' => 'A', '�' => 'A', '�' => 'A', '�' => 'A', '�' => 'A', '�' => 'A', '�' => 'C', '�' => 'E', '�' => 'E',
      '�' => 'E', '�' => 'E', '�' => 'I', '�' => 'I', '�' => 'I', '�' => 'I', '�' => 'N', '�' => 'O', '�' => 'O', '�' => 'O', '�' => 'O', '�' => 'O', '�' => 'O', '�' => 'U',
      '�' => 'U', '�' => 'U', '�' => 'U', '�' => 'Y', '�' => 'B', '�' => 'Ss', '�' => 'a', '�' => 'a', '�' => 'a', '�' => 'a', '�' => 'a', '�' => 'a', '�' => 'a', '�' => 'c',
      '�' => 'e', '�' => 'e', '�' => 'e', '�' => 'e', '�' => 'i', '�' => 'i', '�' => 'i', '�' => 'i', '�' => 'o', '�' => 'n', '�' => 'o', '�' => 'o', '�' => 'o', '�' => 'o',
      '�' => 'o', '�' => 'o', '�' => 'u', '�' => 'u', '�' => 'u', '�' => 'y', '�' => 'y', '�' => 'b', '�' => 'y');
  $str = strtr($str, $unwanted_array);

# Bullets, dashes, and trademarks
  $str = ereg_replace(chr(149), "&#8226;", $str);    # bullet �
  $str = ereg_replace(chr(150), "&ndash;", $str);    # en dash
  $str = ereg_replace(chr(151), "&mdash;", $str);    # em dash
  $str = ereg_replace(chr(153), "&#8482;", $str);    # trademark
  $str = ereg_replace(chr(169), "&copy;", $str);    # copyright mark
  $str = ereg_replace(chr(174), "&reg;", $str);        # registration mark

  return utf8_encode($str);
}

function ascii_sort($val_1, $val_2) {
  $return = 0;
  $len_1 = strlen($val_1);
  $len_2 = strlen($val_2);

  if ($len_1 > $len_2) {
    $return = -1;
  } else if ($len_1 < $len_2) {
    $return = 1;
  }
  return $return;
}

?>
