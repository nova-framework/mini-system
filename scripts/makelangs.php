#!/usr/bin/env php
<?php

define("DS", DIRECTORY_SEPARATOR);

define("BASEPATH", dirname(dirname(__FILE__)) .DS);

$languages = array(
    'en',
    'it',
    'ro',
    'ru',
);

$workPaths = array(
    'src' => 'system',
);

//
function starts_with($haystack, $needle) {
    return (($needle === '') || (strpos($haystack, $needle) === 0));
}

//
function phpGrep($q, $path) {
    $ret = array();

    $fp = opendir($path);

    while($f = readdir($fp)) {
        if( preg_match("#^\.+$#", $f) ) continue; // ignore symbolic links

        $file_full_path = $path.DS.$f;

        if(is_dir($file_full_path)) {
            $ret = array_unique(array_merge($ret, phpGrep($q, $file_full_path)));
        }
        else if(stristr(file_get_contents($file_full_path), $q)) {
            $ret[] = $file_full_path;
        }
    }

    return $ret;
}

//
$options = getopt('', array('path::'));

if(! empty($options['path'])) {
    $worksPaths = array_map('trim', explode(',', $options['path']));
}

foreach($workPaths as $workPath => $workDomain) {
    if(! is_dir(BASEPATH .$workPath)) {
        continue;
    }

    $start = "__d('";

    $results = phpGrep("__d('", BASEPATH .$workPath);

    if(empty($results)) {
        continue;
    }

    $pattern = '#__d\(\'(?:.*)?\',.?\s?\'(.*)\'(?:,.*)?\)#smU';

    echo "Using PATERN: '" .$pattern."'\n";

    $messages = array();

    foreach($results as $key => $filePath) {
        $file = substr($filePath, strlen(BASEPATH));

        $content = file_get_contents($filePath);

        if(preg_match_all($pattern, $content, $matches)) {
            foreach($matches[1] as $message) {
                //$message = trim($message);

                if($message == '$msg, $args = null') {
                    // This is the function
                    continue;
                }

                $messages[] = str_replace("\\'", "'", $message);
            }
        }
    }

    if(!empty($messages)) {
        echo 'Messages found on path "'.$workPath.'". Processing...'.PHP_EOL;

        $messages = array_flip($messages);

        foreach($languages as $language) {
            $langFile = BASEPATH .$workPath.'/Language/'.strtoupper($language).'/messages.php';

            if(is_readable($langFile)) {
                $oldData = include($langFile);

                $oldData = is_array($oldData) ? $oldData : array();
            } else {
                $oldData = array();
            }

            foreach($messages as $message => $value) {
                if(array_key_exists($message, $oldData)) {
                    $value = $oldData[$message];

                    if(!empty($value) && is_string($value)) {
                        $messages[$message] = $value;
                    }
                    else {
                        $messages[$message] = '';
                    }
                }
                else {
                    $messages[$message] = '';
                }
            }

            ksort($messages);

            $output = "<?php

return " .var_export($messages, true).";\n";

            //$output = preg_replace("/^ {2}(.*)$/m","    $1", $output);

            file_put_contents($langFile, $output);

            echo 'Written the Language file: "'.str_replace(BASEPATH, '', $langFile).'"'.PHP_EOL;
        }
    }

    echo PHP_EOL;
}
