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
 * Json file
 *
 * @package    mmogametype_quiz
 * @copyright  2024 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__) . '/../../model/aduel.php');

/**
 * Fill the array $ret with info about the current attempt.
 *
 * @param object $data
 * @param object $game
 * @param array $ret
 */
function mmogame_json_quiz_getattempt($data, $game, &$ret) {
    global $CFG;

    $rinstance = $game->get_rinstance();

    $auserid = mmogame::get_asuerid_from_object( $game->get_db(), $data, $rinstance);
    if ($auserid === false) {
        $ret['errorcode'] = 'invalidauser';
        return;
    }
    $game->login_user( $auserid);

    $ret['dataroot'] = $CFG->dataroot;
    $ret['type'] = $rinstance->type;
    $ret['model'] = $rinstance->model;

    if (isset( $data->nickname) && isset( $data->avatarid) && isset( $data->paletteid)) {
        $info = $game->get_avatar_info( $auserid);
        $game->get_db()->update_record( 'mmogame_aa_grades',
            ['id' => $info->id, 'nickname' => $data->nickname, 'avatarid' => $data->avatarid,
            'colorpaletteid' => $data->paletteid, ]);
    }

    if ($game->get_state() != 0) {
        $attempt = $game->get_attempt();
    } else {
        $attempt = false;
    }

    $game->append_json( $ret, $attempt, $data);
}

/**
 * Update the database about the answer of user and returns to variable $ret informations.
 *
 * @param object $data
 * @param object $game
 * @param array $ret
 */
function mmogame_json_quiz_answer($data, $game, &$ret) {
    $auserid = mmogame::get_asuerid_from_object( $game->get_db(), $data, $game->get_rinstance());
    $game->login_user( $auserid);

    $instance = $game->get_rinstance();
    if ($instance->model == 'step' || $instance->model == 'arguegraph' || $instance->model == 'majority'
        || $instance->model == 'unanimity') {
        return $game->set_answer_model( $data, $ret);
    } else {
        if (!isset( $data->attempt) || $data->attempt == 0) {
            return;
        }

        $attempt = $game->get_db()->get_record_select( 'mmogame_quiz_attempts', 'mmogameid=? AND auserid=? AND id=?',
            [$game->get_id(), $auserid, $data->attempt]);
        if ($attempt === false) {
            return;
        }

        if ($attempt->auserid != $auserid || $attempt->ginstanceid != $instance->id || $attempt->numgame != $instance->numgame) {
            return;
        }
    }

    $query = $game->get_qbank()->load( $attempt->queryid);
    $autograde = true;
    if (isset( $data->subcommand) && $data->subcommand == 'tool2') {
        $autograde = false;
        $ret['tool2'] = 1;
    }
    $iscorrect = $game->set_answer( $attempt, $query, $data->answer, $autograde, $data->submit != 0, $ret);

    $ret['iscorrect'] = $iscorrect ? 1 : 0;
    $ret['correct'] = $query->concept;
    $ret['submit'] = $data->submit;
    $ret['attempt'] = $attempt->id;

    $info = $game->get_avatar_info( $auserid);
    $ret['sumscore'] = $info->sumscore;
    $ret['nickname'] = $info->nickname;
    $ret['rank'] = $game->get_rank_alone( $auserid, 'sumscore');

    $ret['percentcompleted'] = $info->percentcompleted;
    $ret['completedrank'] = $game->get_rank_alone( $auserid, 'percentcompleted');

    if ($instance->model == 'aduel') {
        $aduel = $game->get_aduel();
        $player = ( $aduel->auserid1 == $auserid ? 1 : 2);
        $ret['aduel_player'] = $player;

        if (isset( $data->subcommand) && $data->subcommand === 'tool2') {
            $field = 'tool2numattempt'.$player;
            if ($aduel->$field == 0) {
                $game->get_db()->update_record( 'mmogame_am_aduel_pairs',
                    ['id' => $aduel->id, $field => $attempt->numattempt]);
                $aduel->$field = $attempt->numattempt;
            }

            if ($aduel->$field != 0 && $aduel->$field != null) {
                $ret['tool2'] = $aduel->$field;
            }
        }

        if ($aduel->auserid1 == $auserid) {
            return;
        }

        $attempt1 = $game->get_db()->get_record_select( 'mmogame_quiz_attempts',
            'ginstanceid=? AND auserid=? AND numteam=? AND numattempt=?',
            [$aduel->ginstanceid, $aduel->auserid1, $aduel->id, $attempt->numattempt]);
        if ($attempt1 != false) {
            if ($aduel->auserid2 == $auserid) {
                $ret['aduel_iscorrect'] = $attempt1->iscorrect;
                $ret['aduel_useranswer'] = $attempt1->useranswer;
            }

            $ret['iscorrect'] = $iscorrect ? 1 : 0;
            $query = $game->get_qbank()->load( $attempt->queryid);
            $ret['correct'] = $query->concept;
        }
        if ($game->aduel->isclosed2) {
            $ret['endofgame'] = 1;
        }
        $info = $game->get_avatar_info( $aduel->auserid1);
        $ret['aduel_score'] = $info->sumscore;
        $ret['aduel_rank'] = $game->get_rank_alone( $aduel->auserid1, 'sumscore');
    }
}

/**
 * Fills the variable $ret with information about highscore.
 *
 * @param object $data
 * @param object $game
 * @param array $ret
 */
function mmogame_json_quiz_gethighscore($data, $game, &$ret) {
    $auserid = mmogame::get_asuerid_from_object( $game->get_db(), $data, $game->get_rinstance());
    $game->login_user( $auserid);
    $instance = $game->get_rinstance();
    $game->get_highscore( $data->count, $ret);
}
