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
 * Library functions for overview.
 *
 * @package   block_socialflow
 * @copyright 2024 Zabelle Motte (UCLouvain)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

    $preferences['socialflow_optionchoice'] = [
        'null' => NULL_NOT_ALLOWED,
        'default' => 14,
        'type' => PARAM_ALPHA,
        'choices' => [14, 7, 3, 1],
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];

    $preferences['socialflow_courseschoice'] = [
        'null' => NULL_ALLOWED,
        'default' => null,
        'type' => PARAM_RAW,
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];

    $preferences['socialflow_typechoice'] = [
        'null' => NULL_NOT_ALLOWED,
        'default' => 'both',
        'type' => PARAM_ALPHA,
        'choices' => ['consult', 'contrib', 'both'],
        'permissioncallback' => [core_user::class, 'is_current_user'],
    ];

    return $preferences;
