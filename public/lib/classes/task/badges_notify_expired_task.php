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

namespace core\task;

use core_badges\badge;

/**
 * Class badges_notify_expired_task
 *
 * @package    core
 * @copyright  2026 Southampton Solent University {@link https://www.solent.ac.uk}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class badges_notify_expired_task extends scheduled_task {
    #[\Override]
    public function get_name() {
        return get_string('taskbadgesnotifyexpired', 'admin');
    }

    #[\Override]
    public function execute() {
        global $CFG, $DB;
        if (empty($CFG->enablebadges)) {
            return;
        }
        require_once($CFG->libdir . '/badgeslib.php');
        $now = time();
        $params = [
            'coursevisible' => 1,
            'usersuspended' => 0,
            'userdeleted' => 0,
            'notifywhenexpires' => 1,
            'expirednotified' => 0,
            'expiredate' => $now,
            'expireperiod' => $now,
        ];
        $sql = "SELECT b.id, bi.userid, bi.uniquehash, bi.dateissued
        FROM {badge} b
            JOIN {badge_issued} bi ON bi.badgeid = b.id
            JOIN {course} c ON c.id = b.courseid AND c.visible = :coursevisible
            JOIN {user} u ON u.id = bi.userid AND u.suspended = :usersuspended AND u.deleted = :userdeleted
        WHERE b.notifywhenexpires = :notifywhenexpires
            AND bi.expirednotified = :expirednotified
            AND (b.expiredate < :expiredate OR (b.expireperiod + bi.dateissued < :expireperiod))";

        $expiredbadges = $DB->get_records_sql($sql, $params);
        foreach ($expiredbadges as $expiredbadge) {
            $badge = new badge($expiredbadge->id);
            badges_notify_badge_expired($badge, $expiredbadge->userid, $expiredbadge->uniquehash, $expiredbadge->dateissued);
        }
        $tracemessage = count($expiredbadges) . " expired badges were found";
        if (count($expiredbadges) > 0) {
            $tracemessage .= " and participants notified.";
        }
        mtrace($tracemessage);
    }
}
