<?php
namespace EDI\X12;

include (dirname(__FILE__) . "/../Document.class.php");

/**
* A class to parse ASC X12 EDI documents.  Currently the entire document is 
* read into memory - this may change in future versions.
*/
class Parser {
  
    const SEGMENT_TERMINATOR_POSITION = 105;
    const SUBELEMENT_SEPARATOR_POSITION = 104;
    const ELEMENT_SEPARATOR_POSITION = 3;
    /**
    * Parse an EDI document. Data will be returned as an array of instances of
    * EDI\Document. Document should contain exactly one ISA/IEA envelope.
    */
    public static function parse ($res) {
        $string = '';
        $segments = array();

        if (!$res) {
            throw new Exception('No resource or string passed to parse()');
        }

        $documents = array();
        if (is_resource($res)) {
            $res = $data;
            $meta = stream_get_meta_data($res);
            if (!$meta['seekable']) {
                throw new Exception('Stream is not seekable');
            }
             
            throw new Exception('Not implemented!');            
        } else {
            $data = $res;
            // treat as string.
            if (strcasecmp(substr($data, 0, 3), 'ISA') != 0) {
                throw new Exception('ISA segment not found in data stream');
            }
         
            $segment_terminator = substr($data, self::SEGMENT_TERMINATOR_POSITION, 1);
            $element_separator = substr($data, self::ELEMENT_SEPARATOR_POSITION, 1);
            $subelement_separator = substr($data, self::SUBELEMENT_SEPARATOR_POSITION, 1);

            $document = null;
            $raw_segments = explode($segment_terminator, $data);
        }

        $isas = array();
        $current_isa = null;
        $current_gs = null;
        $current_st = null;

        foreach ($raw_segments as $segment) {
            $elements = explode($element_separator, $segment);
            $identifier = strtoupper($elements[0]);

            // only inspect each element if the subelement separator is present in the string
            if (strpos($segment, $subelement_separator) !== FALSE && $identifier != 'ISA') {
                foreach ($elements as &$element) {
                    if (strpos($segment, $subelement_separator) !== FALSE) {
                        $element = explode($subelement_separator, $element);
                    }
                }
                unset($element);
            }
                        
            /* This is a ginormous switch statement, but necessarily so. 
            * The idea is that the parser will, for each transaction set
            * in the ISA envelope, create a new Document instance with 
            * the containing ISA and GS envelopes copied in.
            */
            switch ($identifier) {
                case 'ISA':
                    $current_isa = array( 'isa' => $elements );
                    break;
                case 'GS':
                    $current_gs = array( 'gs' => $elements );
                    break;
                case 'ST':
                    $current_st = array( 'st' => $elements );
                    break;
                case 'SE':
                    assert($current_gs != null, 'GS data structure isset');
                    $current_st['se'] = $elements;
                    if (!isset($current_gs['txn_sets'])) {
                        $current_gs['txn_sets'] = array();
                    }
                    array_push($current_gs['txn_sets'], $current_st);
                    $current_st = null;
                    break;
                case 'GE': 
                    assert($current_isa != null, 'ST data structure isset');
                    $current_gs['ge'] = $elements;
                    if (!isset($current_isa['func_groups'])) {
                        $current_isa['func_groups'] = array();
                    }
                    array_push($current_isa['func_groups'], $current_gs);
                    $current_gs = null;
                    break;
                case 'IEA':
                    $current_isa['iea'] = $elements;
                    foreach ($current_isa['func_groups'] as $gs) {
                        foreach ($gs['txn_sets'] as $st) {
                            $segments = array_merge(
                                array(
                                    $current_isa['isa'], 
                                    $gs['gs'], 
                                    $st['st']
                                ),
                                $st['segments'],
                                array( 
                                    $st['se'],
                                    $gs['ge'],
                                    $current_isa['iea']
                                )
                            );
                            $document = new \EDI\Document($segments);
                            array_push($documents, $document); 
                        }
                    }
                    break;
                default:
                    if (!isset($current_st['segments'])) {
                        $current_st['segments'] = array();
                    }
                    array_push($current_st['segments'], $elements);
                    break;
            }
        }

        return $documents;
    }

    public static function parseFile ($file) {
        $contents = file_get_contents($file);
        return parse($contents);
    }
}
?>
