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

namespace core_badges\external;

use core\exception\moodle_exception;
use core_badges\badge;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * Class delete_badges
 *
 * @package    core_badges
 * @copyright  2025 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_badges extends external_api {
    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        // This is a placeholder to avoid errors. Implementation to be added later.
        return new external_function_parameters([
            'badgeids' => new external_multiple_structure(
                new external_value(PARAM_TEXT, 'The badge identifiers to delete', VALUE_REQUIRED),
            ),
        ]);
    }

    /**
     * Delete the given badges.
     *
     * @param array $badgeids List of badge identifiers to delete.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(array $badgeids): array {
        global $CFG;
        // This is a placeholder to avoid errors. Implementation to be added later.
        $warnings = [];
        $params = self::validate_parameters(self::execute_parameters(), ['badgeids' => $badgeids]);
        if (empty($CFG->enablebadges)) {
            throw new moodle_exception('badgesdisabled', 'badges');
        }

        foreach ($badgeids as $badgeid) {
            $badge = new badge($badgeid);

            $context = $badge->get_context();
            self::validate_context($context);
            if (!has_capability('moodle/badges:deletebadge', $context)) {
                $warnings[] = [
                    'item' => $badgeid,
                    'warningcode' => 'nopermissions',
                    'message' => get_string('nopermissions', 'error'),
                ];
                continue;
            }
            $badge->delete(false);
        }
        return [
            'result' => empty($warnings),
            'warnings' => $warnings,
        ];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        // This is a placeholder to avoid errors. Implementation to be added later.
        return new external_single_structure([
            'result' => new external_value(PARAM_BOOL, 'True if all badges were deleted successfully'),
            'warnings' => new external_warnings(),
        ]);
    }
}
