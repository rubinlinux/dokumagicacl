====== MagicACL ======

Originally written for our class wikis, the MagicACL dokuwiki plugin allows acl rule generation based on the current namespace/page in the wiki. This is most useful with modified dokuwiki auth modules which generate dokuwiki group membership automatically based upon various directory/mysql db properties (eg. ldap groups become dokuwiki groups become wiki page for that group whose members can view/modify them).

<note warning>
This plugin depends on patching dokuwiki, if access to media files is to work correctly.
The patch was submitted to dokuwiki back in 2010 but as of October 2012 they still haven't done anything with it.

See https://bugs.dokuwiki.org/index.php?do=details&task_id=2103
</note>

Sorry for the wiki formatting in a readme... the following is ported from our internal
documentation:
===== Configuration =====

Configuration is done in conf/local.php as with all dokuwiki bits.

Starting with an example:

<code>
$conf['plugin']['magicacl']['areas']['classes'] = array('level' => 4, 'acl' => array(':* @%GROUP% 16', ':home @%GROUP% 0', ':home @%GROUP%_instructor 16',':home @%GROUP%_ta 16'));
$conf['plugin']['magicacl']['areas']['people'] = array('level' => 2, 'acl' => array(':* @%GROUP% 16', ':home @%GROUP% 16'));
</code>

This (as of September 10, 2008) is the MagicACL configuration for the primary wiki.

MagicACL places each namespace and corresponding rule set under ''areas'' in the magicacl configuration. From this example there are currently two defined areas: ''classes'' and ''people''. These represent the automatic class wikis and personal pages, respectively. This name is important as it corresponds to the namespace of the wiki where the rules will be active, but with the leading colon stripped off. So for classes all the rules will only apply under :classes. (But the areas do not have to be top level: ''groups:awesomephys:people'' could also be used as a name if the awesomephys group wanted their own personal pages inside their area, for example.)

Inside each area there are currently two configuration options: ''level'' and ''acl'':

**''level''** defines the number of namespace levels deep to put the magic at. Perhaps an example will sound significantly less sexual: For the class pages we want each class to be under '':classes://year//://quarter//://classname//''. This means that the magicacl action needs to start at :classes, yet we want all the way down to classname to be used for writing the acl rules. So as shown above in the example classes has a level of 4. For people, however, all we need is '':people://physid//'' -- 2 levels.

Finally, **''acl''** defines the actual access rules. The format is the very similar to the normal dokuwiki acl, however ''%GROUP%'' is a special string which gets replaced with the current namespace (except colons are replaced with underscores and the first colon is again removed). Also, the namespace specified starts at the defined level as the preceding parts do not change.

To prevent read access from the public, class wikis were made readable only by those in the class by setting permissions for @ALL to none for classes:*. This added the following line to acl.auth.php:

  classes:* @ALL 0

==== Example ====

<code>
$conf['plugin']['magicacl']['areas']['classes'] = array('level' => 4, 'acl' => array(':* @%GROUP% 16', ':home @%GROUP% 0', ':home @%GROUP%_instructor 16',':home @%GROUP%_ta 16'));
</code>

Looking at the class wiki example one final time, let's say the user is currently at the page '':classes:2008:fall:Phys1001W.100:home''

We match the classes area, so the rule is active. Next based on the level we know that MagicACL will use the first four levels of the namespace '':classes:2008:fall:Phys1001W.100''. Finally we can translate the rules in to what dokuwiki will enforce:

<code>
:* @classes_2008_fall_Phys1001W.100 16
:home @classes_2008_fall_Phys1001W.100 0
:home @classes_2008_fall_Phys1001W.100_instructor 16
:home @classes_2008_fall_Phys1001W.100_ta 16
</code>

