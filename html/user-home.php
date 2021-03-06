<?php
  $_DISPLAYNEW = false;
  include "nav/default_top.php";
//
  /*
   * user-home.php
   *
   * INPUTS: 
   *
   * OUTPUTS:
   *
   * FUNCTION:
   *
   * GOES:
   *
   * CREATED: 08 Oct 2003
   *
   * AUTHOR: GWA
   */
?>
<?php

global $a;
if ($a->getAuth()) {

  global $_JOBSCHEDULETABLENAME;
  global $_JOBSTABLENAME;
  global $_JOBRUNNING;
  global $_JOBPENDING;
  global $_JOBFINISHED;
  
  $user = getSessionVariable("username");
  $username = getSessionVariable("firstname");
  $DB = DB::Connect($_DSN);
  if (DB::isError($DB)) {
    die($DB->GetMessage());
  }

  // 31 Oct 2003 : GWA : This is simple, but we added some error checking and
  //               seems to work.

  if (($_GET['delete'] == 1) &&
      ($_GET['id'] != "")) {
    if (deleteJobSchedule($_GET['id'])) { ?>
      <p class=error>
        There was a problem deleting this job.  It could be because you don't
        own it, or it doesn't exist.
      </p>
    <?php } else { ?>
      <p style="color:red;">
        Deleted job from run queue.
      </p>
    <?php } 
  }
  
  if ($_GET['doHide'] || $_GET['doShow']) {
    $allIDS = explode(",", $_GET['list']);
    $i = 0;
    foreach($allIDS as $unused => $jobID) {
      if ($i != 0) {
        $conditionString .= " OR ";
      }
      $conditionString .= "id=" . $jobID;
      $i++;
    }
    if ($_GET['doHide']) {
     $setState = "set hidden=1";
    } else {
      $setState = "set hidden=0";
    }
    $hideQuery = "update " . $_JOBSCHEDULETABLENAME . " " .
                 $setState . " where " . $conditionString;
    doDBQuery($hideQuery);
  }

  $queryCommonRoot = "select " . $_JOBSCHEDULETABLENAME .
                     ".id, jobid, name, jobschedule.created, owner, datapath," .
                     " start, end, hidden, jobtempdir from ". 
                     $_JOBSCHEDULETABLENAME .
                     " as jobschedule, " . $_JOBSTABLENAME . 
                     " as jobs where jobschedule.jobid = jobs.id" .
                     " and owner=\"" . $user . "\"";

  $runningQuery = $queryCommonRoot . " and state=" . $_JOBRUNNING;
  $finishedQuery = $queryCommonRoot . " and state=" . $_JOBFINISHED .
                   " order by end desc";
  $pendingQuery = $queryCommonRoot . " and state=" . $_JOBPENDING .
                  " order by start";
  ?>

  <br><p> 
    Hello <?php echo $username; ?>!  Welcome to motelab.  
  <p>
    Start <a href="jobs-create.php">here</a>.
  <p>
    Below you should see information about jobs that you have in the
    scheduling queue, if any.
  <br>
  <hr>
  
  <?php // RUNNING JOBS
  //
  // 08 Oct 2003 : GWA : We assume that there is at most one running job.
  // 30 Aug 2004 : swies : wrong!
  
  $runningResult = $DB->query($runningQuery);
  if (DB::isError($runningResult)) {
    die($runningResult->GetMessage());
  }
  
  if ($runningResult->numRows() > 0) { ?>
    <p>
      You have the following jobs currently running on moteLab.  Right now you cannot
      view job data until the job ends.  Sorry.
    </p>
    <table border=1px 
           align=center
           cellpadding=5px
           style="border-collapse:collapse; 
                  empty-cells:show;
                  width:90%;">
    <?php while ($runningRef = $runningResult->fetchRow(DB_FETCHMODE_ASSOC)) { ?>
      <tr class=scheduleRunning>
        <td>
          <?php echo $runningRef['id']; ?>
        </td>
        <td> 
          <?php echo $runningRef['name']; ?>
        </td>
        <td>
          Started <?php echo $runningRef['start']; ?>
        </td>
        <td>
          Ends <?php echo $runningRef['end']; ?>
        </td>
      </tr>
    <?php } ?>
    </table>
    <br>
  <?php } else { ?>
    <p> You have no jobs currently running.
  <?php } ?>

  <hr>
  
  <?php // PENDING JOBS
  //
  // 08 Oct 2003 : GWA : See note above about collapsing things.
  //

  $pendingResult = $DB->query($pendingQuery);
  if (DB::isError($pendingResult)) {
    die($pendingResult->GetMessage());
  }

  if ($pendingResult->numRows() > 0) { ?>
    <p>
      You have jobs that are waiting to be run.
    </p>
    <table border=1px 
           align=center
           cellpadding=5px
           style="border-collapse:collapse; 
                  empty-cells:show;
                  width:90%;">
    <?php while ($pendingRef = 
                 $pendingResult->fetchRow(DB_FETCHMODE_ASSOC)) { ?>
      <tr class=schedulePending>
        <td>
          <?php echo $pendingRef['id']; ?>
        </td>
        <td> 
          <?php echo $pendingRef['name']; ?>
        </td>
        <td>
          Starts <?php echo $pendingRef['start']; ?>
        </td>
        <td>
          Finishes <?php echo $pendingRef['end']; ?>
        </td>
        <td>
          <a href="user-home.php?delete=1&id=<?php echo $pendingRef['id'];?>">
          Delete
          </a>
        </td>
      </tr>
    <?php } ?>
    
    </table>
    <br>
  <?php } else { ?>
    <p>
      You have no pending jobs.
    </p>
  <?php } ?>

  <hr>

  <?php // FINISHED JOBS
  //
  // 08 Oct 2003 : GWA : Could be many finished jobs.  Eventually we want to
  //               collapse this list but let's not be too slick about it
  //               yet.
  
  $finishedResult = $DB->query($finishedQuery);
  if (DB::isError($finishedResult)) {
    die($finishedResult->GetMessage());
  }
  
  if ($finishedResult->numRows() > 0) { ?>
    <p>
      You have jobs that have finished running and have data available.
    </p>
    <p>
    <input id="selectAll" type=button onClick="selectAll(true);" 
           value="Select All">
    <input id="unselectAll" type=button onClick="selectAll(false);"
           value="Unselect All" style="display:none;">
    <input type=button onClick="doHide();" value="Hide Selected">
    <input id="showHidden" 
           type=button onClick="showHidden();" value="Show Hidden">
    <input id="hideHidden"
           type=button onClick="hideHidden();" value="Hide Hidden"
           style="display:none;">
    <input id="showSelected"
           type=button onClick="doShow();" value="Show Selected"
           style="display:none;">
    </p>
    <table border=1px 
           align=center
           cellpadding=5px
           style="border-collapse:collapse; 
                  empty-cells:show;
                  width:90%;">
    <?php while ($finishedRef =
                 $finishedResult->fetchRow(DB_FETCHMODE_ASSOC)) { ?>
      <tr 
        <?php if ($finishedRef['hidden']) { ?>
          class='scheduleFinishedHidden' style="display:none;">
        <?php } else { ?>
          class='scheduleFinished'>
        <?php } ?>
        <td>
          <input id="<?php echo $finishedRef['id'];?>"
                 class='finishedJobs'
                 type=checkbox>
        </td>
        <td>
          <?php echo $finishedRef['id']; ?>
        </td>
        <td> 
          <?php echo $finishedRef['name']; ?>
        </td>
        <td>
          Started <?php echo $finishedRef['start']; ?>
        </td>
        <td>
          Finished <?php echo $finishedRef['end']; ?>
        </td>
        <td>
          <?php if (file_exists($finishedRef['datapath'])) {
            $realDataRoot = str_replace($_JOBDATAROOT, "/web/users/",
                            $finishedRef['datapath']);
            ?>
            <a href="<?php echo $realDataRoot;?>">
              Download Data
            </a>
          <?php } else { ?>
            No data available.
          <? } ?>
        </td>
        <?php if ($finishedRef['hidden']) { ?>
          </div>
        <?php } ?>
      </tr>
    <?php } ?>
    
    </table>
    <br>
  <?php } else { ?>
    <p>
      You have no completed jobs.
    </p>
  <?php } ?>
  <form name="hidingForm" method=get action="<?php echo
        $_SERVER['PHP_SELF'];?>">
  <input type=hidden name="list" value="">
  <input type=hidden name="doHide" value=0>
  <input type=hidden name="doShow" value=0>
  </form>

<script language="JavaScript">
<!--
  var workingList = new Array();

  function getElementsByClass(cls) {
    var a, b, c, i, j, o;
    b = document.getElementsByTagName("body").item(0);
    a = b.getElementsByTagName("*");
    o = new Array();
    j = 0;
    for (i = 0; i < a.length; i++) {
      if (a[i].className == cls) {
        o[j] = a[i];
        j++;
      }
    }
    return o;
  }

  function doHide() {
    hidingElements = getElementsByClass('finishedJobs');
    var j = 0;
    var passingOptions = "";
    for (i = 0; i < hidingElements.length; i++) {
      if (hidingElements[i].checked) {
        if (j != 0) {
          passingOptions += ",";
        }
        passingOptions += hidingElements[i].id;
        j++;
      }
    }
    document.hidingForm.list.value = passingOptions;
    document.hidingForm.doHide.value=1;
    document.hidingForm.submit();
    return;
  }
  
  function doShow() {
    hidingElements = getElementsByClass('finishedJobs');
    var j = 0;
    var passingOptions = "";
    for (i = 0; i < hidingElements.length; i++) {
      if (hidingElements[i].checked) {
        if (j != 0) {
          passingOptions += ",";
        }
        passingOptions += hidingElements[i].id;
        j++;
      }
    }
    document.hidingForm.list.value = passingOptions;
    document.hidingForm.doShow.value=1;
    document.hidingForm.submit();
    return;
  }
  
  function selectAll(direction) {
    hidingElements = getElementsByClass('finishedJobs');
    for (i = 0; i < hidingElements.length; i++) {
      hidingElements[i].checked = direction;
    }
    if (direction) {
      document.getElementById('unselectAll').style.display='';
      document.getElementById('selectAll').style.display='none';
    } else {
      document.getElementById('unselectAll').style.display='none';
      document.getElementById('selectAll').style.display='';
    }
  }

  function showHidden() {
    anchorArray = document.getElementsByTagName("tr");
    for (i=0; i<anchorArray.length; i++) {
      if (anchorArray.item(i).className == 'scheduleFinishedHidden') {
        anchorArray.item(i).style.display='';
      }
    }
    document.getElementById('showHidden').style.display="none";
    document.getElementById('hideHidden').style.display="";
    document.getElementById('showSelected').style.display="";
  }

  function hideHidden() {
    anchorArray = document.getElementsByTagName("tr");
    for (i=0; i<anchorArray.length; i++) {
      if (anchorArray.item(i).className == 'scheduleFinishedHidden') {
        anchorArray.item(i).style.display='none';
      }
    }
    document.getElementById('showHidden').style.display="";
    document.getElementById('hideHidden').style.display="none";
    document.getElementById('showSelected').style.display="none";
  }
//-->
</script>

<?php } else { ?>
  <p> You cannot access your home page until you log in.
<?php } ?>
<?php
  include "nav/default_bot.php";
?>
