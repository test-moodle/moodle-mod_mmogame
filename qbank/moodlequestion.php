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
 * lib
 *
 * @package    mod_mmogame
 * @copyright  2024 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class mmogameqbank_moodlequestion extends mmogameqbank {

    protected function get_moodle_version() {
        global $DB;

        static $smoodleversion = null;

        if ($smoodleversion != null) {
            return $smoodleversion;
        }

        $rec = $DB->get_record_select( 'config', "name='release'");
        if ($rec == false) {
            return $smoodleversion = '';
        } else {
            $a = explode( '.', $rec->value);
            return $smoodleversion = sprintf( '%02u.%02u', $a[0], $a[1]);
        }
    }

    public function load( $id, $loadextra = true, $fields = '') {
        $params = $this->mmogame->get_params();
        $needname = $params != false && $params->debugname;
        if ($fields == '') {
            $fields = 'qbe.id, q.id as questionid,q.qtype,q.name,q.questiontext as definition';
            if ($needname) {
                $fields .= ',q.name';
            }
        }
        $db = $this->mmogame->get_db();
        $sql = "SELECT $fields ".
            " FROM {$db->prefix}question q, {$db->prefix}question_bank_entries qbe,{$db->prefix}question_versions qv ".
            " WHERE qbe.id=qv.questionbankentryid AND qv.questionid=q.id AND qbe.id=? ".
            " ORDER BY qv.version DESC";
        $recs = $db->get_records_sql( $sql, [$id], 0, 1);
        $ret = false;
        foreach ($recs as $rec) {
            $ret = $rec;
        }

        if ($ret == false) {
            return $ret;
        }

        if ($this->mmogame->get_rinstance()->striptags) {
            $ret->definition = strip_tags( $ret->definition);
        }
        if ($needname) {
            $ret->definition = $ret->definition.' ['.$ret->name.']';
        }

        if ($loadextra == false) {
            return $ret;
        }

        return $this->load2( $ret);
    }

    protected function load2( &$query) {
        $recs = $this->mmogame->get_db()->get_records_select( 'question_answers', 'question=?',
            [$query->questionid], 'fraction DESC', 'id,answer,fraction');
        unset( $query->correctid);
        $first = true;
        $striptags = $this->mmogame->get_rinstance()->striptags;

        foreach ($recs as $rec) {
            if ($striptags) {
                $rec->answer = strip_tags( $rec->answer);
            }

            if ($query->qtype == 'shortanswer') {
                $query->concept = $rec->answer;
                break;
            }
            if (!isset( $query->answers)) {
                $query->answers = [];
            }
            if (!isset( $query->correctid)) {
                $query->correctid = $rec->id;
            }
            $info = new stdClass();
            $info->id = $rec->id;
            $info->answer = $rec->answer;            
            $info->fraction = $rec->fraction;
            if ($first) {
                $first = false;
                $query->concept = $rec->id;
            }
            $query->answers[] = $info;
        }
        if ($query->qtype == 'multichoice') {
            $rec = $this->mmogame->get_db()->get_record_select( 'qtype_multichoice_options', 'questionid=?',
                [$query->id]);
            $query->multichoice = $rec;
        }

        return $query;
    }

    public function load_json( $game, &$ret, $num, $id, $layout, &$files, $fillconcept,
    &$filewidth, &$fileheight, $maxwidth = 0, $maxheight = 0) {
        $courseid = $game->get_rgame()->course;
        $db = $game->get_db();
        $files = $filewidth = $fileheight = [];

        $rec = $this->load( $id, true);
        $contextid = 0;

        $filekeys = $targetwidth = $targetheight = [];

        $ret['qtype'.$num] = $rec->qtype;
        $definition = self::checkforimages( $courseid, $db, $rec->definition, $rec->id,
            $courseid, 'course', 'question', 'questiontext', $maxwidth, $maxheight, true,
            $files, $filekeys, $filewidth, $fileheight);

        $ret['definition'.$num] = $definition;
        if ($this->is_shortanswer( $rec)) {
            if ($fillconcept) {
                $ret['concept'.$num] = $rec->concept;
            }
        } else if ($this->is_multichoice( $rec)) {
            $ret['single'] = $rec->multichoice->single;
            $l = $layout == '' ? [] : explode( ',', $layout);

            for ($i = 1; $i <= count( $rec->answers); $i++) {
                if (array_search( $i, $l) === false) {
                    $l[] = $i;
                }
            }

            $d = [];
            foreach ($rec->answers as $info) {
                $d[] = $info;
            }
            $n = 0;
            foreach ($rec->answers as $info) {
                $info = $d[$l[$n] - 1];
                $n++;
                $ret['answer'.$num.'_'.$n] = self::checkforimages( $courseid, $db,
                    $info->answer, $info->id, $courseid, 'course', 'question', 'answer',
                    $maxwidth, $maxheight, true, $files, $filekeys, $filewidth, $fileheight);
                $ret['answerid'.$num.'_'.$n] = $info->id;
            }
            $ret['answers'.$num] = $n;
        }

        return $rec;
    }

    protected function loads( $sqlid, $loadextra = true, $fields='id,qtype,questiontext as definition') {
        $recs = $this->mmogame->get_db()->get_records_select( 'question', "id IN ($sqlid)", null, '', $fields);

        if ($recs === false || $loadextra == false) {
            return $recs;
        }

        foreach ($recs as $rec) {
            $this->get_record2( $rec);
        }

        return $recs;
    }

    protected static function get_answerid( $query, $answer) {
        if ($query->qtype == 'shortanswer') {
            return null;
        }

        foreach ($query->answers as $rec) {
            if ($rec->answer == $answer) {
                return $rec->id;
            }
        }
        return null;
    }

    public function get_correct( $query, $mmogame) {
        if ($query->qtype == 'shortanswer') {
            return $query->concept;
        } else {
            return $this->get_correct_multichoice( $query, $mmogame);
        }
    }

    protected function get_correct_multichoice( $query, $mmogame) {
        if ($query->multichoice->single) {
            $id = null;
            $max = 0;
            foreach ($query->answers as $answer) {
                if ($answer->fraction > $max) {
                    $max = $answer->fraction;
                    $id = intval( $answer->id);
                    break;
                }
            }
            return $id;
        } else {
            $ret = '';
            foreach ($query->answers as $answer) {
                if ($answer->fraction > 0) {
                    $ret .= ($ret != '' ? ',' : '').$intval( $answer->id);
                }
            }
            return $ret;
        }
    }

    public function is_correct( $query, $useranswer, $mmogame, &$fraction) {
        if ($query->qtype == 'shortanswer') {
            return $this->is_correct_shortanswer( $query, $useranswer, $mmogame, $fraction);
        } else {
            return $this->is_correct_multichoice( $query, $useranswer, $mmogame, $fraction);
        }
    }

    protected function is_correct_shortanswer( $query, $useranswer, $mmogame, &$fraction) {
        if ($mmogame->get_rgame()->casesensitive) {
            $fraction = 1.0;
            return $query->concept == $useranswer;
        } else {
            $fraction = 0.0;
            return strtoupper( $query->concept) == strtoupper( $useranswer);
        }
    }

    protected function is_correct_multichoice( $query, $useranswer, $mmogame, &$fraction) {
        if ($query->multichoice->single) {
            return $this->is_correct_multichoice_single1( $query, $useranswer, $mmogame, $fraction);
        } else {
            return $this->is_correct_multichoice_single0( $query, $useranswer, $mmogame, $fraction);
        }
    }

    protected function is_correct_multichoice_single1( $query, $useranswer, $mmogame, &$fraction) {
        $fraction = null;
        foreach ($query->answers as $answer) {
            if (intval( $answer->id) == intval( $useranswer)) {
                $fraction = $answer->fraction;
                break;
            }
        }

        return abs( $fraction - 1) < 0.0000001;
    }

    protected function is_correct_multichoice_single0( $query, $useranswer, $mmogame, &$fraction) {
        $fraction = 0.0;
        $aids = explode( ',', $useranswer);
        foreach ($query->answers as $answer) {
            if (array_search( $answer->id, $aids) === false) {
                continue;
            }
            $fraction += $answer->fraction;
        }

        return abs( $fraction - 1) < 0.0000001;
    }

    public function needs_layout( $query) {
        return $query->qtype == 'multichoice';
    }

    public function get_layout( $query) {
        if ($query->qtype != 'multichoice') {
            return null;
        }
        $items = [];
        $n = count( $query->answers);
        for ($i = 1; $i <= $n; $i++) {
            $items[] = $i;
        }
        $layout = '';
        while (count( $items)) {
            $pos = random_int( 0, count( $items) - 1);
            if ($layout != '') {
                $layout .= ',';
            }
            $layout .= $items[$pos];
            array_splice( $items, $pos, 1);
        }
        return $layout;
    }

    public function set_layout( &$query, $layout) {
        if (!$this->is_multichoice( $query)) {
            return;
        }
        $inp = $query->answers;
        $query->answers = [];

        $a = explode( ',', $layout);
        foreach ($a as $pos) {
            $query->answers[] = $inp[intval( $pos) - 1];
        }
    }

    public static function is_multichoice( $query) {
        return $query->qtype == 'multichoice';
    }

    public static function is_shortanswer( $query) {
        return $query->qtype == 'shortanswer';
    }

    public function get_queries_ids() {

        $rgame = $this->mmogame->get_rgame();
        $qtypes = '';
        $qtypes .= ($qtypes != '' ? ',' : '')."'shortanswer'";
        $qtypes .= ($qtypes != '' ? ',' : '')."'multichoice'";

        if ( $qtypes == '') {
            return false;
        }
        $categoryids = $rgame->qbankparams != '' ? $rgame->qbankparams : '0';
        $where = "qtype IN ($qtypes)";
        $db = $this->mmogame->get_db();
        $table = "{$db->prefix}question q";

        $table .= ",{$db->prefix}question_bank_entries qbe,{$db->prefix}question_versions qv  ";
        if (strpos( $categoryids, ',') === false) {
            $where2 = ' qbe.questioncategoryid='.$categoryids;
        } else {
            $where2 = ' qbe.questioncategoryid IN ('.$categoryids.')';
        }
        $where .= ' AND qbe.id=qv.questionbankentryid AND qv.questionid=q.id AND '.$where2;

        $recs = $db->get_records_sql( "SELECT q.id,qtype,qbe.id as qbeid FROM $table WHERE $where ORDER BY qv.version DESC");

        $ret = $map = [];
        foreach ($recs as $rec) {
            if (array_key_exists( $rec->qbeid, $map)) {
                continue;
            }
            $map[$rec->qbeid] = $rec->qbeid;
            $ret[$rec->id] = $rec->id;
        }

        return $map;
    }

    public function load_all() {
        $fields = 'id,qtype,questiontext as definition';
        $rgame = $this->mmogame->get_rgame();
        $qtypes = '';
        if ($rgame->useshortanswer) {
            $qtypes .= ($qtypes != '' ? ',' : '')."'shortanswer'";
        }
        if ($rgame->usemultichoice) {
            $qtypes .= ($qtypes != '' ? ',' : '')."'multichoice'";
        }
        if ( $qtypes == '') {
            return false;
        }
        $categoryids = $rgame->qbankparams != '' ? $rgame->qbankparams : '0';
        $where = "category IN ({$categoryids}) AND qtype IN ($qtypes)";

        $db = $this->mmogame->get_db();
        $recs = $db->get_records_sql( "SELECT $fields FROM {$db->prefix}question WHERE $where");
        $ret = [];
        foreach ($recs as $rec) {
            $this->load2( $rec, false);
            $ret[$rec->id] = $rec;
        }

        return $ret;
    }

    public function get_queries_count() {
        $rgame = $this->mmogame->get_rgame();
        $qtypes = '';
        if ($rgame->useshortanswer) {
            $qtypes .= ($qtypes != '' ? ',' : '')."'shortanswer'";
        }
        if ($rgame->usemultichoice) {
            $qtypes .= ($qtypes != '' ? ',' : '')."'multichoice'";
        }
        if ( $qtypes == '') {
            return false;
        }
        $db = $this->mmogame->get_db();
        $categoryids = $rgame->qbankparams != '' ? $rgame->qbankparams : '0';
        $table = $db->prefix.'question q';
        $where = "qtype IN ($qtypes)";

        if ($this->get_moodle_version() >= '04.00') {
            $table .= ",{$db->prefix}question_bank_entries qbe ";
            $where .= ' AND qbe.questioncategoryid='.$categoryids." AND qbe.id=q.id";
        } else {
            $where .= " AND category IN ({$categoryids})";
        }

        $db = $this->mmogame->get_db();

        $rec = $db->get_record_sql( "SELECT COUNT(*) as c FROM $table WHERE $where");

        return $rec->c;
    }

    public static function checkforimages( $courseid, $db, $s, $itemid, $instanceid, $module, $component,
    $filearea, $maxwidth, $maxheight, $exportimages, &$files, &$filekeys, &$filewidth, &$fileheight) {
        $fs = false;

        $contextid = 0;
        for (;;) {
            $find = '@@PLUGINFILE@@/';
            $pos = strpos( $s, $find);
            if ($pos != false) {
                $pos2 = strpos( $s, '"', $pos);
                if ($pos2 != false) {
                    if ($contextid == 0) {
                        if ($module == 'course') {
                            $contextid = context_course::instance( $instanceid)->id;
                        } else {
                            $sql = "SELECT cm.id FROM {$db->prefix}course_modules cm, {$db->prefix}modules m".
                               " WHERE cm.module = m.id AND cm.course=? AND cm.instance=? AND m.name='{$module}'";
                            $cm = $db->get_record_sql( $sql, [$courseid, $instanceid]);
                            $contextid = context_module::instance( $cm->id)->id;
                        }
                    }

                    $filename = urldecode( substr( $s, $pos + strlen( $find), $pos2 - $pos - strlen( $find)));
                    $pos3 = strpos( $filename, '?');
                    if ($pos3 != false) {
                        $filename = substr( $filename, 0, $pos3);
                    }

                    if ($fs === false) {
                        $fs = get_file_storage();
                    }
                    $filepath = '/';
                    $file = $fs->get_file($contextid, $component, $filearea, $itemid, $filepath, $filename);
                    if (!$file) {
                        return $s; // The file does not exist.
                    }

                    $guid = $file->get_contenthash();
                    $files[$guid] = mmogame_CheckForImages_base64( $db, $file, $maxwidth, $maxheight, $instanceid,
                        $targetwidth, $targetheight);
                    $filekeys[] = $guid;
                    $filewidth[$guid] = $targetwidth;
                    $fileheight[$guid] = $targetheight;
                    $s = substr( $s, 0, $pos).'@@GUID@@'.$guid.substr( $s, $pos2);
                    continue;
                }
            }
            break;
        }

        return $s;
    }
}
