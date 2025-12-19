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
 * Version details
 *
 * @package    block_socialflow
 * @copyright  2024 Zabelle Motte (UCLouvain)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


/**
 * Renderer for the SocialFlow block.
 *
 * Provides functions to use default Moodle help icon.
 *
 * @package    block_socialflow
 * @copyright  Zabelle Motte (UCLouvain)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_socialflow_renderer extends plugin_renderer_base{
    /**
     * Render a help icon for a given string identifier.
     *
     * @param string $identifier The identifier of the help string.
     * @param string $component The language component containing the string.
     * @return string HTML for the help icon.
     */
    public function help_icon($identifier, $component): string {
        // Utilise l'icône d'aide par défaut de Moodle.
        $helptext = get_string($identifier, $component);
        return $this->output->pix_icon('i/help', $helptext);
    }
}
