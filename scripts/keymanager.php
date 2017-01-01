#!/usr/bin/php
<?php
include ("config.php");

function delTree($dir) {
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        (is_dir("$dir/$file")) ? delTree("$dir/$file") : unlink("$dir/$file");
    }
    return rmdir($dir);
}

function keys2cache() {
    global $keymanagerconfig;
    $keys = array();

    $handle = fopen($keymanagerconfig->inputdir . "/keys", "r");
    if (FALSE === $handle) {
        exit("Failed to open " . $keymanagerconfig->inputdir . "/keys" . "\n");
    }


    while (!feof($handle)) {
        $line = fgets($handle, 4096);
//        echo $line . "\n";
        $keyparts = explode(",", $line);
        if (3 == count($keyparts)) {
            $keys[trim($keyparts[0])][] = trim($keyparts[1]) . " " . trim($keyparts[2]) . " " . trim($keyparts[0]);
        }
    }
    fclose($handle);

    if (0 == count($keys)) {
        exit("No keys found in " . $keymanagerconfig->inputdir . "/keys" . "\n");
    }

    return $keys;
}

function aliases2cache() {
    global $keymanagerconfig;
    $aliases = array();

    $handle = fopen($keymanagerconfig->inputdir . "/aliases", "r");
    if (FALSE === $handle) {
        exit("Failed to open " . $keymanagerconfig->inputdir . "/aliases" . "\n");
    }


    while (!feof($handle)) {
        $line = fgets($handle, 4096);
//        echo $line . "\n";
        $lineparts = explode(":", $line);
        if (2 == count($lineparts)) {
            $aliasname = trim($lineparts[0]);
            $aliasesparts = explode(",", $lineparts[1]);
            if (0 < count($aliasesparts)) {
                foreach ($aliasesparts as &$value) {
                    $aliases[$aliasname][] = trim($value);
                }
            }
        }
    }
    fclose($handle);

    if (0 == count($aliases)) {
        exit("No aliases found in " . $keymanagerconfig->inputdir . "/aliases" . "\n");
    }

    return $aliases;
}

function expandaliases($alias, $aliascache) {
    if (!preg_match('/^@/', $alias)) {
        $aliases[] = $alias;
        return $aliases;
    } else {
        if (!array_key_exists($alias, $aliascache)) {
            echo "Alias $alias not found!\n";
        } else {
            $aliases = array();
            foreach ($aliascache[$alias] as &$value) {
                $aliases = array_merge($aliases, expandaliases($value, $aliascache));
            }
            return array_unique($aliases);
        }
    }
}

