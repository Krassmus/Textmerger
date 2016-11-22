<?php

require_once __DIR__ . "/../Textmerger.php";

    function escape($text) {
        return htmlentities($text);
    }

?><!DOCTYPE html>
<html>
<head lang="en">
    <meta charset="UTF-8">
    <title>Testing Textmerger.php</title>
    <style>
        body {
            font-family: Sans-Serif;
        }
        table {
            margin: 20px;
        }
        table > caption {
            background-color: lightblue;
            text-align: left;
            font-size: 1.3em;
            padding: 5px;
        }
        table.failed caption {
            background-color: #cc6165;
        }
        table a {
            font-size: 0.8em;
            color: blue;
            cursor: pointer;
        }
    </style>
</head>
    <body>

    <?
    $tests = json_decode(file_get_contents("./tests.json"), true);

    if (isset($_REQUEST['only']) && $_REQUEST['only'] !== "") {
        $tests = array($tests[$_REQUEST['only']]);
    }
    $successful = 0;
    foreach ($tests as $key => $test) {
        $tests[$key]['result'] = Textmerger::get()->merge($test['original'], $test['mine'], $test['theirs']);
        if ($tests[$key]['result'] === $test['expected']) {
            $successful++;
        }
    }
    ?>

    <h1>Testing  Textmerger.php</h1>

    <table id="resulttable">
        <tbody>
        <tr>
            <td>Successful tests</td>
            <td id="successful"><?= (int) $successful ?></td>
        </tr>
        <tr>
            <td>Failed tests</td>
            <td id="failed"><?= (int) count($tests) - $successful ?></td>
        </tr>
        </tbody>
    </table>

    <h2>
        Tests
        <? if (isset($_REQUEST['only']) && $_REQUEST['only'] !== "") : ?>
            <a href="?" style="font-size: 0.5em;">All tests</a>
        <? endif ?>
    </h2>

    <? foreach ($tests as $key => $test) : ?>
        <table class="test <?= $test['result'] === $test['expected'] ? "" : "failed" ?>">
            <caption><?= escape($test['title']) ?></caption>
            <tbody>
            <tr>
                <td>Original</td>
                <td class="original"><?= escape($test['original']) ?></td>
            </tr>
            <tr>
                <td>Mine</td>
                <td class="mine"><?= escape($test['mine']) ?></td>
            </tr>
            <tr>
                <td>Theirs</td>
                <td class="theirs"><?= escape($test['theirs']) ?></td>
            </tr>
            <tr>
                <td>Expected Result</td>
                <td class="expected"><?= escape($test['expected']) ?></td>
            </tr>
            <tr>
                <td>Result</td>
                <td class="result"><?= escape($test['result']) ?></td>
            </tr>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2">
                        <? if (!isset($_REQUEST['only'])) : ?>
                        <a href="?only=<?= $key ?>">Execute test independently.</a>
                        <? endif ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    <? endforeach ?>


    </body>
</html>