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
 * Unit tests for the block_html implementation of the privacy API.
 *
 * @package    block_html
 * @category   test
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_html\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\writer;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use block_html\privacy\provider;

/**
 * Unit tests for the block_html implementation of the privacy API.
 *
 * @copyright  2018 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Get the list of standard format options for comparison.
     *
     * @return \stdClass
     */
    protected function get_format_options() {
        return (object) [
            'overflowdiv' => true,
            'noclean' => true,
        ];
    }

    /**
     * Creates an HTML block on a user.
     *
     * @param   string  $title
     * @param   string  $body
     * @param   string  $format
     * @return  \block_instance
     */
    protected function create_user_block($title, $body, $format) {
        global $USER;

        $configdata = (object) [
            'title' => $title,
            'text' => [
                'itemid' => 19,
                'text' => $body,
                'format' => $format,
            ],
        ];

        $this->create_block($this->construct_user_page($USER));
        $block = $this->get_last_block_on_page($this->construct_user_page($USER));
        $block = block_instance('html', $block->instance);
        $block->instance_config_save((object) $configdata);

        return $block;
    }

    /**
     * Creates an HTML block on a course.
     *
     * @param   \stdClass $course
     * @param   string  $title
     * @param   string  $body
     * @param   string  $format
     * @return  \block_instance
     */
    protected function create_course_block($course, $title, $body, $format) {
        global $USER;

        $configdata = (object) [
            'title' => $title,
            'text' => [
                'itemid' => 19,
                'text' => $body,
                'format' => $format,
            ],
        ];

        $this->create_block($this->construct_course_page($course));
        $block = $this->get_last_block_on_page($this->construct_course_page($course));
        $block = block_instance('html', $block->instance);
        $block->instance_config_save((object) $configdata);

        return $block;
    }

    /**
     * Creates an HTML block on a page.
     *
     * @param \page $page Page
     */
    protected function create_block($page) {
        $page->blocks->add_block_at_end_of_default_region('html');
    }

    /**
     * Get the last block on the page.
     *
     * @param \page $page Page
     * @return \block_html Block instance object
     */
    protected function get_last_block_on_page($page) {
        $blocks = $page->blocks->get_blocks_for_region($page->blocks->get_default_region());
        $block = end($blocks);

        return $block;
    }

    /**
     * Constructs a Page object for the User Dashboard.
     *
     * @param   \stdClass       $user User to create Dashboard for.
     * @return  \moodle_page
     */
    protected function construct_user_page(\stdClass $user) {
        $page = new \moodle_page();
        $page->set_context(\context_user::instance($user->id));
        $page->set_pagelayout('mydashboard');
        $page->set_pagetype('my-index');
        $page->blocks->load_blocks();
        return $page;
    }

    /**
     * Constructs a Page object for the User Dashboard.
     *
     * @param   \stdClass       $course Course to create Dashboard for.
     * @return  \moodle_page
     */
    protected function construct_course_page(\stdClass $course) {
        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        $page->set_pagelayout('standard');
        $page->set_pagetype('course-view');
        $page->set_course($course);
        $page->blocks->load_blocks();
        return $page;
    }

    /**
     * Test that a block on the dashboard is exported.
     */
    public function test_user_block(): void {
        $this->resetAfterTest();

        $title = 'Example title';
        $content = 'Example content';
        $format = FORMAT_PLAIN;

        // Test setup.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $block = $this->create_user_block($title, $content, $format);
        $context = \context_block::instance($block->instance->id);

        // Get the contexts.
        $contextlist = provider::get_contexts_for_userid($user->id);

        // Only the user context should be returned.
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Export the data.
        $this->export_context_data_for_user($user->id, $context, 'block_html');
        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        // Check the data.
        $data = $writer->get_data([]);
        $this->assertInstanceOf('stdClass', $data);
        $this->assertEquals($title, $data->title);
        $this->assertEquals(format_text($content, $format, $this->get_format_options()), $data->content);

        // Delete the context.
        provider::delete_data_for_all_users_in_context($context);

        // Re-fetch the contexts - it should no longer be returned.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Test that a block on the dashboard which is not configured is _not_ exported.
     */
    public function test_user_block_unconfigured(): void {
        global $DB;

        $this->resetAfterTest();

        $title = 'Example title';
        $content = 'Example content';
        $format = FORMAT_PLAIN;

        // Test setup.
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);
        $block = $this->create_user_block($title, $content, $format);
        $block->instance->configdata = '';
        $DB->update_record('block_instances', $block->instance);
        $block = block_instance('html', $block->instance);

        $context = \context_block::instance($block->instance->id);

        // Get the contexts.
        $contextlist = provider::get_contexts_for_userid($user->id);

        // Only the user context should be returned.
        $this->assertCount(1, $contextlist);
        $this->assertEquals($context, $contextlist->current());

        // Export the data.
        $this->export_context_data_for_user($user->id, $context, 'block_html');
        /** @var \core_privacy\tests\request\content_writer $writer */
        $writer = \core_privacy\local\request\writer::with_context($context);
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Test that a block on the dashboard is exported.
     */
    public function test_user_multiple_blocks_exported(): void {
        $this->resetAfterTest();

        $title = 'Example title';
        $content = 'Example content';
        $format = FORMAT_PLAIN;

        // Test setup.
        $blocks = [];
        $contexts = [];
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $block = $this->create_user_block($title, $content, $format);
        $context = \context_block::instance($block->instance->id);
        $contexts[$context->id] = $context;

        $block = $this->create_user_block($title, $content, $format);
        $context = \context_block::instance($block->instance->id);
        $contexts[$context->id] = $context;

        // Get the contexts.
        $contextlist = provider::get_contexts_for_userid($user->id);

        // There are now two blocks on the user context.
        $this->assertCount(2, $contextlist);
        foreach ($contextlist as $context) {
            $this->assertTrue(isset($contexts[$context->id]));
        }

        // Turn them into an approved_contextlist.
        $approvedlist = new approved_contextlist($user, 'block_html', $contextlist->get_contextids());

        // Delete using delete_data_for_user.
        provider::delete_data_for_user($approvedlist);

        // Re-fetch the contexts - it should no longer be returned.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(0, $contextlist);
    }

    /**
     * Test that a block on the dashboard is not exported.
     */
    public function test_course_blocks_not_exported(): void {
        $this->resetAfterTest();

        $title = 'Example title';
        $content = 'Example content';
        $format = FORMAT_PLAIN;

        // Test setup.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        $block = $this->create_course_block($course, $title, $content, $format);
        $context = \context_block::instance($block->instance->id);

        // Get the contexts.
        $contextlist = provider::get_contexts_for_userid($user->id);

        // No blocks should be returned.
        $this->assertCount(0, $contextlist);
    }

    /**
     * Test that a block on the dashboard is exported.
     */
    public function test_mixed_multiple_blocks_exported(): void {
        $this->resetAfterTest();

        $title = 'Example title';
        $content = 'Example content';
        $format = FORMAT_PLAIN;

        // Test setup.
        $contexts = [];

        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);
        $this->getDataGenerator()->enrol_user($user->id, $course->id, 'editingteacher');

        $block = $this->create_course_block($course, $title, $content, $format);
        $context = \context_block::instance($block->instance->id);

        $block = $this->create_user_block($title, $content, $format);
        $context = \context_block::instance($block->instance->id);
        $contexts[$context->id] = $context;

        $block = $this->create_user_block($title, $content, $format);
        $context = \context_block::instance($block->instance->id);
        $contexts[$context->id] = $context;

        // Get the contexts.
        $contextlist = provider::get_contexts_for_userid($user->id);

        // There are now two blocks on the user context.
        $this->assertCount(2, $contextlist);
        foreach ($contextlist as $context) {
            $this->assertTrue(isset($contexts[$context->id]));
        }
    }

    /**
     * Test that only users with a user context HTML block are fetched.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $component = 'block_html';
        $title = 'Block title';
        $content = 'Block content';
        $blockformat = FORMAT_PLAIN;

        // Create a user with a user context HTML block.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $userblock = $this->create_user_block($title, $content, $blockformat);
        $usercontext = \context_block::instance($userblock->instance->id);

        // Create a user with a course context HTML block.
        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user2);

        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->enrol_user($user2->id, $course->id, 'editingteacher');
        $courseblock = $this->create_course_block($course, $title, $content, $blockformat);
        $coursecontext = \context_block::instance($courseblock->instance->id);

        // Ensure only the user with a user context HTML block is returned.
        $userlist = new \core_privacy\local\request\userlist($usercontext, $component);
        \block_html\privacy\provider::get_users_in_context($userlist);

        $this->assertCount(1, $userlist);

        $expected = [$user1->id];
        $actual = $userlist->get_userids();

        $this->assertEquals($expected, $actual);

        // Ensure passing the course context returns no users.
        $userlist = new \core_privacy\local\request\userlist($coursecontext, $component);
        \mod_forum\privacy\provider::get_users_in_context($userlist);
        $this->assertEmpty($userlist);
    }

    /**
     * Test that data for users in approved userlist is deleted.
     */
    public function test_delete_data_for_users(): void {
        $this->resetAfterTest();

        $component = 'block_html';
        $title = 'Block title';
        $content = 'Block content';
        $blockformat = FORMAT_PLAIN;

        // Create 2 user swith a user context HTML blocks.
        $user1 = $this->getDataGenerator()->create_user();
        $this->setUser($user1);

        $block1 = $this->create_user_block($title, $content, $blockformat);
        $context1 = \context_block::instance($block1->instance->id);

        $user2 = $this->getDataGenerator()->create_user();
        $this->setUser($user2);
        $block2 = $this->create_user_block($title, $content, $blockformat);
        $context2 = \context_block::instance($block2->instance->id);

        // Create and populate the userlists.
        $userlist1 = new \core_privacy\local\request\userlist($context1, $component);
        \block_html\privacy\provider::get_users_in_context($userlist1);
        $userlist2 = new \core_privacy\local\request\userlist($context2, $component);
        \block_html\privacy\provider::get_users_in_context($userlist2);

        // Ensure both members are included.
        $this->assertCount(1, $userlist1);
        $this->assertCount(1, $userlist2);

        // Convert $userlist1 into an approved_contextlist.
        $approvedlist = new approved_userlist($context1, 'block_html', $userlist1->get_userids());

        // Delete using delete_data_for_user.
        provider::delete_data_for_users($approvedlist);

        // Re-fetch users in the contexts - only the first one should now be empty.
        $userlist1 = new \core_privacy\local\request\userlist($context1, $component);
        \block_html\privacy\provider::get_users_in_context($userlist1);
        $this->assertCount(0, $userlist1);

        $userlist2 = new \core_privacy\local\request\userlist($context2, $component);
        \block_html\privacy\provider::get_users_in_context($userlist2);
        $this->assertCount(1, $userlist2);
    }
}
