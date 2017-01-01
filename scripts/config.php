<?php


$keymanagerconfig = new \stdClass();

//location with access, keys and aliases files
$keymanagerconfig->inputdir="/home/git/config/";

//location where to put the generated authorisedkeyfiles
$keymanagerconfig->outputdir="/home/git/generated_keys/";
$keymanagerconfig->chmoddir=0700;
$keymanagerconfig->chmodauthfile=0400;

//force a keymanager on all systems
$keymanagerconfig->forcekeymanager=true;

//distribution user for keymanager on other systems
$keymanagerconfig->asuser="admin";
//public key
$keymanagerconfig->keytype="ssh-rsa";
$keymanagerconfig->identifier="keymanager";
$keymanagerconfig->pubkey="{keymanager_public_key}";


//distribution command, mind the trailing / in the destination path!
$keymanagerconfig->distributioncmd='rsync -e "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10" --rsync-path="sudo rsync" -uv -c --chmod=ugo=r {localpath}* admin@{server}:/etc/ssh/security/control/ 2>&1';

$keymanagerconfig->extracmd['default_2201'] = 'rsync -e "ssh -p2201 -o StrictHostKeyChecking=no -o ConnectTimeout=10" --rsync-path="sudo rsync" -uv -c --chmod=ugo=r {localpath}* admin@{server}:/etc/ssh/security/control/ 2>&1';

