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
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installation to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the methods of database_manager class
//
// Please do not forget to use upgrade_set_timeout()
// before any action that may take longer time to finish.

/**
 * This code run after the installation of MMOGame module and fills tables avatars and colorpalettes.
 *
 * @package    mod_game
 * @copyright  2024 Vasilis Daloukas
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Function to execute custom code after the module is installed.
 */
function xmldb_mmogame_install() {
    global $DB;

    // Check if it needed to insert data at table mmogame_aa_colorpalettes.
    $recs = $DB->get_records_select( 'mmogame_aa_colorpalettes', '', null, '', '*', 0, 1);
    if (count( $recs) == 0) {
        xmldb_mmogame_install_import( 'mmogame_aa_colorpalettes');
    }

    $recs = $DB->get_records_select( 'mmogame_aa_avatars', '', null, '', '*', 0, 1);
    if (count( $recs) == 0) {
        xmldb_mmogame_install_avatars();
    }

    return true;
}

function xmldb_mmogame_install_import( $table) {
    global $DB;

    // Path to the CSV file.
    $file = dirname(__FILE__).DIRECTORY_SEPARATOR.$table.'.csv';

    // Open the CSV file.
    if (($handle = fopen( $file, "r")) === false) {
        return false;
    }

    // Read the first line as header (field names).
    $header = fgetcsv($handle, 1000, ',');

    // Start reading the records.
    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
        // Create a new record object.
        $record = new stdClass();

        $rec = new stdClass();
        // Dynamically map the values from the CSV to corresponding fields.
        foreach ($header as $index => $fieldname) {
            $rec->$fieldname = $data[$index];
        }
        // Insert the record into the Moodle database table.
        $DB->insert_record( $table, $rec);
    }
    fclose( $handle);

    return true;
}

function xmldb_mmogame_install_avatars() {
    global $DB;

    $dir = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'avatars';
    $d = dir( $dir);

    $map = [];
    while (false !== ($entry = $d->read())) {
        if (substr( $entry, 0, 1) != '.') {
            $d2 = dir( $dir.DIRECTORY_SEPARATOR.DIRECTORY_SEPARATOR.$entry);
            while (false !== ($entry2 = $d2->read())) {
                if (substr( $entry2, 0, 1) == '.') {
                    continue;
                }
                $rec = $DB->get_record_select( 'mmogame_aa_avatars', 'directory=? AND filename=?',
                    [$entry, $entry2]);
                if ($rec != false) {
                    $map[$rec->id] = $rec->id;
                    continue;
                }
                $rec = new stdClass();
                $rec->directory = $entry;
                $rec->filename = $entry2;
                $rec->numused = 0;
                $rec->randomkey = mt_rand();
                $id = $DB->insert_record( 'mmogame_aa_avatars', $rec);
                $map[$id] = $id;
            }
            $d2->close();
        }
    }
    $d->close();

    $recs = $DB->get_records_select( 'mmogame_aa_avatars', '');
    foreach ($recs as $rec) {
        if (!array_key_exists( $rec->id, $map)) {
            $updrec = new stdClass();
            $updrec->id = $rec->id;
            $updrec->ishidden = 1;
            $DB->update_record( 'mmogame_aa_avatars', $updrec);
        }
    }
}