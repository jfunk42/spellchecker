# spellchecker

call with
    php spell_check.php -i test.txt -d dictionary.txt

The heart of the algorithm is the variable indexes used by the dictionary class to find candidates quickly in the database. the next step is to formulate better indexes and evaluate them. Ideally you would test against a lot of different real world examples of uncorrected data. I would expect to find that different groups of people would find different indexes to be more valuable. For example native english speakers might have more benefit the "forward" and "fatfinger" indexes while a group of native german speakers would benefit more from the "middle".

One potential index function that we don't have time for would be a phonetic translation.

Another potential index function could count occurrences of frequent letters.

