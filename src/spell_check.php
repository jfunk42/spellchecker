<?php

include_once "dictionary.php";


$options = getopt('d:i:');

if (!$options['i']) {
    die("usage : php {$argv[0]} -i test_file -d dictionary" . PHP_EOL);
}

$dictionary = new dictionary();
$dictionary->init($options['d']);

$misspelled_words = $dictionary->find_misspelled_words($options['i']);
$candidates = $dictionary->find_candidates($misspelled_words);


