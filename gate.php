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
 * @package   mod_mmogame
 */

?>
<?php

require_once( dirname(__FILE__)."/../../config.php");
header("Cache-Control: max-age=604800"); // Is 7days.

?>
<!DOCTYPE html>
<html lang="el">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="MMOGAME Gate.">
<title>MMOGAME gate</title>
<style type="text/css">
<?php echo file_get_contents( dirname(__FILE__)."/css/mmogame.css"); ?>
</style>
<body onload="runmmogame()">

<?php

require_once(dirname(__FILE__) . '/../../config.php');
require_once( 'database/moodle.php');
require_once(dirname(__FILE__) . '/mmogame.php');

$db = new mmogame_database_moodle();
$pin = required_param('pin', PARAM_INT);

$instance = $DB->get_record_select( 'mmogame_aa_instances', 'pin=?', [$pin]);
$rgame = $DB->get_record_select( 'mmogame', 'id=?', [$instance->mmogameid]);
$game = mmogame::getgame( $db, $rgame->id, $pin);
$type = $game->get_type();
$color = $DB->get_record_select( 'mmogame_aa_colorpalettes', 'id=?', [2]);
$colors = "[$color->color1, $color->color2, $color->color3, $color->color4, $color->color5]";

$pin = required_param('pin', PARAM_INT);
$usercode = '1';

echo "<script src=\"js/google.fastbutton.js\" async> </script>";

echo "<script>\n";
mmogame_change_javascript( 'js/mmogame.js');
mmogame_change_javascript( 'js/gate.js');
mmogame_change_javascript( 'type/quiz/js/quiz.js');
mmogame_change_javascript( 'type/quiz/js/quiz_aduel.js');

?>
    function runmmogame() {
        let game = new mmogame_gate();
        game.repairColors( <?php echo $colors;?>)
        game.open(<?php echo "\"{$CFG->wwwroot}/mod/mmogame/json.php\",\"".$game->get_rgame()->id."\",
            {$instance->pin},{$usercode},\"{$instance->kinduser}\"" ?>);
    }
</script>
<?php

function mmogame_change_javascript( $file) {
    global $instance;

    $s = file_get_contents( dirname(__FILE__).'/'.$file);

    $source = $dest = [];

    // Start from [LANG and finish with ].
    preg_match_all('/\[LANG_[A-Z0-9_]+\]/', $s, $matches);

    foreach ($matches as $stringm) {
        foreach ($stringm as $string) {
            $source[] = $string;
            $name = 'js_'.strtolower( substr( trim($string, '[]'), 5));
            $dest[] = get_string( $name, 'mmogametype_'.$instance->type);
        }
    }

    // Start from [LANGM (for whole module) and finish with ].
    preg_match_all('/\[LANGM_[A-Z0-9_]+\]/', $s, $matches);
    foreach ($matches as $stringm) {
        foreach ($stringm as $string) {
            $source[] = $string;
            $name = 'js_'.strtolower( substr( trim($string, '[]'), 6));
            $dest[] = get_string( $name, 'mmogame');
        }
    }

    echo str_replace( $source, $dest, $s);
}
