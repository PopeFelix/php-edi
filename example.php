<?php

include 'EDI/X12/Parser.class.php';

$string = file_get_contents('testfile');
$documents = \EDI\X12\Parser::parse($string);
foreach ($documents as $document) {
    print "$document";
    print "--\n";
}
?>
