<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This file contains the definition for the class mmogame
 *
 * This class provides all the functionality for the new mmogame module.
 *
 * @package    mmogame_quiz
 * @copyright  2024 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class mmogame_js_quiz {
    public static function init( $game, $usercode) {
        global $CFG, $USER;

        $instance = $game->get_rinstance();
?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Quiz game.">
<title>Quiz Game</title>
<style type="text/css">
        <?php
        echo file_get_contents( dirname(__FILE__)."/../../mmogame.css");
        ?>
</style>
<body onload="runmmogame()">
<script>
        <?php
        echo file_get_contents( dirname(__FILE__)."/../../js/mmogame.js");
        echo file_get_contents( dirname(__FILE__)."/js/quiz.js");
        if ($instance->model == 'arguegraph' || $instance->model == 'unanimity') {
            echo file_get_contents( dirname(__FILE__).'/js/quiz_majority.js');
        }
        if ($instance->model != 'alone') {
            echo file_get_contents( dirname(__FILE__).'/js/quiz_'.$instance->model.'.js');
        }
        ?>

        function runmmogame() {
            var game = new <?php echo 'mmogame_quiz_'.$instance->model;?>();
            game.setKindUser( <?php echo $instance->kinduser == null ? "'usercode'" : "'$instance->kinduser'"; ?>);
            game.openGame(<?php echo "\"{$CFG->wwwroot}/mod/mmogame/json.php\",\"".
                $game->get_rgame()->guidgame."\",{$instance->pin},{$usercode},\"{$instance->kinduser}\"" ?>);
        }
        </script>
        <?php
    }
}
