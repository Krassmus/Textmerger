<?php

/**
 * Class TextmergerException
 * Special Exception class for exceptions that are thrown when text-conflicts happen.
 */
class TextmergerException extends Exception {

    public $data = array();

    public function __construct($message, $data = array())
    {
        $this->data = $data;
        parent::__construct($message);
    }
}

/**
 * Class TextMergerReplacement
 * An object of TextMergerReplacement represents a replacement of text for another text.
 */
class TextmergerReplacement {

    public $start;
    public $end;
    public $text;
    public $origin;

    /**
     * TextMergerReplacement constructor.
     * Create a new Replacement. A replacement consists of a start-value (index within the text string),
     * an end-value and the text that replaces everything between start and end.
     * @param integer $start
     * @param integer $end : must be bigger or equal than start.
     * @param string $text
     * @param string|null $origin : the name of an origin
     */
    public function __construct($start = 0, $end = 0, $text = "", $origin = null)
    {
        $this->start   = $start;
        $this->end     = $end;
        $this->text    = $text;
        $this->origin  = $origin;
    }

    /**
     * When a text is changed by multiple replacements each replacement can change the
     * textlength (by simply adding or erasing characters). In this case the next replacements need
     * adjusted start and end numbers to work correctly.
     * @param $add
     */
    public function changeIndexesBy($add)
    {
        $this->start = $this->start + $add;
        $this->end   = $this->end + $add;
    }

    /**
     * Applies the Text-replacement to the given text and replaces the characters between
     * $this->start and $this->end with the $this->text, which can also be an empty string.
     * @param $text
     * @return string
     */
    public function applyTo($text)
    {
        return substr($text, 0, $this->start) . $this->text . substr($text, $this->end);
    }

    /**
     * Finds out if this replacement is in conflict with the given replacement.
     * @param $replacement : the replacement to compare with.
     * @return bool : true if there is a conflict.
     */
    public function isConflictingWith($replacement)
    {
        return ($this->start < $replacement->end && $this->start > $replacement->start)
                    || ($this->end < $replacement->end && $this->end > $replacement->start)
                    || ($this->start < $replacement->start && $this->end > $replacement->end)
                    || ($this->start === $replacement->start && $this->end === $replacement->end && $this->end - $this->start > 0);
    }