function access2cache($aliascache, $keycache) {
    global $keymanagerconfig;
    $access = array();

    $handle = fopen($keymanagerconfig->inputdir . "/access", "r");
    if (FALSE === $handle) {
        exit("Failed to open " . $keymanagerconfig->inputdir . "/access" . "\n");
    }


    while (!feof($handle)) {
        $line = fgets($handle, 4096);
//echo "\n ***\n";
//echo $line . "\n";
        $lineparts = explode(":", $line);
        if (3 == count($lineparts)) {
            $keyuserlist = $lineparts[0];
            $atserverlist = $lineparts[1];
            $asuserlist = $lineparts[2];

            $keyuserparts = explode(",", $keyuserlist);
            $atserverparts = explode(",", $atserverlist);
            $asuserparts = explode(",", $asuserlist);

            $keyusers = array();
            $atservers = array();
            $asusers = array();

            if ((0 < count($keyuserparts)) && (0 < count($atserverparts)) && (0 < count($asuserparts))) {
                foreach ($keyuserparts as &$keyuser) {
                    $keyusers = array_merge($keyusers, expandaliases(trim($keyuser), $aliascache));
                }
                foreach ($atserverparts as &$atserver) {
                    $atservers = array_merge($atservers, expandaliases(trim($atserver), $aliascache));
                }
                foreach ($asuserparts as &$asuser) {
                    $asusers = array_merge($asusers, expandaliases(trim($asuser), $aliascache));
                }
                /*
                  print_r($keyusers);
                  print_r($atservers);
                  print_r($asusers);
                 */
                if ((0 < count($keyusers)) && (0 < count($atservers)) && (0 < count($asusers))) {
                    reset($atservers);
                    foreach ($atservers as &$server) {
                        reset($asusers);
                        foreach ($asusers as &$asuser) {
                            reset($keyusers);
                            foreach ($keyusers as &$keyuser) {
                                if (isset($keycache[$keyuser])) {
                                    if (!isset($access[$server]) || !isset($access[$server][$asuser])) {
                                        $access[$server][$asuser] = $keycache[$keyuser];
                                    } else {
                                        $access[$server][$asuser] = array_merge($access[$server][$asuser], $keycache[$keyuser]);
                                    }
                                } else {
                                    if (isset($nokeyusers[$keyuser])) {
                                        $nokeyusers[$keyuser] = $nokeyusers[$keyuser] + 1;
                                    } else {
                                        $nokeyusers[$keyuser] = 1;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    fclose($handle);

    if (isset($nokeyusers)) {
        while (list($key, $val) = each($nokeyusers)) {
            echo "No keys found for user '$key' ($val times)\n";
        }
    }

    return $access;
}

function makeauthfiles($access) {
    global $keymanagerconfig;

    delTree($keymanagerconfig->outputdir);

    while (list($server, $asuserkeys) = each($access)) {
        $outputdir = \rtrim($keymanagerconfig->outputdir, "/\\");
        $serverdir = $outputdir . "/" . $server;
        if (!is_dir($serverdir)) {
            mkdir($serverdir, $keymanagerconfig->chmoddir, true);
        }
        if ($keymanagerconfig->forcekeymanager) {
            $asuserkeys[$keymanagerconfig->asuser][] = $keymanagerconfig->keytype . " " .
                    $keymanagerconfig->pubkey . " " .
                    $keymanagerconfig->identifier;
        }
        while (list($asuser, $keys) = each($asuserkeys)) {
            $authuserfile = $keymanagerconfig->outputdir . "/" . $server . "/" . $asuser . ".auth";
            if (!$handle = fopen($authuserfile, 'w')) {
                echo "Cannot open file ($authuserfile)";
                exit;
            } else {
                foreach ($keys as &$key) {
                    fwrite($handle, $key . "\n");
                }
                fclose($handle);
                chmod($authuserfile, $keymanagerconfig->chmodauthfile);
            }
        }
    }
}

function distributeauthfiles($access, $ndhostcache) {
    global $keymanagerconfig;
    while (list($server, $asuserkeys) = each($access)) {
        $return_var = 0;
        $output = array();
        $outputdir = \rtrim($keymanagerconfig->outputdir, "/\\");
        $serverdir = $outputdir . "/" . $server;
//distribute this servers auth files
        if (array_key_exists($server, $ndhostcache)) {
            $hostcmd = $ndhostcache[$server];
            if (array_key_exists($hostcmd, $keymanagerconfig->extracmd)) {
                $distributioncmd = $keymanagerconfig->extracmd[$hostcmd];
            } else {
                $distributioncmd = $keymanagerconfig->distributioncmd;
            }
        } else {
            $distributioncmd = $keymanagerconfig->distributioncmd;
        }
        $cmd = \preg_replace("/{server}/", $server, $distributioncmd);
        $cmd = \preg_replace("/{localpath}/", $serverdir . "/", $cmd);
        exec($cmd, $output, $return_var);
        if (0 != $return_var) {
            echo "Error for server $server:\n";
            echo $cmd . "\n";
            echo "Exited with return code $return_var\n";
            foreach ($output as &$outputline) {
                echo "$outputline\n";
            }
            echo "\n";
        } else if (3 < count($output)) {
            echo "Changed files for $server:\n";
            foreach ($output as &$outputline) {
                echo "$outputline\n";
            }
            echo "\n";
        }
    }
}

function nondefaulthosts2cache() {
    global $keymanagerconfig;
    $hosts = array();

    if (file_exists($keymanagerconfig->inputdir . "/nondefaulthosts")) {

        $handle = fopen($keymanagerconfig->inputdir . "/nondefaulthosts", "r");
        if ($handle) {

            while (!feof($handle)) {
                $line = fgets($handle, 4096);
                $keyparts = explode(":", $line);
                if (2 == count($keyparts)) {
                    $hosts[trim($keyparts[0])] = trim($keyparts[1]);
                }
            }
            fclose($handle);
        }
    }

    return $hosts;
}

$keyscache = keys2cache();
$aliasescache = aliases2cache();
$accesscache = access2cache($aliasescache, $keyscache);
$ndhostscache = nondefaulthosts2cache();

makeauthfiles($accesscache);
distributeauthfiles($accesscache, $ndhostscache);
