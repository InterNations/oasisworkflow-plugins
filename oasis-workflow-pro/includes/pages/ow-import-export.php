<?php

/*
 * Workflow Import/Export Tool
 *
 * @copyright   Copyright (c) 2018, Nugget Solutions, Inc
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       5.3
 *
 */
?>

<div class="wrap ow-tools">
  
    <span class="required-color"><?php echo __( "Note: Make sure your environment is identical in terms of plugins, roles, custom roles and users otherwise, the import might error out." ); ?></span>
    
   <form enctype="multipart/form-data" method="post">
      <div id="owf-export">
         <div id="settingstuff">
            <fieldset class="owf_fieldset">
               <legend><?php echo __( "Export", "oasisworkflow" ); ?></legend>               
               <span class="description"><?php echo __( "Use the download button to export to a .json file which you can then import to another WordPress installation", "oasisworkflow" ); ?></span>
               <br/>
               <br/>               
               <label style="display: block;"> 
                  <input type="checkbox" class="owf-checkbox" name="add_for_export[]" value= "workflows"/>
                     <?php echo __( "Workflows (includes Teams and Groups, if any)", "oasisworkflow" ); ?>
               </label>               
               <label style="display: block;"> 
                  <input type="checkbox" class="owf-checkbox" name="add_for_export[]" value= "settings"/>
                     <?php echo __( "Settings (includes all the settings)", "oasisworkflow" ); ?>
               </label> 
               <br/>
               <input type="submit" name="ow-export-workflow" id="ow-export-workflow"
                  class="button action" value="<?php echo __("Download Export File", "oasisworkflow"); ?>">
               <?php wp_nonce_field( 'owf_export_workflows', 'owf_export_workflows' ); ?>
            </fieldset>  
         </div>
      </div>
       
      <!-- Import Workflow -->
      <div id="workflow-import">
         <div id="settingstuff">
            <fieldset class="owf_fieldset">
               <legend><?php echo __( "Import", "oasisworkflow" ); ?></legend>
               <span class="description"><?php echo __( "Select the Oasis Workflow JSON file you would like to import.", "oasisworkflow" ); ?></span>
               <br/>
               <p>
                  <label for="upload"><?php _e( 'Choose a file from your computer:', 'oasisworkflow' ); ?></label>
               </p>
               <p>
                  <input type="file" id="upload" name="import-workflow-filename" size="50">
               </p>
               <br/>
               <input type="submit" name="ow-import-workflow" id="ow-import-workflow"
                 class="button action" value="<?php echo __("Import", "oasisworkflow"); ?>">
              <?php wp_nonce_field( 'owf_import_workflows', 'owf_import_workflows' ); ?>
            </fieldset>  
         </div>
      </div>
       
   </form> 
</div>
        