    /**
     * @param $delimiter
     * @param $original
     * @return array of replacements
     */
    public function breakApart($delimiter, $original)
    {
        $original_snippet = substr($original, $this->start, $this->end - $this->start + 1);
        if (($this->start === $this->end && $this->text === "") || ($original_snippet === $this->text)) {
            return array($this);
        }

        if ($delimiter !== "") {
            $parts = explode($delimiter, $this->text);
            $original_parts = explode($delimiter, $original_snippet);
        } else {
            $parts = str_split($this->text);
            $original_parts = str_split($original_snippet);
        }
        if (count($parts) === 1 || count($original_parts) === 1) {
            return array($this);
        }

        //levensthein-algorithm (maybe implement hirschberg later)
        $backtrace = self::getLevenshteinBacktrace($original_parts, $parts);

        if (!in_array("=", $backtrace)) {
            //Merging can be interesting, but still pointless. So just:
            return array($this);
        }

        //use result to break this replacement into multiple smaller replacements:

        $replacements = array();
        $replacement = null;

        $originaltext_index = $this->start;
        $originalpartsindex = 0;

        $replacetext_index = 0;
        $replacetext_start = 0;
        $replacetext_end = 0;


        foreach ($backtrace as $key => $operation) {
            if ($key > 0) {
                $replacetext_end += strlen($delimiter);
                $originaltext_index += strlen($delimiter);
            }
            if ($operation === "=") {
                if ($replacement !== null) {
                    $replacement->end = $originaltext_index - strlen($delimiter);
                    $replacement->text = substr(
                        $this->text,
                        $replacetext_start,
                        $replacetext_end - strlen($delimiter) - $replacetext_start
                    );
                    $replacements[] = $replacement;
                    $replacement = null;
                }
            } else {
                if ($replacement === null) {
                    $replacement = new TextmergerReplacement();
                    $replacement->origin = $this->origin;
                    $replacement->start = $originaltext_index;
                    $replacetext_start = $replacetext_end;
                }
            }
            switch ($operation) {
                case "=":
                    $originaltext_index += strlen($original_parts[$originalpartsindex]);
                    $originalpartsindex++;
                    break;
                case "r":
                    $originaltext_index += strlen($original_parts[$originalpartsindex]);
                    $originalpartsindex++;
                    break;
                case "i":
                    break;
                case "d":
                    $originaltext_index += strlen($original_parts[$originalpartsindex]);
                    $originalpartsindex++;
                    break;
            }

            switch ($operation) {
                case "=":
                    $replacetext_end += strlen($parts[$replacetext_index]);
                    $replacetext_index++;
                    break;
                case "r":
                    $replacetext_end += strlen($parts[$replacetext_index]);
                    $replacetext_index++;
                    break;
                case "i":
                    $replacetext_end += strlen($parts[$replacetext_index]);
                    $replacetext_index++;
                    break;
                case "d":
                    break;
            }
        }
        if ($replacement !== null) {
            $replacement->end = $originaltext_index;
            $replacement->text = substr(
                $this->text,
                $replacetext_start,
                $replacetext_end - strlen($delimiter) - $replacetext_start + 1 //TODO: why +1 ??
            );
            $replacements[] = $replacement;
        }
        return $replacements;
    }


    /**
     * Determains the levenshtein backtrace to two arrays. The backtrace is an array
     * containing i, d, r and = as characters. i for an insertion, d for a deletion, r for a replacement
     * and = for equivalent character.
     * @param array $original
     * @param array $new
     * @return string
     */
    public static function getLevenshteinBacktrace($original, $new)
    {
        //create levenshtein-matrix:
        $matrix = array(array(0));

        //   ? m i n e
        // ? 0 1 2 3 4
        // o 1 .
        // r 2   .
        // i 3     .
        // g 4       .
        // i 5       .
        // n 6       .
        // a 7       .
        // l 8       .

        for ($k = 0; $k <= count($original); $k++) {
            for ($i = 0; $i <= count($new); $i++) {
                if (!isset($matrix[$k][$i])) {
                    $matrix[$k][$i] = min(
                        isset($matrix[$k - 1][$i - 1]) && ($new[$i - 1] === $original[$k - 1])
                            ? $matrix[$k - 1][$i - 1] : 100000,                                  //identity
                        isset($matrix[$k - 1][$i - 1]) ? $matrix[$k - 1][$i - 1] + 1 : 100000,   //replace
                        isset($matrix[$k][$i - 1]) ? $matrix[$k][$i - 1] + 1 : 100000,           //insert
                        isset($matrix[$k - 1][$i]) ? $matrix[$k - 1][$i] + 1 : 100000            //delete
                    );
                }
            }
        }

        /**echo "<table>";
        foreach ($matrix as $key => $line) {
            if ($key === 0) {
                echo "<tr><td></td><td>#</td>";
                foreach ($new as $part) {
                    echo "<td>".(strlen($part) === 1 ? $part : ".")."</td>";
                }
                echo "</tr>";
            }
            echo "<tr>";
            if ($key === 0) {
                echo "<td>#</td>";
            } else {
                echo "<td>".(strlen($original[$key - 1]) === 1 ? $original[$key - 1] : ".")."</td>";
            }
            foreach ($line as $value) {
                echo "<td>".$value."</td>";
            }
            echo "</tr>";
        }
        echo "</table><br> \n";**/

        //now create the backtrace to the matrix:
        $k = count($original);
        $i = count($new);
        $backtrace = array();
        while ($k > 0 || $i > 0) {
            if ($k > 0 && ($matrix[$k - 1][$i] + 1 == $matrix[$k][$i])) {
                array_unshift($backtrace, "d");
                $k--;
            }
            if ($i > 0 && ($matrix[$k][$i - 1] + 1 == $matrix[$k][$i])) {
                array_unshift($backtrace, "i");
                $i--;
            }
            if ($i > 0 && $k > 0 && ($matrix[$k - 1][$i - 1] + 1 == $matrix[$k][$i])) {
                array_unshift($backtrace, "r");
                $i--;
                $k--;
            }
            if ($i > 0 && $k > 0 && ($matrix[$k - 1][$i - 1] == $matrix[$k][$i])) {
                array_unshift($backtrace, "=");
                $i--;
                $k--;
            }
        }
        return $backtrace;
    }

}

