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
 * This file is the entry point to the game module. All pages are rendered from here
 *
 * @package    mod_mmogame
 * @copyright  2024 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class mmogame_database {

};

class mmogame_database_moodle extends mmogame_database {

    public $prefix;

    public function __construct() {
        global $CFG;

        $this->prefix = $CFG->prefix;
    }

    public function insert_record($table, $a) {
        global $DB;

        $rec = new StdClass;
        foreach ($a as $name => $value) {
            $rec->$name = $value;
        }
        return $DB->insert_record( $table, $rec);
    }

    public function insert_record_raw($table, $a, $returnid, $customsequence) {
        global $DB;

        $rec = new StdClass;
        foreach ($a as $name => $value) {
            $rec->$name = $value;
        }
        return $DB->insert_record_raw( $table, $rec, $returnid, false, $customsequence);
    }

    public function execute($sql, $params=null) {
        global $DB;

        $DB->execute( $sql, $params);
    }

    public function get_record_select($table, $select, array $params=null, $fields='*') {
        global $DB;

        return $DB->get_record_select( $table, $select, $params, $fields);
    }

    public function get_record_select_first($table, $select, array $params=null, $sort, $fields='*') {
        global $DB;

        $recs = $DB->get_records_select( $table, $select, $params, $sort, $fields, 0, 1);
        foreach ($recs as $rec) {
            return $rec;
        }
        return false;
    }

    public function get_records_select($table, $select, array $params=null, $sort='', $fields='*', $limitfrom=0, $limitnum=0) {
        global $DB;

        return $DB->get_records_select( $table, $select, $params, $sort, $fields, $limitfrom, $limitnum);
    }

    public function count_records_select($table, $select, array $params=null, $countitem="COUNT('*')") {
        global $DB;

        return $DB->count_records_select( $table, $select, $params, $countitem);
    }

    public function get_record_sql($sql, array $params=null) {
        global $DB;

        return $DB->get_record_sql($sql, $params);
    }

    public function get_records_sql($sql, array $params=null, $limitfrom=0, $limitnum=0) {
        global $DB;

        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    }

    public function update_record($table, $a) {
        global $DB;

        $rec = new StdClass;
        foreach ($a as $name => $value) {
            $rec->$name = $value;
        }

        $DB->update_record( $table, $rec);
    }

    public function delete_records_select($table, $select, array $params=null) {
        global $DB;

        $DB->delete_records_select($table, $select, $params);
    }

    public function iif($condition, $iftrue, $iffalse) {
        return "IF($condition,$iftrue,$iffalse)";
    }
}
