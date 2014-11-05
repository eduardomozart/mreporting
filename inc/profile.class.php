<?php
/*
 * @version $Id: HEADER 15930 2011-10-30 15:47:55Z tsmr $
 -------------------------------------------------------------------------
 Mreporting plugin for GLPI
 Copyright (C) 2003-2011 by the mreporting Development Team.

 https://forge.indepnet.net/projects/mreporting
 -------------------------------------------------------------------------

 LICENSE

 This file is part of mreporting.

 mreporting is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 mreporting is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with mreporting. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

class PluginMreportingProfile extends CommonDBTM {

   static function getTypeName($nb = 0) {
      global $LANG;

      return $LANG['plugin_mreporting']["name"];
   }

   static function canCreate() {
      return Session::haveRight('profile', 'w');
   }

   static function canView() {
      return Session::haveRight('profile', 'r');
   }

    //if profile deleted
    static function purgeProfiles(Profile $prof) {
        $plugprof = new self();
        $plugprof->deleteByCriteria(array('profiles_id' => $prof->getField("id")));
    }



    //if reports  deleted
    static function purgeProfilesByReports(PluginMreportingProfile $config) {
        $plugprof = new self();
        $plugprof->deleteByCriteria(array('reports' => $config->getField("id")));
    }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      global $LANG;

      if ($item->getType()=='Profile' && $item->getField('interface')!='helpdesk') {
            return $LANG['plugin_mreporting']["name"];
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum=1, $withtemplate=0) {
      global $CFG_GLPI;

      if ($item->getType()=='Profile') {
         $ID = $item->getField('id');
         $prof = new self();

         if (!$prof->getFromDBByProfile($item->getField('id'))) {
            $prof->createAccess($item->getField('id'));
         }
         $prof->showForm($item->getField('id'), array('target' =>
                     $CFG_GLPI["root_doc"]."/plugins/mreporting/front/profile.form.php"));
      }
      return true;
   }

   function getFromDBByProfile($profiles_id) {
      global $DB;

      $query = "SELECT * FROM `".$this->getTable()."`
               WHERE `profiles_id` = '" . $profiles_id . "' ";
      if ($result = $DB->query($query)) {
         if ($DB->numrows($result) != 1) {
            return false;
         }
         $this->fields = $DB->fetch_assoc($result);
         if (is_array($this->fields) && count($this->fields)) {
            return true;
         } else {
            return false;
         }
      }
      return false;
   }


    static function createFirstAccess($ID) {

        $myProf = new self();
        if (!$myProf->getFromDBByProfile($ID)) {
            $myProf->add(array(
                'profiles_id' => $ID,
                'reports' => 'r',
                'config' => 'w'
            ));
        }

    }


    /**
     * @param $right array
     */
    static function addRightToProfiles($right){

        global $DB;

        $profiles = "SELECT `id` FROM `glpi_profiles` where `interface` = 'central'";
        $reports = "SELECT `id` FROM `glpi_plugin_mreporting_configs`";

        //TODO : We need to reload cache before else glpi don't show migration table
        $myreport = new PluginMreportingProfile();
        $table_fields = $DB->list_fields($myreport->getTable(),false);


        foreach ($DB->request($profiles) as $prof) {

            foreach($DB->request($reports) as $report){

                //If profiles have right
                if(in_array($prof['id'],$right)){
                    $tmp = array(
                        'profiles_id' => $prof['id'],
                        'reports'   => $report['id'],
                        'right' => 'r');
                    $myreport->add($tmp);
                }else{
                    $tmp = array(
                        'profiles_id' => $prof['id'],
                        'reports'   => $report['id']
                    );
                    $myreport->add($tmp);
                }
            }
        }


    }


    static function getRight(){

        global $DB;
        $query = "select `profiles_id` from `glpi_plugin_mreporting_profiles` where reports = 'r' ";

        $right = array();
        foreach ($DB->request($query) as $profile) {
            $right[] = $profile['profiles_id'];
        }

        return $right;
    }

    /**
     * Function to add right on report to a profile
     * @param $idProfile
     */
    function addRightToProfile($idProfile){

        //get all reports
        $config = new PluginMreportingConfig();
        $res = $config->find();

        foreach( $res as $report) {
            //add right for any reports for profile
            $reportProfile1 = new PluginMreportingProfile();
            $reportProfile1->add(array(
                'profiles_id' => $idProfile,
                'reports'   => $report['id'],
                'right' => 'r'
            ));

        }

    }


    /**
     * Function to add right of a new report
     * @param $report_id
     */
    function addRightToReports($report_id){

        global $DB;
        $profiles = "SELECT `id` FROM `glpi_profiles` where `interface` = 'central'";

        foreach ($DB->request($profiles) as $prof) {

                $reportProfile1 = new PluginMreportingProfile();
                $reportProfile1->add(array(
                    'profiles_id' => $prof['id'],
                    'reports'   => $report_id,
                    'right' => 'r'
                ));

        }
    }

   function createAccess($ID) {

      $this->add(array(
      'profiles_id' => $ID));
   }

   static function changeProfile() {
      $prof = new self();
      if ($prof->getFromDBByProfile($_SESSION['glpiactiveprofile']['id'])) {
         $_SESSION["glpi_plugin_mreporting_profile"] = $prof->fields;
      }
      else unset($_SESSION["glpi_plugin_mreporting_profile"]);
   }


    /**
     * Form to manage report right on profile
     * @param $ID (id of profile)
     * @param array $options
     * @return bool
     */
    function showForm ($ID, $options=array()) {
        global $LANG;

        if (!Session::haveRight("profile","r")) return false;

        global $LANG;


        $config = new PluginMreportingConfig();
        $res = $config->find();

        $this->showFormHeader($options);

        echo "<table class='tab_cadre_fixe'>\n";
        echo "<tr><th colspan='3'>".$LANG['plugin_mreporting']["right"]["manage"]."</th></tr>\n";

        foreach( $res as $report) {

            $profile = $this->findByProfileAndReport($ID,$report['id']);
            echo "<tr class='tab_bg_1'><td>" . $report['name'] . "&nbsp: </td><td>";
            Profile::dropdownNoneReadWrite($report['id'],$profile->fields['right'],1,1,0);
            echo "</td></tr>\n";
        }


        echo "<tr class='tab_bg_4'><td colspan='2'> ";
        echo "</tr>";

        echo "</table>\n";
        echo "<input type='hidden' name='profile_id' value=".$ID.">";

        echo "<input type='submit' name='giveReadAccessForAllReport' value=\"".$LANG['plugin_mreporting']["right"]["giveAllRight"]."\" class='submit'><br><br>";
        echo "<input type='submit' name='giveNoneAccessForAllReport' value=\"".$LANG['plugin_mreporting']["right"]["giveNoRight"]."\" class='submit'><br><br><br>";


        $options['candel'] = false;
        $this->showFormButtons($options);
    }


    /**
     * Form to manage right on reports
     * @param $items
     */
    function showFormForManageProfile($items,$options=array()){
        global $DB, $LANG;

        if (!Session::haveRight("config","r")) return false;

        $this->showFormHeader($options);

        echo "<table class='tab_cadre_fixe'>\n";
        echo "<tr><th colspan='3'>".$LANG['plugin_mreporting']["right"]["manage"]."</th></tr>\n";

        $query = "SELECT `id`, `name`
                FROM `glpi_profiles` where `interface` = 'central'
                ORDER BY `name`";

        foreach ($DB->request($query) as $profile) {

            $reportProfiles=new Self();
            $reportProfiles = $reportProfiles->findByProfileAndReport($profile['id'],$items->fields['id']);
            echo "<tr class='tab_bg_1'><td>" . $profile['name'] . "&nbsp: </td><td>";
            Profile::dropdownNoneReadWrite($profile['id'],$reportProfiles->fields['right'],1,1,0);
            echo "</td></tr>\n";

        }


        echo "<tr class='tab_bg_4'><td colspan='2'> ";

        echo "</tr>";

        echo "</table>\n";

        echo "<input type='hidden' name='report_id' value=".$items->fields['id'].">";



        echo "<input type='submit' name='giveReadAccessForAllProfile' value=\"".$LANG['plugin_mreporting']["right"]["giveAllRightOnProfile"]."\" class='submit'><br><br>";
        echo "<input type='submit' name='giveNoneAccessForAllProfile' value=\"".$LANG['plugin_mreporting']["right"]["giveNoRightOnProfile"]."\" class='submit'><br><br><br>";


        $options['candel'] = false;
        $this->showFormButtons($options);
    }


    function findByProfileAndReport($profil_id, $report_id){
        $reportProfile = new Self();
        $reportProfile->getFromDBByQuery(" where `reports` = ".$report_id." AND `profiles_id` = ".$profil_id);
        return $reportProfile;
    }

    function findReportByProfiles($profil_id){
        $reportProfile = new Self();
        $reportProfile->getFromDBByQuery(" where `profiles_id` = ".$profil_id);
        return $reportProfile;
    }


    static function canViewReports($profil_id, $report_id){
        $reportProfile = new Self();

        $reportProfile->getFromDBByQuery(" where `reports` = ".$report_id." AND `profiles_id` = ".$profil_id);
        if($reportProfile->fields['right'] == 'r'){
            return true;
        }
        return false;
    }

    // Hook done on add item case
    static function addProfiles(Profile $item) {

        if($item->getType()=='Profile' && $item->getField('interface')!='helpdesk'){
            Session::addMessageAfterRedirect("Add Profile Hook, ID=".$item->getID(), true);
            $profile = new PluginMreportingProfile();
            $profile->addRightToProfile($item->getID());
        }

        return true;
    }



}

