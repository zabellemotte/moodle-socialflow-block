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
 * Class block_socialflow
 *
 * @copyright  2024  Zabelle Motte (UCLouvain)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 define([], function() {
    return {
        init: function() {

            let sftogg0 = document.getElementById('socialflow_optionselectopener');
            let sftogg1 = document.getElementById('socialflow_courseselectopener');
            let sftogg2 = document.getElementById('socialflow_typeselectopener');
            let sftogg3 = document.getElementById('socialflow_itemnumselectopener');
            let sftogg4 = document.getElementById('socialflow_helpopener');
            let sfd0 = document.getElementById('socialflow_optionselectblock');
            let sfd1 = document.getElementById('socialflow_courseselectblock');
            let sfd2 = document.getElementById('socialflow_typeselectblock');
            let sfd3 = document.getElementById('socialflow_itemnumselectblock');
            let sfd4 = document.getElementById('socialflow_helpblock');
            sftogg0.addEventListener('click', () => {
                if (getComputedStyle(sfd0).display != 'none') {
                    sfd0.style.display = 'none';
                } else {
                    sfd0.style.display = 'block';
                    sfd1.style.display = 'none';
                    sfd2.style.display = 'none';
                    sfd3.style.display = 'none';
                    sfd4.style.display = 'none';
                 }
              });
              sftogg1.addEventListener('click', () => {
                  if (getComputedStyle(sfd1).display != 'none') {
                      sfd1.style.display = 'none';
                  } else {
                      sfd0.style.display = 'none';
                      sfd1.style.display = 'block';
                      sfd2.style.display = 'none';
                      sfd3.style.display = 'none';
                      sfd4.style.display = 'none';
                  }
              });
              sftogg2.addEventListener('click', () => {
                  if (getComputedStyle(sfd2).display != 'none') {
                      sfd2.style.display = 'none';
                   } else {
                      sfd0.style.display = 'none';
                      sfd1.style.display = 'none';
                      sfd2.style.display = 'block';
                      sfd3.style.display = 'none';
                      sfd4.style.display = 'none';
                   }
              });    
              sftogg3.addEventListener('click', () => {
                  if (getComputedStyle(sfd3).display != 'none') {
                      sfd3.style.display = 'none';
                   } else {
                      sfd0.style.display = 'none';
                      sfd1.style.display = 'none';
                      sfd2.style.display = 'none';
                      sfd3.style.display = 'block';
                      sfd4.style.display = 'none';
                   }
              });    
              sftogg4.addEventListener('click', () => {
                  if (getComputedStyle(sfd4).display != 'none') {
                      sfd4.style.display = 'none';
                   } else {
                      sfd0.style.display = 'none';
                      sfd1.style.display = 'none';
                      sfd2.style.display = 'none';
                      sfd3.style.display = 'none';
                      sfd4.style.display = 'block';
                   }
              });
        }
    };
});
