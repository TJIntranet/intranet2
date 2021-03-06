<?php
#
# Written by William Yang (2008), based on pictureimport.php.
#
# Replaces existing pictures (e.g. to load make-up photos).
#
# November 2009
#
# This script sucks.  But it works!
#
# To use:
# 1. Place in a web-accessible location.  You may want to protect the location
#     with htaccess so random Joe can't just grab everyone's pictures.
# 2. Set the gradyear and pathtopics variables to be correct.
# 3. Check and set the LDAP server and LDAP manager password as necessary.
# 4. Run "wget -O pictures.ldif --http-user=user --http-password=password
#     http://iodine.tjhsst.edu/path/to/script/picturereplace.php"
# 5. Look over the LDIF to make sure it is what you expect.
# 6. ldapmodify the generated ldif as an LDAP admin user.
# 7. Optionally run the findStudentsMissingPictures.sh shell script to check
#     for users missing pictures.
#

$gradyear = 2010;
$pathtopics = "/home/wyang/200910pics/makeups_extract";

$ldapconn = ldap_connect('iodine-ldap.tjhsst.edu');
ldap_set_option($ldapconn, LDAP_OPT_PROTOCOL_VERSION, 3);
$bind = ldap_bind($ldapconn,"cn=Manager,dc=tjhsst,dc=edu","MANAGERPSWDGOESHERE");



###You should not need to modify anything below here unless you are cleaning up the script.

exec("ls -1 $pathtopics", $files);

//frosh
foreach($files as $file) {
	list($sid, $ext) = explode('.', $file);
	$udn = ldap_search($ldapconn, 'ou=people,dc=tjhsst,dc=edu', "(&(tjhsststudentid={$sid})(graduationyear=" . ($gradyear + 3) . "))", array('dn', 'perm-showpictures', 'perm-showpictures-self'));
	$info = ldap_get_entries($ldapconn, $udn);
	if($info['count']==0)
		continue;
	echo 'dn: cn=freshmanPhoto,';
	echo $info[0]["dn"] . "\r\n";
	echo "replace: jpegPhoto\r\n";
	echo "jpegPhoto::";
	echo base64_encode(`convert "{$pathtopics}"/"{$file}" -format "jpeg" -resize 172x228 -`);
	echo "\r\n\r\n";
}

//soph
foreach($files as $file) {
	list($sid, $ext) = explode('.', $file);
	$udn = ldap_search($ldapconn, 'ou=people,dc=tjhsst,dc=edu', "(&(tjhsststudentid={$sid})(graduationyear=" . ($gradyear + 2) . "))", array('dn', 'perm-showpictures', 'perm-showpictures-self'));
	$info = ldap_get_entries($ldapconn, $udn);
	if($info['count']==0)
		continue;
	echo 'dn: cn=sophomorePhoto,';
	echo $info[0]["dn"] . "\r\n";
	echo "replace: jpegPhoto\r\n";
	echo "jpegPhoto::";
	echo base64_encode(`convert "{$pathtopics}"/"{$file}" -format "jpeg" -resize 172x228 -`);
	echo "\r\n\r\n";
}

//junior
foreach($files as $file) {
	list($sid, $ext) = explode('.', $file);
	$udn = ldap_search($ldapconn, 'ou=people,dc=tjhsst,dc=edu', "(&(tjhsststudentid={$sid})(graduationyear=" . ($gradyear + 1) . "))", array('dn', 'perm-showpictures', 'perm-showpictures-self'));
	$info = ldap_get_entries($ldapconn, $udn);
	if($info['count']==0)
		continue;
	echo 'dn: cn=juniorPhoto,';
	echo $info[0]["dn"] . "\r\n";
	echo "replace: jpegPhoto\r\n";
	echo "jpegPhoto::";
	echo base64_encode(`convert "{$pathtopics}"/"{$file}" -format "jpeg" -resize 172x228 -`);
	echo "\r\n\r\n";
}

//senior, if any
foreach($files as $file) {
	list($sid, $ext) = explode('.', $file);
	$udn = ldap_search($ldapconn, 'ou=people,dc=tjhsst,dc=edu', "(&(tjhsststudentid={$sid})(graduationyear={$gradyear}))", array('dn', 'perm-showpictures', 'perm-showpictures-self'));
	$info = ldap_get_entries($ldapconn, $udn);
	if($info['count']==0)
		continue;
	echo 'dn: cn=seniorPhoto,';
	echo $info[0]["dn"] . "\r\n";
	echo "replace: jpegPhoto\r\n";
	echo "jpegPhoto::";
	echo base64_encode(`convert "{$pathtopics}"/"{$file}" -format "jpeg" -resize 172x228 -`);
	echo "\r\n\r\n";
}
?>