class TextmergerReplacementGroup implements ArrayAccess, Iterator, Countable{

    public $replacements = array();
    private $position = 0;

    public function offsetExists($offset)
    {
        return isset($this->replacements[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->replacements[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->replacements[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->replacements[$offset]);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function current()
    {
        return $this->replacements[$this->position];
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function valid()
    {
        return isset($this->replacements[$this->position]);
    }

    public function sort()
    {
        usort($this->replacements, function ($a, $b) {
            return $a->start >= $b->start ? 1 : -1;
        });
        $this->rewind();
    }

    public function count()
    {
        return count($this->replacements);
    }

    /**
     * Tries to break all replacements apart into more smaller replacements. After that all replacements are still
     * well ordered.
     * @param string $delimiter : "" or " " or "\n" or any other string.
     * @param $original
     */
    public function breakApart($delimiter, $original)
    {
        $replacements = array();
        foreach ($this->replacements as $replacement) {
            $replacements = array_merge(
                $replacements,
                $replacement->breakApart($delimiter, $original)
            );
        }
        $this->replacements = $replacements;
        $this->sort();
    }

    /**
     * Determains if there are any conflicts in this set of replacements.
     * @return bool
     */
    public function haveConflicts()
    {
        foreach ($this->replacements as $index => $replacement) {
            if ($index === $this->count() - 1) {
                break;
            }
            if ($replacement->isConflictingWith($this->replacements[$index + 1])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Looks through all conflicts and resolves them according to $conflictBehaviour.
     * @param string $conflictBehaviour : "select_larger_difference", "throw_exception", "select_text1" or "select_text2"
     * @throws TextmergerException : throws exception if there is a conflict and $conflictBehaviour === "throw_exception"
     */
    public function resolveConflicts($conflictBehaviour)
    {
        foreach ($this->replacements as $index => $replacement) {
            if ($index === count($this->replacements) - 1) {
                break;
            }
            if ($replacement->isConflictingWith($this->replacements[$index + 1])) {
                switch ($conflictBehaviour) {
                    case "throw_exception":
                        throw new TextmergerException("Texts have a conflict.", array(
                            "original" => $original,
                            "text1" => $text1,
                            "text2" => $text2,
                            "replacement1" => $replacement,
                            "replacement2" => $this->replacements[$index + 1]
                        ));
                        break;
                    case "select_text1":
                        if ($replacement->origin === "text1") {
                            unset($this->replacements[$index]);
                        } else {
                            unset($this->replacements[$index + 1]);
                        }
                        break;
                    case "select_text2":
                        if ($replacement->origin === "text2") {
                            unset($this->replacements[$index]);
                        } else {
                            unset($this->replacements[$index + 1]);
                        }
                        break;
                    case "select_larger_difference":
                    default:
                        if ($replacement->end - $replacement->start > $this->replacements[$index + 1]->end - $this->replacements[$index + 1]->start) {
                            unset($this->replacements[$index + 1]);
                        } else {
                            unset($this->replacements[$index]);
                        }
                        break;
                }
                $this->resolveConflicts($conflictBehaviour);
                return;
            }
        }
    }

    /**
     * Applies the replacements to the given text.
     * @param $text
     * @return string
     */
    public function applyTo($text)
    {
        $index_alteration = 0;
        foreach ($this->replacements as $replacement) {
            $replacement->changeIndexesBy($index_alteration);
            $text = $replacement->applyTo($text);
            $replacement->changeIndexesBy(- $index_alteration);
            $alteration = strlen($replacement->text) - ($replacement->end - $replacement->start);
            $index_alteration += $alteration;
        }
        return $text;
    }
}

class Textmerger {

    protected $conflictBehaviour = "select_larger_difference"; // "throw_exception", "select_text1", "select_text2"
    protected $levenshteinDelimiter = null; // something like array("\n", " ", "")

    //Hashes the replacements of three texts, so they don't need to be calculated multiple times.
    static protected $replacement_hash = array();

    /**
     * Creates a new Textmerger object. Same parameters as in constructor.
     * @param array $params
     * @return TextMerger
     */
    static public function get($params = array())
    {
        return new Textmerger($params);
    }

    /**
     * Textmerger3 constructor.
     * Possible parameter: array(
     *     'conflictBehaviour' => "throw_exception", // "select_larger_difference", "select_text1", "select_text2"
     *     'levenshteinDelimiter' => array("\n", " ", "")
     * )
     * @param array $params
     */
    public function __construct($params = array())
    {
        $this->conflictBehaviour = isset($params['conflictBehaviour'])
            ? $params['conflictBehaviour']
            : "select_larger_difference";
        $this->levenshteinDelimiter = isset($params['levenshteinDelimiter'])
            ? $params['levenshteinDelimiter']
            : array("\n", " ", "");
    }

    /**
     * Implements a 3-way-merge between an original text and two independently derived texts. For this task
     * an algorithm needs to calculate all small changes that were made from original to text1 and from original
     * to text2 and merges these changes. We call these changes replacements, because they are simply that.
     * If replacements have conflicts the algorithm tries to break down the replacements in smaller ones and merge
     * them. But if this also won't work the conflicting replacement with the smaller change will be applied. But
     * you can also set $this->$exceptionOnConflict to receive an exception on text-conflicts instead.
     * @param string $original
     * @param string $text1
     * @param string $text2
     * @return string
     */
    public function merge($original, $text1, $text2)
    {
        $replacements = $this->getReplacements($original, $text1, $text2);
        return $replacements->applyTo($original);
    }

    /**
     * Calculates a new cursor position for a given old cursor position, the original text and the two new texts.
     * @param $cursor_position
     * @param $original
     * @param $text1
     * @param $text2
     * @return int
     * @throws TextmergerException
     */
    public function calculateCursor($cursor_position, $original, $text1, $text2)
    {
        $replacements = $this->getReplacements($original, $text1, $text2);

        $index_alteration = 0;
        foreach ($replacements as $replacement) {
            $replacement->changeIndexesBy($index_alteration);
            if ($replacement->start <= $cursor_position) {
                $cursor_position += strlen($replacement->text) - $replacement->end + $replacement->start;
                $index_alteration += strlen($replacement->text) - $replacement->end + $replacement->start;
            } else {
                break;
            }
        }
        return $cursor_position;
    }

    /**
     * Returns an array of TextMergerReplacement which are not conflicting. Or if there are conflicts and
     * $this->$exceptionOnConflict is set to true, an exception will be thrown.
     * @param string $original
     * @param string $text1
     * @param string $text2
     */
    public function getReplacements($original, $text1, $text2)
    {
        $hash_id = md5($original."___".$text1."____".$text2);
        if (isset(self::$replacement_hash[$hash_id])) {
            return self::$replacement_hash[$hash_id];
        }
        //Make texts smaller
        for($offset = 0; $offset < strlen($original); $offset++) {
            if ($original[$offset] !== $text1[$offset] || $original[$offset] !== $text2[$offset]) {
                break;
            }
        }

        for($backoffset = 0; $backoffset < strlen($original); $backoffset++) {
            if (($original[strlen($original) - 1 - $backoffset] !== $text1[strlen($text1) - 1 - $backoffset])
                    || ($original[strlen($original) - 1 - $backoffset] !== $text2[strlen($text2) - 1 - $backoffset])
                    || (strlen($original) - 1 - $backoffset <= $offset)) {
                break;
            }
        }
        $original_trimmed = (string) substr($original, $offset, strlen($original) - $offset - $backoffset);
        $text1_trimmed = (string) substr($text1, $offset, strlen($text1) - $offset - $backoffset);
        $text2_trimmed = (string) substr($text2, $offset, strlen($text2) - $offset - $backoffset);

        //collect the two major replacements:
        $replacements = new TextmergerReplacementGroup();
        $replacements[0] = $this->getSimpleReplacement($original_trimmed, $text1_trimmed, "text1");
        $replacements[1] = $this->getSimpleReplacement($original_trimmed, $text2_trimmed, "text2");

        if (!$replacements->haveConflicts()) {
            foreach ($replacements as $replacement) {
                $replacement->start += $offset;
                $replacement->end += $offset;
            }
            self::$replacement_hash[$hash_id] = $replacements;
            return $replacements;
        }

        //Now if this didn't work we try it with levenshtein. The old simple replacements won't help us, wo we create
        //a new pair of replacements:
        $replacements[0] = new TextmergerReplacement(0, strlen($original_trimmed) - 1, $text1_trimmed, "text1");
        $replacements[1] = new TextmergerReplacement(0, strlen($original_trimmed) - 1, $text2_trimmed, "text2");

        foreach ($this->levenshteinDelimiter as $delimiter) {
            if ($replacements->haveConflicts() !== false) {
                $replacements->breakApart($delimiter, $original_trimmed);
            } else {
                break;
            }
        }

        $have_conflicts = $replacements->haveConflicts();
        if ($have_conflicts !== false) {
            $replacements->resolveConflicts($this->conflictBehaviour);
        }

        foreach ($replacements as $replacement) {
            $replacement->start += $offset;
            $replacement->end += $offset;
        }

        self::$replacement_hash[$hash_id] = $replacements;
        return $replacements;
    }

    /**
     * Calculates the simple replacement between original and text in the way that all changed characters are between
     * start and end of the one replacement. For example if you change line 3 and line 20 of a document, the whole
     * block from line 3 to line 20 will be considered as the change. That is very simple, I know. But it's also fast.
     * @param string $original
     * @param string $text : the derived text
     * @return TextMergerReplacement
     */
    public function getSimpleReplacement($original, $text, $origin)
    {
        $replacement = new TextmergerReplacement();
        $replacement->origin = $origin;
        $text_start = 0;
        $text_end = strlen($text);
        for($i = 0; $i <= strlen($original); $i++) {
            if ($original[$i] !== $text[$i]) {
                $replacement->start = $i;
                $text_start = $i;
                break;
            } elseif ($i === strlen($original) - 1) {
                $replacement->start = strlen($original);
                $text_start = strlen($original);
                break;
            }
        }

        for($i = 0; $i < strlen($original); $i++) {
            if (($original[strlen($original) - 1 - $i] !== $text[strlen($text) - 1 - $i])
                    || (strlen($original) - $i === $replacement->start)) {
                $replacement->end = strlen($original) - $i;
                $text_end = strlen($text) - $i;
                break;
            }
        }

        if ($text_end - $text_start < 0) {
            $replacement->end++;
            $length = 0;
        } else {
            $length = $text_end - $text_start;
        }
        $replacement->text = (string) substr($text, $text_start, $length);
        return $replacement;
    }

}
