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

$word_location_context =  file_scanner::find_word_location_context($options['i'],  $misspelled_words);

foreach ($candidates['candidates'] as $word => $candidates) {
    echo "Unrecognized word : $word" . PHP_EOL;
    echo "did you mean :";
    $i = 0;
    foreach($candidates as $candidate) {
        echo "{$candidate['candidate']}, ";
        if (5 < $i++){
            break;
        }
    }
    echo PHP_EOL;
    if (isset($word_location_context[$word] )){
        print_r($word_location_context[$word]);
    }
    echo PHP_EOL;



}

