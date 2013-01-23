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
      $controller->register_hook('CHECK_ACL', 'BEFORE', $this, 'handle_magicacl2');
    }
    
    function handle_magicacl2(&$event, $param) {
      $debug = true;
      global $ID;
      global $AUTH_ACL;
      global $INFO;
      global $USERINFO;

      $dodebug = false;
      if($debug && $_GET['doit']) {
         $dodebug = true;
      }
      //print "DEBUG: ". $_SERVER['REMOTE_ADDR'];
//      if($_SERVER['REMOTE_ADDR'] == '134.84.199.3') {
//        $dodebug = true;
//      }

      $ns = $event->data['ns'];

      if ($dodebug) {
        print "<pre>\n\n";
        print "physmagicACL pluggin hook running. ns=$ns\n";
      }

      $areas     = $this->getConf('areas');

      if (!count($areas)) return;

      foreach ($areas as $magicns => $area) {
            //$magicns = $area['ns_base'];
            $level = $area['level'];
            $cust_acl = $area['acl'];
            //$perm = $area['perm_level'];
            //$groupbase = $area['group_base'];
            if($dodebug) {
                print "Looking at physmagicacl config area:$magicns\n";
            }

            if (!$cust_acl || !$level) {
                if($dodebug) {
                    print "DEBUG: bailing because no cust_acl or no level\n";
                }
                continue;
            }

            $namespaces = Array();
            // continue only if $magicns area is a substring of $ns and theres no weird chars in $ns
            if ( preg_match("/^$magicns/", $ns) && (count(preg_grep('/^[*:\-\.\w]*$/',array($ns))))) {
                  // Check for any groups that match the namespace
                  $groups = $USERINFO['grps'];
                  foreach($groups as $group) {
                        $area_slice = explode('_', $group, $level);
                        if (count($area_slice) == $level && $area_slice[0] == $magicns) {
                          $namespace['ns'] = implode(':', $area_slice);
                          $namespace['group'] = $group;
                          if ($dodebug) {
                            print "Adding ns " . $namespace['ns'] . " and group $group\n";
                          }
                          $namespaces[] = $namespace;
                        }
                  }
                  // Take the current ns, explode it to the specified level
                  // and implode it back to get the namespace and group for ACL
                  $magicns = array_slice(explode(':', $ns), 0, $level);
                  if($dodebug) {
                      foreach ($magicns as $test) {
                        if($test == '*') {
                            print "Yep, $ns has a * in it\n";
                        }
                      }
                  }
                  $rootns = implode(':', $magicns);
                  $group  = ($groupbase ? $groupbase : '') . implode('_', $magicns);

                  // Update ACL

                  if ($cust_acl) {
                        foreach ($cust_acl as $entry) {
                          if (!empty($entry)) {
                            $entryarr = preg_split('/\s+/', $entry);
                            if ($dodebug) {
                              //print "entryarr: ";
                              //print_r($entryarr);
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
                  // If this isn't the mediamanager, we may need to update page permisions
                }
            else {
                if($dodebug) {
                    print "Skipped area because its not in ns: $ns or ns is invalid\n";
                }
            }
          }

          if ($dodebug) {
            print '</pre><br>';
          }
    } /* end foreach */
}
//Setup VIM: ex: et ts=4 enc=utf-8 :
