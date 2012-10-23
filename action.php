<?php
/**
 * Action Plugin:   Magic ACL Area. Convert ns/id to group name
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Andy Clayton <clayton@physics.umn.edu>  
 */

/*
 * TODO:
 *
 *   Handle AJAX calls correctly (would need adding an extra event or something.
 *     the best solution would be an AUTH_LOADED type event in auth.php)
 *
 */

// must be run within Dokuwiki
if(!defined('DOKU_INC')) die();

if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once(DOKU_PLUGIN.'action.php');

/**
 * All DokuWiki action plugins need to inherit from this class
 */
class action_plugin_physmagicacl extends DokuWiki_Action_Plugin {

    /**
     * return some info
     */
    function getInfo(){
      return array(
        'author' => 'Andy Clayton',
        'email'  => 'clayton@physics.umn.edu',
        'date'   => '2008-02-26',
        'name'   => 'Magic ACL',
        'desc'   => 'Convert id/ns to group name ACL magicness',
        'url'    => 'about:blank',
      );
    }
    
    /*
     * plugin should use this method to register its handlers with the dokuwiki's event controller
     */
    function register(&$controller) {
      $controller->register_hook('DOKUWIKI_STARTED','BEFORE', $this, 'handle_magicacl');
      $controller->register_hook('MEDIAMANAGER_STARTED','BEFORE', $this, 'handle_magicacl', 'mediamanager');
      $controller->register_hook('MEDIA_SENDFILE_PREPROCESS','BEFORE', $this, 'handle_magicacl', 'sendfile');
    }
    
    function handle_magicacl(&$event, $param) {
      $debug = true;
      global $ID;
      global $AUTH_ACL;
      global $INFO;
      global $USERINFO;


      if ($debug && $_GET['doit']) {
        print '<pre>Debugging it' . "\n";
      }

      $areas     = $this->getConf('areas');

      if ($debug && $_GET['doit']) {
        print 'Area Count: ' . count($areas) . "\n";
        print 'Plugin Name: ' . $this->getPluginName() . "\n";
      }

      if (!count($areas)) return;

      // We need to do some specialness to get the namespace if this is the media manager
      if ($param == 'mediamanager') {
        // Adapted from the start of lib/exe/mediamanager.php
        if($_REQUEST['delete']){
            $DEL = cleanID($_REQUEST['delete']);
            $NS  = getNS($DEL);
        }elseif($_REQUEST['edit']){
            $IMG = cleanID($_REQUEST['edit']);
            $NS  = getNS($IMG);
        }elseif($_REQUEST['img']){
            $IMG = cleanID($_REQUEST['img']);
            $NS  = getNS($IMG);
        }else{
            $NS = $_REQUEST['ns'];
            $NS = cleanID($NS);
        }
        $ns = $NS;
      }
      elseif($param == 'sendfile') {
        $MEDIA  = cleanID(stripctl(getID('media',false)));
        $ns = $MEDIA;
      } else {
        $ns = $ID;
      }

      if ($debug && $_GET['doit']) {
        print 'Area: ' . $ns . "\n";
	print '$areas: '."\n";
	print_r($areas);
	print "\n";
      }


      foreach ($areas as $magicns => $area) {
        //$magicns = $area['ns_base'];
        $level = $area['level'];
        $cust_acl = $area['acl'];
        //$perm = $area['perm_level'];
        //$groupbase = $area['group_base'];
        if($debug && $_GET['doit']) {
            print "DEBUG: In foreach. magicns=$magicns, area=$area. level=$level, cust_acl=$cust_acl, ns=$ns<BR>\n";
        }

        if (!$cust_acl || !$level) continue;

        // Only continue if we are in magic land
        // (and magic land doesn't have any odd characters...)
        if ((stripos($ns, $magicns) === 0) && (count(preg_grep('/^[:\-\.\w]*$/',array($ns))))) {
          // Check for any groups that match the namespace
          $groups = $USERINFO['grps'];
          foreach($groups as $group) {
            $area_slice = explode('_', $group, $level);
            if ($debug && $_GET['doit']) {
              print "group: $group, slices: ";
              print_r($area_slice);
            }
            if (count($area_slice) == $level && $area_slice[0] == $magicns) {
              $namespace['ns'] = implode(':', $area_slice);
              $namespace['group'] = $group;
              $namespaces[] = $namespace;
              if ($debug && $_GET['doit']) {
                print "Adding ns " . $namespace['ns'] . " and group $group\n";
              }
            }
          }
          // Take the current ns, explode it to the specified level
          // and implode it back to get the namespace and group for ACL
          $magicns = array_slice(explode(':', $ns), 0, $level);
          $rootns = implode(':', $magicns);
          $group  = ($groupbase ? $groupbase : '') . implode('_', $magicns);
          if ($debug && $_GET['doit']) {
            print "group: $group, rootns: $rootns, magicns: ";
            print_r($magicns);
          }

          // Update ACL

          if ($cust_acl) {
            foreach ($cust_acl as $entry) {
              if (!empty($entry)) {
                $entryarr = preg_split('/\s+/', $entry);
                if ($debug && $_GET['doit']) {
                  print "entryarr: ";
                  print_r($entryarr);
                }
                if (count($entryarr) >= 3) {
                  $AUTH_ACL[] = $rootns . $entryarr[0] . ' ' . auth_nameencode(str_replace('%GROUP%', $group, $entryarr[1]), true) . ' ' . $entryarr[2];
                  foreach ($namespaces as $aclns) {
                    $AUTH_ACL[] = $aclns['ns'] . $entryarr[0] . ' ' . auth_nameencode(str_replace('%GROUP%', $aclns['group'], $entryarr[1]), true) . ' ' . $entryarr[2];
                  }
                }
              }
            }
          }
          if ($debug && $_GET['doit']) {
            print 'Dumping: ';
            print_r($AUTH_ACL);
            print_r($USERINFO['grps']);
          }

          // If this isn't the mediamanager, we may need to update page permisions
          if ($param != 'mediamanager') {
              // Check (possibly new) permissions
              $new_perm = auth_quickaclcheck($ID);

              if ($debug && $_GET['doit']) {
                  print $new_perm . "OLD" . $INFO['perm'];
              }

              // Update page permissions if they are now higher.
              // We could just do $INFO = pageinfo(), but there's a lot of overhead there
              if ($new_perm > $INFO['perm'])
              {

                $INFO['perm'] = $new_perm;

                // stolen from auth.php...
                if($INFO['exists']){
                  $INFO['writable'] = (is_writable($INFO['filepath']) &&
                                      ($INFO['perm'] >= AUTH_EDIT));
                }else{
                  $INFO['writable'] = ($INFO['perm'] >= AUTH_CREATE);
                }
                $INFO['editable']  = ($INFO['writable'] && empty($INFO['lock']));
              }
          }
        }
      }

      if ($debug && $_GET['doit']) {
        print '</pre>';
      }
   }
}

//Setup VIM: ex: et ts=4 enc=utf-8 :
