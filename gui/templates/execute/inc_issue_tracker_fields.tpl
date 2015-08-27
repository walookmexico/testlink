{if $gui->issueTrackerMetaData.hasExtraFields == true}
  {* user assigned to selection *}
  <label for="issueTracker_assigned_to">{$labels.issueTracker_assigned_to}</label>
  <select name="issueTracker_assigned_to" id="issueTracker_assigned_to">
    {html_options options=$gui->issueTracker_users}
  </select>
  {* estimate selection *}
  <label for="issueTracker_estimate">{$labels.issueTracker_estimate}</label>
  <input type="text" name="issueTracker_estimate" id="issueTracker_estimate" />
  {* milestone selection *}
  <label for="issueTracker_milestone">{$labels.issueTracker_milestone}</label>
  <select name="issueTracker_milestone" id="issueTracker_milestone">
    {html_options options=$gui->issueTracker_milestones}
  </select>
  {* user reported by selection *}
  <label for="issueTracker_reported_by">{$labels.issueTracker_reported_by}</label>
  <select name="issueTracker_reported_by" id="issueTracker_reported_by">
    {html_options options=$gui->issueTracker_users}
  </select>
  {* attach file to ticket *}
  <label for="uploadedFile">{$labels.local_file}</label>
  <input type="hidden" name="MAX_FILE_SIZE" value="{$gui->import_limit}" /> {* restrict file size *}
  <input type="file" name="uploadedFile" size="{#UPLOAD_FILENAME_SIZE#}" />
{/if}