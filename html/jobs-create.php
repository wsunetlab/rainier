<?php
  global $_DISPLAYNEW;
  $_DISPLAYNEW = false;
  global $_DISPLAYMOTD;
  $_DISPLAYMOTD = false;

  include "nav/default_top.php";

  /*
   * job-create.php
   * 
   * INPUTS: 
   *  1) GET['jobid'].  Optional.
   *  2) POST[<various>].  Optional. 
   *
   * FUNCTION:
   *  If GET['jobid'] is set, allows the user to modify the job with the
   *  given jobid, if it exists.  If GET['jobid'] is not set will allow the
   *  user to create a new job.
   * 
   *  If the various POST[] options are set, it reloads that information.
   *  This is primarily to support the reloads demanded by the upload-file
   *  action.
   *
   * GOES: 
   *  1) self
   *
   * CREATED: 24 Jul 2003 
   *
   * AUTHOR: GWA
   */
?>

<?php
global $a;
if ($a->getAuth()) {


  //
  // PRELIMINARY STUFF
  //

  // 04 Aug 2003 : GWA : Session variables that we want to access.

  $user = getSessionVariable("username");
  $userid = getSessionVariable("id");
  $execDir = getSessionVariable("exec_dir");
  $upload = $_POST['Upload'];

  // 07 Oct 2003 : GWA : Try and prevent reloads from doing damage.
  
  if (doReloadProtect()) {
    $upload = false;
  }

  //
  // FORM ELEMENTS
  //

  // 04 Aug 2003 : GWA : Description panel defaults.  

  $jobName = "";
  $jobDescription = "";

  // 04 Aug 2003 : GWA : Files panel defaults.  

  $jobFiles = array();
  $notJobFiles = array();
  $allFiles = array();
  $jobClasses = array();
  $notJobClass = array();
  $allClasses = array();

  // 04 Aug 2003 : GWA : Motes panel defaults.  Because the javascript is
  //               responsible for keeping these updated, the code is
  //               somewhat ugly to do this correctly when loading POST or
  //               GET data.
  
  $selectionType = "";
  $moteToFile = array();

  // 
  // GRABBING ACTIVE MOTES
  //
  // 22 Oct 2003 : GWA : Something new.  We want to allow motes to be picked
  //               up from the database, not assigned statically.  Let's do
  //               that right here.

  $activeMotes = array();
  $moteQuery = "select moteid from " . $_MOTESTABLENAME . 
               " where active=1";
  $moteResult = doDBQuery($moteQuery);
  
  // 22 Oct 2003 : GWA : This is pretty painless.

  while ($moteRow = $moteResult->fetchRow(DB_FETCHMODE_ASSOC)) {
    array_push($activeMotes, $moteRow['moteid']);
  }
  
  // 22 Oct 2003 : GWA : We also kind of want the maximum mote ID for sizing
  //               a few arrays and such.

  $moteMaxQuery = "select moteid from " . $_MOTESTABLENAME .
                  " order by moteid desc limit 1";
  $moteMaxResult = doDBQuery($moteMaxQuery);
  $moteMaxRow = $moteMaxResult->fetchrow(DB_FETCHMODE_ASSOC);
  $MAXMOTENUM = $moteMaxRow['moteid'];

  //
  // DEALING WITH FILE UPLOADS
  //
  // 05 Aug 2003 : GWA : This used to be a seperate page, but that didn't
  //               seem to make much sense, so it was moved here, inline with
  //               the rest of the job setup process.
  //
  //               Note that right now the ONLY time that we do uploads is
  //               when we are also reloading with POST data, but I didn't
  //               want to build too much of that into the logic below.
  //
  // 18 Aug 2003 : GWA : Added the .class to the new file name to make it
  //               easier to extract the actual classname later.
  //
  // 19 Aug 2003 : GWA : Ack... javaw doesn't deal well with periods in the
  //               class file name, so we're going to omit the user name,
  //               which currently is the email address which contains
  //               periods.  This is OK because the directory is the user
  //               name anyways, so it's still there.

  if ($upload) {

    $uploadName = trim($_POST['UploadName']);
    $uploadName = preg_replace("/\./", "_", $uploadName);
    $newFileName = $execDir . 
                   $uploadName . "_" . 
                   date("YmdHis");
    
    // 31 Aug 2003 : GWA : Add an appropriate extension.

    if ($_REQUEST['UploadType'] == "class") {
      $newFileName .= ".class";
    } else {
      $newFileName .= ".exe";
    }
   
    // 16 Jul 2006 : GWA : Adding some testing for class files here.
   
    $uploadSuccess = true;

    if ($_REQUEST['UploadType'] == "class") {

      // 16 Jul 2006 : GWA : Run the test on our uploaded class file.

      $testClassFileCommand = $_TESTCLASSFILE . " " .  $_FILES['moteprogram']['tmp_name'];
      $testClassFileOutput = `$testClassFileCommand`;

      // 16 Jul 2006 : GWA : If it fails, try to pretty-up the output a bit
      //               and report the failure below.

      if ($testClassFileOutput != "") {
        $testClassFileOutput = preg_replace("/\n/", "<br>", $testClassFileOutput);
        $testClassFileOutput = preg_replace("/\t/", "&nbsp;&nbsp;&nbsp;", $testClassFileOutput);
        $uploadSuccess = false;
      }
    } else {
      // 16 Jul 2006 : GWA : Run the test on our uploaded class file.

      $testExecutableCommand = $_TESTEXECUTABLE . " " .  $_FILES['moteprogram']['tmp_name'];
      $testExecutableOutput = `$testExecutableCommand`;

      // 16 Jul 2006 : GWA : If it fails, try to pretty-up the output a bit
      //               and report the failure below.

      if ($testExecutableOutput != "") {
        $testExecutableOutput = preg_replace("/\n/", "<br>", $testExecutableOutput);
        $testExecutableOutput = preg_replace("/\t/", "&nbsp;&nbsp;&nbsp;", $testExecutableOutput);
        $uploadSuccess = false;
      }
    }
      
    // 05 Oct 2003 : GWA : Don't actually put the file into the database
    //               unless it was successfully uploaded.
    
    if ($uploadSuccess == true) {
      $uploadSuccess = move_uploaded_file($_FILES['moteprogram']['tmp_name'],
                                          $newFileName);
      
	chmod($newFileName, 0664);
     shell_exec("scp -q -v ". $newFileName . " sensors@ecs322-4:". $newFileName);

      // 03 Nov 2003 : GWA : Moving to unique userid instead of username in
      //               info tables.

      $insert_string = "insert into " . $_FILESTABLENAME .
                       " set path=\"" . $newFileName . "\"" . 
                       ", user=\"" . $user . "\"" .
                       ", userid=" . $userid . 
                       ", name=\"" . $uploadName . "\"" .
                       ", type=\"" . $_REQUEST['UploadType'] . "\"" .
                       ", description=\"" . $_REQUEST['UploadDesc'] . "\"";
      doDBQuery($insert_string);
    } else {

      // 05 Oct 2003 : GWA : Handled below
    }
  }

  //
  // FILLING FORM ELEMENTS
  //
  // 04 Aug 2003 : GWA : We do this either a) from POST data on a
  //               self-reload, b) from GET['jobid'] page when editing a page
  //               or c) from nowhere, set to defaults when creating a job.
  //
  
  if ($_POST['Self']) {
  
    // 04 Aug 2003 : GWA : When we have POST data we know that the javascript
    //               put everything in the right place.  We just need to get
    //               it out, which is a bit squirrelly but not too bad.
    
    // 04 Aug 2003 : GWA : Name and description, in particular, are easy.

    $jobName = $_POST['Name'];
    $jobDescription = $_POST['Description'];
    $jobCurrentPanel = $_POST['Where'];
    $jobDistType = $_POST['DistType'];
    if ($_HAVEPOWERMANAGE) {
      $jobPowerManage = $_POST['doPowerManage'];
    }
    if (getSessionVariable("type") == "admin") {
      $jobIsCronJob = $_POST['IsCronJob'];
      $jobCronFreq = $_POST['CronFreq'];
      $jobCronTime = $_POST['CronTime'];
      $jobDuringRun = $_POST['DuringRun'];
      $jobPostProcess = $_POST['PostProcess'];
    }

    // 04 Aug 2003 : GWA : Now get data about what files were in the project.
    //               This is passed, really only for us, because perhaps
    //               there are files that aren't yet associated with motes,
    //               and so we can't rely on the moteID->fileID info to give
    //               us all of the active files.

    $splitInfo = explode(",", $_POST['Files']);
    $fileInfo = array();

    foreach($splitInfo as $current) {
      array_push($fileInfo, $current);
    }

    // 04 Aug 2003 : GWA : Get the same information for the class files so 
    //               that we can process everything together below.

    $splitClass = explode(",", $_POST['Classes']);
    $classInfo = array();

    foreach($splitClass as $current) {
      array_push($classInfo, $current);
    }

    // 04 Aug 2003 : GWA : We'll just grab all the files that the user has
    //               access to and sort things out later.

    $fileQuery = "select id, name, UNIX_TIMESTAMP(uploaded) as uploaded," .
                 " type, description" .
                 " from " . $_FILESTABLENAME . " as files" .
                 " where userid=" . $userid;
    $fileResult = doDBQuery($fileQuery);

    // 04 Aug 2003 : GWA : Poring through all the files that the user has
    //               access to and comparing them against the fileID's
    //               present in the project lets us put everything in the
    //               right place.
    
    while($row = $fileResult->fetchRow(DB_FETCHMODE_ASSOC)) {
      $currentFileArray = array($row['name'],
                                date("j M Y \a\\t G:i:s", $row['uploaded']),
                                $row['description']);
      if ($row['type'] == "program") {
        $allFiles[$row['id']] = $currentFileArray;
        if (in_array($row['id'], $fileInfo)) {
          $jobFiles[$row['id']] = $currentFileArray;
        } else {
          $notJobFiles[$row['id']] = $currentFileArray;
        }
      } else if ($row['type'] == "class") {
        $allClasses[$row['id']] = $currentFileArray;
        if (in_array($row['id'], $classInfo)) {
          $jobClasses[$row['id']] = $currentFileArray;
        } else {
          $notJobClasses[$row['id']] = $currentFileArray;
        }
      } 
    }
    
    // 04 Sep 2003 : GWA : Just prepare the mote->file mapping stuff.
    //               Finished later.

    $splitMoteFile = explode("|", $_POST['Info']);

  } else if ($jobID = $_GET['jobid']) {

    // 04 Aug 2003 : GWA : When we have GET data all we get is the jobID.  We
    //               need to reconstruct everything using information from
    //               the database.

    // 28 Jul 2003 : GWA : We check the user to make sure that they have
    //               access to this job, since we can't necessarily trust the
    //               passed job ID.
    //        
    //               TODO : Add group support.

    $jobQuery = "select name, description, disttype, currentpanel" . 
                ", moteprogram, powermanage, cronjob, crontime, cronfreq" .
                ", duringrun, postprocess from " .
                $_JOBSTABLENAME . 
                " where userid=" . $userid . 
                " and id=" . $jobID;
    $jobResult = doDBQuery($jobQuery);
    
    if ($jobResult->numRows() == 0) {
      die("no access to this job for user $user");
    }

    // 28 Jul 2003 : GWA : Set the form defaults from database info.

    $jobRow = $jobResult->fetchRow(DB_FETCHMODE_ASSOC);
    $jobName = $jobRow['name'];
    $jobDescription = $jobRow['description'];
    $jobCurrentPanel = $jobRow['currentpanel'];
    $jobDistType = $jobRow['disttype'];
    $splitMoteFile = explode("|", $jobRow['moteprogram']);
    $jobPowerManage = $jobRow['powermanage'];
    $jobIsCronJob = $jobRow['cronjob'];
    $jobCronFreq = $jobRow['cronfreq'];
    $jobCronTime = $jobRow['crontime'];
    $jobDuringRun = $jobRow['duringrun'];
    $jobPostProcess = $jobRow['postprocess'];

    // 28 Jul 2003 : GWA : We check for user validity here as well because we
    //               can't trust the passed jobID.
    //        
    // 29 Oct 2003 : GWA : This is broken.  We need to pull all jobs and
    //               compare against that.       
    
    $inJobQuery = "select distinct files.id, files.name," .
                  " UNIX_TIMESTAMP(files.uploaded) as uploaded," .
                  " files.type, files.description, jobfiles.jobid from " .
                  $_FILESTABLENAME . " as files, " .
                  $_JOBFILESTABLENAME . " as jobfiles" .
                  " where jobfiles.fileid=files.id" .
                  " and jobfiles.jobid=" . $jobID;
    
    $allQuery = "select distinct files.id, files.name," .
                " UNIX_TIMESTAMP(files.uploaded) as uploaded," .
                " files.type, files.description from " .
                $_FILESTABLENAME . " as files" .
                " where files.userid=" . $userid; 
                    
    $inJobResult = doDBQuery($inJobQuery);
    $allResult = doDBQuery($allQuery);

    $seenFiles = array();

    while ($row = $inJobResult->fetchRow(DB_FETCHMODE_ASSOC)) {
      array_push($seenFiles, $row['id']);
      $currentFileArray = array($row['name'],
                                date("j M Y \a\\t G:i:s", $row['uploaded']),
                                $row['description']);
      if ($row['type'] == "program") {
        $jobFiles[$row['id']] = $currentFileArray;
        $allFiles[$row['id']] = $currentFileArray;
      } else if ($row['type'] == "class") {
        $jobClasses[$row['id']] = $currentFileArray;
        $allClasses[$row['id']] = $currentFileArray;
      } 
    }

    // 29 Oct 2003 : GWA : Step though every file the user has access to.  If
    //               it's in the array above, skip it.

    while ($row = $allResult->fetchrow(DB_FETCHMODE_ASSOC)) {
      if (in_array($row['id'], $seenFiles)) {
        continue;
      }
      $currentFileArray = array($row['name'],
                                date("j M Y \a\\t G:i:s", $row['uploaded']),
                                $row['description']);
      if ($row['type'] == "program") {
        $allFiles[$row['id']] = $currentFileArray;
        $notJobFiles[$row['id']] = $currentFileArray;
      } else if ($row['type'] == "class") {
        $allClasses[$row['id']] = $currentFileArray;
        $notJobClasses[$row['id']] = $currentFileArray;
      } 
    }

    $totalFiles = count($jobFiles) + count($notJobFiles);
    $totalClasses = count($jobClasses) + count($notJobClasses);
   
  } else {

    // 04 Aug 2003 : GWA : If we don't have POST or GET data we're in good
    //               shape, since the default values should be fine in all
    //               cases.  But we still need to grab some file information
    //               and such.

    // 28 Jul 2003 : GWA : Retrieve the file list.

    $notFileQuery = "select files.id, files.name," .
                    " DATE_FORMAT(files.uploaded, '%d %b %Y at %H:%i:%s')," .
                    " files.description from " .
                    $_FILESTABLENAME . " as files" .
                    " where files.userid=" . $userid . 
                    " and files.type=\"program\"";

    $notClassQuery = "select files.id, files.name," .
                    " DATE_FORMAT(files.uploaded, '%d %b %Y at %H:%i:%s')," .
                    " files.description from " .
                     $_FILESTABLENAME . " as files" .
                     " where files.userid=" . $userid . 
                     " and files.type=\"class\"";

     
    $notJobFiles = doDBQueryAssoc($notFileQuery);
    $notJobClasses = doDBQueryAssoc($notClassQuery);
    $allFiles = $notJobFiles;
    $allClasses = $notJobClasses;

    $totalFiles = count($notJobFiles);
    $totalClasses = count($notJobClasses);
  } 
 
  if ($_GET['jobid'] || $_POST['Self']) {
      
    // 04 Aug 2003 : GWA : Now, on to the mote->file mapping stuff.  We're
    //               just going to load this into an array and generate some
    //               javascript at the bottom to deal with it.

    
    foreach($splitMoteFile as $current) {
      $newElement = sscanf($current, "( %d, %d )");
      if ($newElement != "") {
        $moteToFile[$newElement[0]] = $newElement[1];
      }
    }

    // 04 Aug 2003 : GWA : Some of this continues at the bottom, using
    //               javascript to set other options.
  }

  $totalFiles = count($jobFiles) + count($notJobFiles);
  $totalClasses = count($jobClasses) + count($notJobClasses);
  ?>

  <?php
  // ERRORS
  //
  // 05 Oct 2003 : GWA : We can do the error handling here, which hopefully
  //               won't interfere with the tabs below too much.
  // ?>
  
  <?php if (isset($uploadSuccess) && ($uploadSuccess == false)) { 
      if ($testClassFileOutput != "") { ?>
        <p class="error">
        The file that you tried to upload does not seem to be a properly
        formatted MIG-generated class file.
        <br><br> 
        The following error message may
        be helpful:
        <blockquote>
          <span style="background-color:#f8cecd;">
            <strong>
              <?php echo $testClassFileOutput;?>
            </strong>
          </span>
        </blockquote>
      <?php } else if ($testExecutableOutput != "") { ?>
        <p class="error">
        The file that you tried to upload does not seem to be a properly
        compiled TMote Sky binary.
        <br><br> 
        The following error message may
        be helpful:
        <blockquote>
          <span style="background-color:#f8cecd;">
            <strong>
              <?php echo $testExecutableOutput;?>
            </strong>
          </span>
        </blockquote>
      <?php } else { ?>
        <p class="error">
        There was a problem uploading your file.  Please make sure it exists
        and try again.
      <?php } ?>
    </p>
  <?php } ?>
       
  <?php
  // HTML
  //
  // 05 Aug 2003 : GWA : The actual HTML output is a) dependent on the PHP
  //               actions performed above and b) modified extensively on the
  //               client side by the javascript below.
  //
  //               Simply, this is a tabbed environment.  We use CSS to set
  //               the visual tab properties and javascript to change between
  //               them. 
  // ?>

  <div id="tabbar">
    <div id="nametab" class="tabs" onClick="nextStep('nametab');"
         style="border-bottom:1px solid #FFF;
          <?php if ($jobName != "") { ?>
            color:green;
          <?php } else { ?>
            color:red;
          <?php } ?>
          ">
      description
    </div>
    <div id="filetab" class="tabs" onClick="nextStep('filetab');"
         style="
          <?php if ((count($jobFiles) != 0) &&
                    (count($jobClasses) != 0)) { ?>
            color:green;
          <?php } else { ?>
            color:red;
          <?php } ?>
          ">
      files
    </div>
    <div id="motetab" class="tabs" onClick="nextStep('motetab');"
         style="color:green;">
      motes
    </div>
    <?php if ($_NEEDOPTIONTAB) { ?>
      <div id="optiontab" class="tabs" onClick="nextStep('optiontab');"
           style="color:green;">
        options
      </div>
    <?php } ?>
  </div>
  <?php 
  
  // 05 Aug 2003 : GWA : This giant form organizes all of the data collected.
  //               Unfortunately forms split over multiple DIV tags don't do
  //               well in IE or Mozilla, so we use javascript to collect the
  //               data on submit and put it into hidden fields that do get
  //               submitted.
  //
  //               The form below has no action because we could either be
  //               coming back here (on file upload for instance), or
  //               continuing on to the ViewSummary page (if and when one
  //               gets written :-).  So the action is set when the form is
  //               submitted by the javascript.
  // ?>

  <form name="bigform" method="post" enctype="multipart/form-data"> 
    
    <?php
    //
    // NAME TAB
    //
    // Form Element Rules:
    //
    //  jobName : no spaces or apostrophes.  Checked by javascript on this
    //            page.
    //
    //  jobDescription : none.
    //
    // 27 Jul 2003 : GWA : name{panel,tab}
    //               
    //               Here is where users can enter summary information about
    //               a given job.  We want this at the head of the line for
    //               now because users cannot save job data without it.
    //
    //               FUTURE :
    //                1) group info so that people can share jobs with others
    //                2) edit jobs box at bottom?
    // ?>

    <div id="namepanel" class="panels">
      <?php if ($_GET['jobid']) { ?>
        You are editing Job #<?php echo $jobID; ?>.  Modify the following
        information and click Save to save changes or Schedule to save and
        move on to the scheduling page. 
        <br><br> 
      <?php } else { ?>
        To begin creating a new job, enter a name a description in the fields
        below.  When you are finished, click Save to save the job for later
        scheduling or click Schedule to save the job and move directly to the
        scheduling page.
        <br><br>
      <?php } ?>
      <span id=jobNameTitle
        <?php if ($jobName != "") { ?>
          class=formFulfilled
        <?php } else { ?>
          class=formRequired
        <?php } ?>> 
        Name: 
      </span>
      <br>
      <input type="text" name="jobName" 
             style="width:20em;" value="<?php echo $jobName; ?>"
             onBlur="jobNameChange();">
      <br><br>
      <span class=formOptional>
        Description:
      </span>
      <br>
      <textarea name="jobDescription" cols="50" rows="6"><?php
        echo $jobDescription; ?>
      </textarea>
      <br><br>
      <input type="button" onClick="nextStep('filetab');" value="Next">
    </div>
   
    <?php 
    // 27 Jul 2003 : GWA : file{panel,tab}
    //
    //               The elements in this tab allow the user to associate
    //               files with the job.  Either program files or class
    //               message files can be used, and eventually validation
    //               will require that at least one of each be selected.
    //                
    //               FUTURE :
    //                1) put the file upload on this page.  that's tricky and
    //                may require that we grab the acual form values
    //                everywhere and post them so that we can handle reloads.
    // ?>

    <div id="filepanel" class="panels" style="display:none;">
      <?php if ($_GET['jobid']) { ?>
        You can edit the files present in the job below.<br><br>
      <?php } else { ?>
        Here you can associate files with your job.  If the file you want has
        not yet been uploaded, you can do that below.<br><br>
      <?php } ?>
      <span id=textJobFiles 
      <?php if (count($jobFiles) == 0) { ?>
        class=formRequired
      <?php } else { ?>
        class=formFulfilled
      <?php } ?>>
      <strong>Program Files</strong></span>
      <br>
      <?php if ($totalFiles == 0) { ?>
        <p class=error>
        You have not uploaded any executables.  To continue, please upload
        one below.  
        </p>
      <?php } else { ?>
        <table>
          <tr>
            <td>
              <select size=4 style="width:24em;" multiple name="notfile"
                      onChange="notFileChange();">
                <?php 
                while (list($fileID, $fileInfo) = each($notJobFiles)) { ?>
                  <option value="<?php echo $fileID;?>">
                    <?php echo $fileInfo[0]; ?>
                <?php } ?>
              </select>
            </td>
            <td>
              <select size=4 style="width:24em;" multiple name="file[]"
                      onChange="fileChange();">
                <?php
                while (list($fileID, $fileInfo) = each($jobFiles)) { ?>
                  <option value="<?php echo $fileID;?>">
                    <?php echo $fileInfo[0]; ?>
                <?php }?>
              </select>
            </td>
            <td>
            <?php 
            reset($allFiles);
            while(list($fileID, $fileInfo) = each($allFiles)) { ?>
              <div id="fileInfo<?php echo $fileID;?>" 
                    style="display:none;">
                <table style="font-size:8pt;table-layout:auto;">
                <tr>
                  <td style="color:blue;">
                    Name: 
                  </td>
                  <td>
                    <?php echo $fileInfo[0];?>
                  </td>
                </tr>
                <tr>
                  <td style="color:blue;">
                    Uploaded: 
                  </td>
                  <td>
                    <?php echo $fileInfo[1];?>
                  </td>
                </tr>
                <tr>
                  <td style="color:blue;">
                    Description:
                  </td>
                  <td>
                    <?php echo $fileInfo[2];?>
                  </td>
                </tr>
                </table> 
              </div>
            <?php } ?>
            </td>
          </tr>
          <tr>
            <td align="center">
              <input type="button"
                     onClick="addProgramClick();"
                     value="Add >>"
                     style="width:8em;">
            </td>
            <td align="center">
              <input type="button" 
                     onClick="removeProgramClick();"
                     value="<< Remove"
                     style="width:8em;">
            </td>
          </tr>
        </table>
        <?php } ?>
      <span id=textJobClasses 
      <?php if (count($jobClasses) != 0) { ?>
        class=formFulfilled
      <?php } else { ?>
        class=formRequired
      <?php } ?>>
      <strong>Class Files</strong></span>
      <br>
      <?php if ($totalClasses == 0) { ?>
      <p class=error>
      You have not uploaded any class files.  To continue, please upload one
      below.  
      </p>
      <?php } else { ?>
        <table>
          <tr>
            <td>
              <select size=4 style="width:24em" multiple name="notclass"
                      onChange="notClassChange();">
                <?php 
                while (list($classID, $classInfo) = each($notJobClasses)) { ?>
                  <option value="<?php echo $classID;?>">
                    <?php echo $classInfo[0];?>
                <?php }?>
              </select>
            </td>
            <td>
              <select size=4 style="width:24em" multiple name="class[]"
                      onChange="classChange();">
                <?php 
                while (list($classID, $classInfo) = each($jobClasses)) { ?>
                  <option value="<?php echo $classID;?>">
                    <?php echo $classInfo[0];?>
                <?php } ?>
              </select>
            </td>
            <td>
            <?php 
            reset($allClasses);
            while(list($fileID, $fileInfo) = each($allClasses)) { ?>
              <div id="classInfo<?php echo $fileID;?>" 
                    style="display:none;">
                <table style="font-size:8pt;table-layout:auto;">
                <tr>
                  <td style="color:blue;">
                    Name: 
                  </td>
                  <td>
                    <?php echo $fileInfo[0];?>
                  </td>
                </tr>
                <tr>
                  <td style="color:blue;">
                    Uploaded: 
                  </td>
                  <td>
                    <?php echo $fileInfo[1];?>
                  </td>
                </tr>
                <tr>
                  <td style="color:blue;">
                    Description:
                  </td>
                  <td>
                    <?php echo $fileInfo[2];?>
                  </td>
                </tr>
                </table> 
              </div>
            <?php } ?>
            </td>
          </tr>
          <tr>
            <td align="center">
              <input type="button"
                     onClick="addClassClick();"
                     value="Add >>"
                     style="width:8em;">
            </td>
            <td align="center">
              <input type="button" 
                     onClick="removeClassClick();"
                     value="Remove <<"
                     style="width:8em;">
            </td>
          </tr>
        </table>
        <input type="button"
               onClick="nextStep('motetab');"
               value="Next">
      <?php } ?>
      <?php
      // 05 Aug 2003 : GWA : Adding file upload capability here.
      // ?>
      <hr>
      <p>
      <span id=fileUploadText class=formRequired> 
        Select a file to upload:
      </span>
      <br>
      <input type="file" name="moteprogram" 
             onFocus="moteProgramChange();">
      <br><br>
      <span id=fileUploadNameText class=formRequired>
      Provide a reference name:
      </span>
      <br>
      <input type="text" name="uploadname" 
             onBlur="uploadNameChange();"
             style="width:15em;">
      <br><br>
      <span class=formOptional>
      Provide any comments or other information:
      <br>
      </span>
      <textarea name="uploaddescription" cols="50" rows="6"></textarea>
      <br><br>
      <input type="button" onClick="doSubmit('upload');"
             value="Upload">
    </div>

    <?php 
    // 27 Jul 2003 : GWA : mote{panel,tab}
    //
    //               This tab allows users to select, in a variety of ways,
    //               which motes to run their executables or.  If there is
    //               only one executable this is easy, but if there are
    //               several this is a bit more complicated and we are trying
    //               to be as flexible as possible.
    //
    //               FUTURE:
    //                1) put a link to the mote info page here, so that if
    //                people want to select motes based on mote
    //                characteristics (connectivity with other motes, 
    //                environmental conditions, etc...) they can.
    // ?>

    <div id="motepanel" class="panels" style="display:none;">
    <span id="motePanelWarning" class=error
      <?php if (count($jobFiles) != 0) { ?>
        style='display:none;'
      <?php } ?>>
      You must assign a program file to this job before assigning motes.
      <br><br>
      </span>
      <span id="motePanelText"
      <?php if (count($jobFiles) == 0) { ?>
        style='display:none;'
      <?php } ?>>
      Here you can assign programs to motes.
      <br><br>
      </span>
      <input type="radio" name="assigntype" value="single"
             onclick="assignSingleClick();"
             <?php if (count($jobFiles) == 0) { ?>
             disabled
             <?php } ?>>
      Run <select name="oneprogram" style="width:20em;"></select>
      on all available motes.<br>

<!--       <input type="radio" name="assigntype" value="single"
             onclick="assignSingleClick();"
             <?php if (count($jobFiles) == 0) { ?>
             disabled
             <?php } ?>>
      Run <select name="oneprogram" style="width:20em;"></select>
      on topology:<select name="oneprogram" style="width:20em;"></select><br>
    -->  
      <input type="radio" name="assigntype" value="distribute"
             onclick="assignDistributeClick();"
             <?php if (count($jobFiles) == 0) { ?>
             disabled
             <?php } ?>>
      Distribute multiple programs evenly across the entire mote lab.<br>
      <span style="display:none;">
      <input type="radio" name="assigntype" value="numbers"
             onclick="assignNumbersClick();">
      Assign each program to a certain number of motes.<br>
      
      <div id="mp-num" class="subpanels" style="display:none;">
      ToDo
      </div>
      </span>
      <input type="radio" name="assigntype" value="specific"
             onclick="assignSpecificClick();"
             <?php if (count($jobFiles) == 0) { ?>
             disabled
             <?php } ?>>
      Select which program will run on individual motes.<br>

      <div id="mp-individual" class="subpanels" style="display:none;">
        <select size=4 style="width:15em;" name="motefile"
                onchange="changeSelectedProgram();"></select>
        <table>
          <tr>
            <th>
              Motes running the selected program above.
            </th>
            <th>
              Available motes.
            </th>
          </tr>
            <td>
              <select size=6 style="width:15em;" multiple name="selectedmotes">
              </select>
            <td>
              <select size=6 style="width:15em;" multiple name="allmotes">
                <?php 
                while (list($unused, $activeMote) = each($activeMotes)) {
                  if ($_POST['Self'] ||
                      $_GET['jobid']) {
                    $assignedMotes = array_keys($moteToFile);
                    if (in_array($activeMote, $assignedMotes)) { 
                      continue;
                    } 
                  } ?>
                  <option value="<?php echo $activeMote;?>">
                    Mote <?php echo $activeMote; ?>
                  </option>
                <?php } ?>
              </select>
            </td>
          </tr>
          <tr>
            <td align="center">
              <input type="button"
                     onClick="moveMotes(this.form.selectedmotes,
                                        this.form.allmotes);"
                     value=">>">
            </td>
            <td align="center">
              <input type="button" 
                     onClick="moveMotes(this.form.allmotes,
                                        this.form.selectedmotes);"
                     value="<<">
            </td>
          </tr>
        </table>
      </div>
      <?php if ($_NEEDOPTIONTAB) { ?>
        <input type="button"
               onClick="nextStep('optiontab');"
               value="Next">
      <?php } ?>
    </div>
    <?php if ($_NEEDOPTIONTAB) { ?>
      <div id="optionpanel" class="panels" style="display:none;">
      <?php if ($_HAVEPOWERMANAGE) { ?>
        <input type="checkbox" name="powermanage"
        <?php if ($jobPowerManage != 0) { ?> checked
        <?php } ?>> Check here to enable ~250 Hz power data collection on Mote 118.<br>
      <?php } ?>
      <?php if (getSessionVariable("type") == "admin") { ?>
        <input type="checkbox" name="cronjob"
        <?php if ($jobIsCronJob != 0) { ?> checked
        <?php } ?> onChange="changeCronJob();"> 
        Check here to run as cron job.<br>
        <select size=2 style="width:15em;" name="crontime"
        <?php if ($jobIsCronJob == 0) { ?> disabled
        <?php } ?>>
          <?php foreach (array(1, 5) as $currentOpt) { ?>
            <option value=<?php echo $currentOpt;
              if ($currentOpt == $jobCronTime) {
                echo " selected";
              } ?>>
              <?php echo $currentOpt;?>
            </option>
          <?php } ?>
        </select>
        Choose time to try and run cron job.<br>
        <select size=3 style="width:15em;" name="cronfreq"
        <?php if ($jobIsCronJob == 0) { ?> disabled
        <?php } ?>>
        <?php foreach (array(15, 30, 60) as $currentOpt) { ?>
          <option value=<?php echo $currentOpt;
            if ($currentOpt == $jobCronFreq) {
              echo " selected";
            } ?>>
            <?php echo $currentOpt;?>
          </option>
        <?php } ?>
        </select>
        Choose freq to try and run cron job.<br>
        The following local script will be executed when the job starts and
        killed when it ends (enter a full path):<br>
        <input type="text" name="duringrun"
               style="width:20em;" value="<?php echo $jobDuringRun; ?>"><br>
        The following local script will be executed when the job
        completes:<br>
        <input type="text" name="postprocess"
               style="width:20em;" value="<?php echo $jobPostProcess; ?>"><br>
      <?php } ?>
      </div>
    <?php } ?>
    <input type="button" 
           onClick="doSubmit('reload');"
           value="Reload">
    <input type="button"
      <?php if ($_GET['jobid']) { ?>
        onClick="doSubmit('update')";
      <?php } else { ?>
        onClick="doSubmit('onward');"
      <?php } ?>
           value="Submit">
    <input type="hidden" name="Name">
    <input type="hidden" name="Description">
    <input type="hidden" name="Files">
    <input type="hidden" name="Info">
    <input type="hidden" name="Classes">
    <input type="hidden" name="Self">
    <input type="hidden" name="DistType">
    <input type="hidden" name="Where"> 
    <input type="hidden" name="Upload">
    <input type="hidden" name="UploadName">
    <input type="hidden" name="UploadType">
    <input type="hidden" name="UploadDesc">
    <input type="hidden" name="doPowerManage">
    <?php if (getSessionVariable("type") == "admin") { ?>
      <input type="hidden" name="IsCronJob">
      <input type="hidden" name="CronFreq">
      <input type="hidden" name="CronTime">
      <input type="hidden" name="DuringRun">
      <input type="hidden" name="PostProcess">
    <?php } ?>
    <input type="hidden" name="ReloadProtect">
  </form>

<script language="JavaScript">
<!--
  <?php
  // 30 Oct 2003 : GWA : Defaults.  We're moving to a model where all of the
  //               state is contained down here, and the buttons above just
  //               call into things that change that state.  We'll initiate
  //               things here and allow on onload function to set things up.
  //?>

  var activePanel = "namepanel";

  <?php 
  // 30 Oct 2003 : GWA : If we are reloading or editing a job we want to stay
  //               on the same panel that we left.
  if (($_POST['Self'] == true) || 
         ($_GET['jobid'])) { ?>
    activePanel = "<?php echo $jobCurrentPanel; ?>";
  <?php } ?>

  var moteAssignedFiles = new Array();
  var moveTemp = new Array();
  <?php 
  // 22 Oct 2003 : GWA : Generate the active motes array.
  ?>
  var activeMotes = [<?php for ($i = 0; $i < count($activeMotes); $i++) {
    echo $activeMotes[$i]; 
    if ($i < (count($activeMotes) - 1)) { 
      echo ", ";
    }
  }?>
  ];

  <?php 
  // 04 Aug 2003 : GWA : OK, now things get ugly.  If we received either POST
  //               or GET data we have an idea of how things were mapped,
  //               what options were chosen, etc.  We need to express that to
  //               the javascript somehow, and we do it by _generating_
  //               javascript.  Yuck.
  // ?>

  var oneSelectedFile = 0;
  var numProgramFiles = 0;
  var numClassFiles = <?php echo count($jobClasses); ?>;
  var numProgramFiles = <?php echo count($jobFiles); ?>;
  var editingJob;
  <?php if (isset($_GET['jobid'])) { ?>
    editingJob = true;
  <?php } else { ?>
    editingJob = false;
  <?php } ?>

  <?php if (($_POST['Self'] == true) ||
      ($_GET['jobid'])) {
    
    // 04 Aug 2003 : GWA : Necessary, but a hack: zero out the
    //               moteAssignedFiles array.
    //
    // 22 Oct 2003 : GWA : Changed to use max possible mote number.

    for ($i = 1; $i < $MAXMOTENUM; $i++) { ?>
      moteAssignedFiles[<?php echo $i ?>] = 0;
    <?php }
    
    // 04 Aug 2003 : GWA : Now load our values in.
    ?>
    var warningLabel = "";
    <?php
    while(list($moteID, $fileID) = each($moteToFile)) { 
      if ($fileID == "") {
        continue;
      }
      ?>
      oneSelectedFile = <?php echo $fileID; ?>;
      moteAssignedFiles[<?php echo $moteID; ?>] = <?php echo $fileID ?>;
    <?php }
  } ?>

  var assignSingle = false;
  var assignDistribute = false;
  var assignNumbers = false;
  var assignSpecific = false;

  <?php
  // 04 Aug 2003 : GWA : If we're coming from POST data, we want to make sure
  //               that we stay on the same tab.
  // ?>

  <? if (($_POST['Self'] == true) || 
         ($_GET['jobid'])) { ?>
    <?php if ($jobDistType == "single") { ?>
      document.bigform.assigntype[0].checked = true;
      assignSingle = true;
    <?php } else if ($jobDistType == "even") { ?>
      document.bigform.assigntype[1].checked = true; 
      assignDistribute = true;
    <?php } else if ($jobDistType == "individual") { ?>
      document.bigform.assigntype[3].checked = true;
      assignSpecific = true;
    <?php }

    if (count($jobFiles) <= 1) { ?>
      document.bigform.assigntype[1].disabled = true;
    <?php }
  } ?>
  
  <?php
  // 05 Aug 2003 : GWA : Javascript to submit the form and point it in the
  //               appropriate direction.
  // 
  // 27 Oct 2003 : GWA : Going over this to try and get it rock solid.  What
  //               a mess.
  // ?>
  
  function doSubmit(whatkind) {
  
    <?php 
    // 28 Jul 2003 : GWA : Eventually we want to do some verification here,
    //               but for now I'm just going to assign the mote files.
    //
    // 04 Aug 2003 : GWA : Turns out that forms divided by tabs (as this one
    //               is), don't really work in Mozilla, and probably not
    //               exactly in IE either.  So what we do is use the
    //               javascript here to retrieve values from the form fields
    //               and pack it into a few hidden fields that (for some
    //               reason) DO get sent correctly.
    //?>

    <?php
    // 27 Oct 2003 : GWA : collected holds the program->mote string passed to
    //               the submit page.  
    // ?>

    var collected = "";
    var foundOneFile = 0;
    
    <?php
    // 20 Oct 2003 : GWA : We need to do some early validation here because
    //               some stuff just breaks if the user hasn't selected
    //               files.
    // ?>
    
    if ((document.bigform.oneprogram.selectedIndex != -1) ||
        (oneSelectedFile != 0)) {
      if (document.bigform.oneprogram.selectedIndex != -1) {
        var comingFrom = document.bigform.oneprogram;
        oneSelectedFile = comingFrom.options[comingFrom.selectedIndex].value;
      }
      foundOneFile = 1;
    }
    
    if ((whatkind == "onward") ||
        (whatkind == "update")) {
      if (numProgramFiles == 0) {
        alert("You must associate at least one program file with this job");
        changePanels('filepanel');
        return false;
      }
      if (numClassFiles == 0) {
        alert("You must associate at least one message class with this job");
        changePanels('filepanel');
        return false;
      }
    }
    
    if (assignSingle) {
      <?php 
      // 28 Jul 2003 : GWA : This one's easy: just assign every mote to
      //               the selected fileID.
      //
      // 27 Oct 2003 : GWA : But first check and make sure that that program
      //               is selected.
      // ?>
      
      for (i = 0; i < activeMotes.length; i++) {
        collected += "( " + activeMotes[i] + ", " + oneSelectedFile + " )|";
      }
      
      document.bigform.DistType.value = "single";

    } else if (assignDistribute) {
      <?php 
      // 29 Jul 2003 : GWA : This one's not too difficult either.  Just loop
      //               through the selected programs assigning motes.
      // ?>
     
      var comingFrom = document.bigform.elements['file[]'];
      
      for (i = 0; i < activeMotes.length; i++) {
        var currentIndex = (i % comingFrom.options.length);
        var fileID = comingFrom.options[currentIndex].value;
        
        collected += "( " + activeMotes[i] + ", " + fileID + " )|";
      }
      document.bigform.DistType.value = "even";

    } else if (assignNumbers) {
      alert("not supported");
      return false;
    } else if (assignSpecific) {
      
      <?php 
      // 27 Oct 2003 : GWA : Surprisingly enough I think this one's the most
      //               error proof of them all because of all the other state
      //               we're keeping around.
      // ?>
      <?php
      //for (var i = 1; i < moteAssignedFiles.length; i++) {
      //  if (moteAssignedFiles[i] != 0) {
      //    collected += "( " + i + ", " + moteAssignedFiles[i] + ")|";
      //  }
      //}

      // 30 Aug 2004: swies : this screws up when we have inactive motes, but now it's fixed ?>

      for (i = 0; i < activeMotes.length; i++) {
        if (moteAssignedFiles[activeMotes[i]] != 0) {
          collected += "( " + activeMotes[i] + ", " + moteAssignedFiles[activeMotes[i]] + ")|";
        }
      }
      document.bigform.DistType.value = "individual";
    }

    <?php 
    // 04 Aug 2003 : GWA : Dump the collected mote->file info into this
    //               string.
    // ?>

    document.bigform.Info.value = collected;
    
    var classesFrom = document.bigform.elements['class[]'];
    var classes = "";

    if (classesFrom) {
      for (i = 0; i < classesFrom.options.length; i++) {
        classes += classesFrom.options[i].value + ",";
      }
    }
    document.bigform.Classes.value = classes;
    
    var filesFrom = document.bigform.elements['file[]'];
    var files = "";

    if (filesFrom) {
      for (i = 0; i < filesFrom.options.length; i++) {
        files += filesFrom.options[i].value + ",";
      }
    } 

    document.bigform.Files.value = files;
    document.bigform.Name.value = document.bigform.jobName.value;
    if (document.bigform.powermanage.checked) {
      document.bigform.doPowerManage.value = 1;
    } else {
      document.bigform.doPowerManage.value = 0;
    }
    <?php if (getSessionVariable("type") == "admin") { ?>
      if (document.bigform.cronjob.checked) {
        document.bigform.IsCronJob.value = 1;
        var firstIndex = document.bigform.cronfreq.selectedIndex;
        var secondIndex = document.bigform.crontime.selectedIndex;
        document.bigform.CronFreq.value =
          document.bigform.cronfreq[firstIndex].value;
        document.bigform.CronTime.value = 
          document.bigform.crontime[secondIndex].value;
      } else {
        document.bigform.IsCronJob.value = 0;
        document.bigform.CronTime.value = 0;
        document.bigform.CronFreq.value = 0;
      }
      document.bigform.DuringRun.value = 
        document.bigform.duringrun.value;
      document.bigform.PostProcess.value =
        document.bigform.postprocess.value;
    <?php } ?>
    document.bigform.Description.value = document.bigform.jobDescription.value;

    <?
    // 05 Aug 2003 : GWA : Based on what kind of submit was pressed, we
    //               have different things to take care of and different
    //               targets.
    // ?>

    if (whatkind == "upload") {
      <?php
      // 05 Aug 2003 : GWA : We need to retrieve the upload information
      //               and store it in the hidden fields.
      // ?>
      if (document.bigform.moteprogram.value == "") {
        alert("You must provide a file to upload");
        return false;
      }

      if (document.bigform.uploadname.value == "") {
        alert("You must provide a reference name");
        return false;
      }
      
      referenceName = new String(document.bigform.uploadname.value);

      if (referenceName.indexOf(" ") != -1) {
        alert("The reference name may not contain spaces");
        return false;
      }
      ourArray = new Array();
      ourString = new String(document.bigform.moteprogram.value);
      ourArray = ourString.split("/");
      if (ourString.length < 2) {
        ourArray = ourString.split("\\");
      }
      lastBit = new String(ourArray[ourArray.length - 1]);
      veryLastBit = lastBit.split(".");
      veryLastBit = veryLastBit[veryLastBit.length - 1];

      if (veryLastBit == "exe") {
        document.bigform.UploadType.value = "program";
      } else if (veryLastBit == "class") {
        document.bigform.UploadType.value = "class";
      } else {
        alert(lastBit + " is not a mote-executable (.exe) or MIG-generated class file (.class)\nPlease try again!");
        return false;
      }
      document.bigform.UploadName.value = document.bigform.uploadname.value;
      document.bigform.UploadDesc.value =
        document.bigform.uploaddescription.value;
      document.bigform.Where.value = activePanel;

      <?php 
      // 05 Aug 2003 : GWA : Set the upload flag so that we know to do
      //               it above.
      // ?>
      document.bigform.Upload.value = true;
      document.bigform.Self.value = true;
      if (editingJob) {
        document.bigform.action =
          "jobs-create.php?jobid=<?php echo $_GET['jobid'];?>";
      } else {
        document.bigform.action = "jobs-create.php";
      }
    } else if (whatkind == "onward") {
      if (document.bigform.Name.value == "") {
        alert("You must provide a name for this job");
        changePanels('namepanel');
        return false;
      }
      <?php 
      // 30 Oct 2003 : GWA : I don't think people really want to come back
      //               when they edit jobs.  They probably just want to start
      //               again at the beginning.
      // ?>
      document.bigform.Where.value = 'namepanel'; 
      document.bigform.action = "jobs-submit.php";
    } else if (whatkind == "reload") {
      if (editingJob) {
        document.bigform.action =
          "jobs-create.php?jobid=<?php echo $_GET['jobid'];?>";
      } else {
        document.bigform.action = "jobs-create.php";
      }
      document.bigform.Where.value = activePanel;
      document.bigform.Self.value = true;
    } else if (whatkind == "update") {
      <?php 
      // 30 Oct 2003 : GWA : I don't think people really want to come back
      //               when they edit jobs.  They probably just want to start
      //               again at the beginning.
      // ?>
      document.bigform.Where.value = 'namepanel'; 
      document.bigform.action =
        "jobs-submit.php?jobid=<?php echo $_GET['jobid'];?>";
    }
    now = new Date();
    document.bigform.ReloadProtect.value = now.getTime();
    document.bigform.submit();
    
    return true;
  }

  <?php 
  // 10 Oct 2003 : GWA : Start adding tab validation that changes colors 
  //               when people get things 'right'.
  ?>
  function validateTab(activeTab) {
    
    var nameTab = document.getElementById('nametab');
    var fileTab = document.getElementById('filetab');
    var moteTab = document.getElementById('motetab');
    <?php // var optionTab = document.getElementById('optiontab'); ?>
    
    if (activeTab == "nametab") {
      if (document.bigform.jobName.value != "") {
        nameTab.style.color = "green";
      } else {
        nameTab.style.color = "red";
      }
    }

    if (activeTab == "filetab") {
      var comingFrom = document.bigform.elements['file[]'];
      var comingClasses = document.bigform.elements['class[]'];
      if (comingFrom.options &&
          comingClasses.options) {
        fileElement = document.getElementById('textJobFiles');
        classElement = document.getElementById('textJobClasses');
        if (comingFrom.options.length != 0) {
          fileElement.className = 'formFulfilled';
        } else {
          fileElement.className = 'formRequired';
        }
        if (comingClasses.options.length != 0) {
          classElement.className = 'formFulfilled';
        } else {
          classElement.className = 'formRequired';
        }

        if ((comingFrom.options.length != 0) &&
            (comingClasses.options.length != 0)) {
          fileTab.style.color = "green";
        } else {
          fileTab.style.color = "red";
        }
      }
    }
  }
  
  function clearAllAssigns() {
    assignSingle = false;
    assignDistribute = false;
    assignNumbers = false;
    assignSpecific = false;
  }

  function disableAllMoteBoxes() {
    document.bigform.assigntype[0].disabled = true;
    document.bigform.assigntype[1].disabled = true;
    document.bigform.assigntype[3].disabled = true;
  }
  
  function uncheckAllMoteBoxes() {
    document.bigform.assigntype[0].checked = false;
    document.bigform.assigntype[1].checked = false;
    document.bigform.assigntype[3].checked = false;
  }
  
  function changeCronJob() {
    if (document.bigform.cronjob.checked) {
      document.bigform.cronfreq.disabled = false;
      document.bigform.cronfreq.selectedIndex = 2;
      document.bigform.crontime.disabled = false;
      document.bigform.crontime.selectedIndex = 0;
    } else {
      document.bigform.cronfreq.selectedIndex = -1;
      document.bigform.cronfreq.disabled = true;
      document.bigform.crontime.selectedIndex = -1;
      document.bigform.crontime.disabled = true;

    }
  }

  <?php
  // 31 Oct 2003 : GWA : Remove when no files defined.

  if ($totalFiles != 0) { ?>
    
    function programCommon(previousLength) {
      var fileElement = document.bigform.elements['file[]'];
      if (fileElement.options.length == 0) {
        uncheckAllMoteBoxes();
        disableAllMoteBoxes();
        clearAllAssigns();
        document.getElementById('motePanelWarning').style.display = '';
        document.getElementById('motePanelText').style.display = 'none';
        oneSelectedFile = 0;
      } else if (fileElement.options.length == 1) {
        uncheckAllMoteBoxes();
        disableAllMoteBoxes();
        clearAllAssigns();
        document.bigform.assigntype[0].disabled = false;
        document.bigform.assigntype[3].disabled = false;
        document.bigform.assigntype[0].checked = true;
        document.getElementById('motePanelWarning').style.display = 'none';
        document.getElementById('motePanelText').style.display = '';
        assignSingle = true;
        oneSelectedFile = document.bigform.elements['file[]'][0].value;
      } else {
        <?php
        // 31 Oct 2003 : GWA : Here we don't do anything currently, since we
        //               don't want to muck up previous selections.  The only
        //               time that we do is if we go 1->2 in which case we
        //               set a few things up.
        ?>
        if ((fileElement.options.length == 2) &&
            (previousLength < 2)) {
          uncheckAllMoteBoxes();
          disableAllMoteBoxes();
          clearAllAssigns();
          document.bigform.assigntype[1].disabled = false;
          document.bigform.assigntype[3].disabled = false;
          document.bigform.assigntype[1].checked = true;
          assignDistribute = true;
        }
        document.getElementById('motePanelWarning').style.display = 'none';
        document.getElementById('motePanelText').style.display = '';
      }
      numProgramFiles = fileElement.options.length;
      validateTab('filetab');
    }
    
    function addProgramClick() {
      previousLength = document.bigform.elements['file[]'].options.length;
      moveCommon(document.bigform.elements['notfile'],
                 document.bigform.elements['file[]']);
      programCommon(previousLength);  
    }

    function removeProgramClick() {
      previousLength = document.bigform.elements['file[]'].options.length;
      moveCommon(document.bigform.elements['file[]'],
                 document.bigform.elements['notfile']);
      programCommon(previousLength);
    }

  <?php } ?>
  
  <?php
  // Oct 31 2003 : GWA : Remove when no classes defined.
  //
  if ($totalClasses != 0) { ?>
    
    function classCommon() {
      classElement = document.bigform.elements['class[]'];
      numClassFiles = classElement.options.length;
      if (numClassFiles == 0) {
        document.getElementById('textJobClasses').className = 
          'formRequired';
      } else {
        document.getElementById('textJobClasses').className =
          'formFulfilled';
      }
    }
    
    function addClassClick() {
      moveCommon(document.bigform.elements['notclass'],
                 document.bigform.elements['class[]']);
      classCommon();
    }
    
    function removeClassClick() {
      moveCommon(document.bigform.elements['class[]'],
                 document.bigform.elements['notclass']);
      classCommon();
    }
  <?php } ?>

  function moveMotes(from, to) {
    moveCommon(from, to);
 
    inMotes = document.bigform.selectedmotes;
    outMotes = document.bigform.allmotes;

    var selectedValue = document.bigform.motefile;
    var selectedIndex = selectedValue.selectedIndex;
    var selectedValue = selectedValue.options[selectedIndex].value;

    for (var i = 0; i < outMotes.options.length; i++) {
      moteAssignedFiles[outMotes.options[i].value] = 0;
    }

    for (var i = 0; i < inMotes.options.length; i++) {
      moteAssignedFiles[inMotes.options[i].value] = selectedValue; 
    }
  }
 
  function changeSelectedProgram() {
    
    var selectedValue = document.bigform.motefile;
    var selectedIndex = selectedValue.selectedIndex;
    var inMotes = document.bigform.selectedmotes;
    
    if (selectedIndex == -1) {
      inMotes.options.length = 0;
      return;
    }

    var value = selectedValue.options[selectedIndex].value;

    inMotes.options.length = 0;
    for (var i = 0; i < moteAssignedFiles.length; i++) {
      if (moteAssignedFiles[i] == value) {
        inMotes.options[inMotes.options.length] =
          new Option("Mote " + i, i, false, false);
      }
    }
  }

  function moveCommon(from, to) {
    for (var i = 0; i < from.options.length; i++) {
      if (from.options[i].selected) {
        to[to.options.length] = 
          new Option(from.options[i].text,
                     from.options[i].value);
      }
    }
    for (i = (from.options.length - 1); i >= 0; i--) {
      if (from.options[i].selected) {
        from.options[i] = null;
      }
    }
    from.selectedIndex = -1;
    to.selectedIndex = -1;
  }

  <?php 
  // 27 Jul 2003 : GWA : Used to change between the various tabbed portions
  //               of the input screen.
  //              
  //               The current progression is:
  //                 name{panel,tab} -> file{panel,tab} ->
  //                 motes{panel,tab} -> ...
  //              
  //               This progression isn't maintained here though, but rather
  //               in the buttons below that control "next" behavior.
  // ?>
  
  function nextStep(activeTab) {
    
    var activePanel;

    if (activeTab == "filetab") {
      activePanel = "filepanel";
    } else if (activeTab == "motetab") {
      activePanel = "motepanel";
    } else if (activeTab == "nametab") {
      activePanel = "namepanel";
    } <?php if ($_NEEDOPTIONTAB) { ?>
      else if (activeTab == "optiontab") {
      activePanel = "optionpanel";
    } <?php } ?>

    changePanels(activePanel);
  }
 
  <?php 
  // 05 Aug 2003 : GWA : Called to switch the active panel.
  // ?>

  function changePanels(passedPanel) {
    
    var namePanel = document.getElementById('namepanel');
    var nameTab = document.getElementById('nametab');
    var filePanel = document.getElementById('filepanel');
    var fileTab = document.getElementById('filetab');
    var motePanel = document.getElementById('motepanel');
    var moteTab = document.getElementById('motetab');
    <?php if ($_NEEDOPTIONTAB) { ?>
      var optionPanel = document.getElementById('optionpanel');
      var optionTab = document.getElementById('optiontab');
    <?php } ?> 
    <?php
    // 05 Sep 2003 : GWA : For some reason the file browse info gets zeroed
    //               when we change panels, so we kill this too.
    // ?>

    document.bigform.uploadname.value = "";
    
    <?php
    // 27 Oct 2003 : GWA : Hack for now, until we can make this more
    // graceful.
    // ?>

    if ((passedPanel == "motepanel") &&
        ((!document.bigform.elements['file[]']) || 
         (document.bigform.elements['file[]'].options.length == 0))) {
    }
    <?php
    // 05 Aug 2003 : GWA : Turn everything off to start.
    // ?>

    filePanel.style.display = "none";
    fileTab.style.borderBottomColor = "#000";
    <?php // document.getElementById('mp-num').style.display = "none"; ?>
    document.getElementById('mp-individual').style.display = "none";
    
    motePanel.style.display = "none";
    moteTab.style.borderBottomColor = "#000";
    
    namePanel.style.display = "none";
    nameTab.style.borderBottomColor = "#000";
    
    <?php if ($_NEEDOPTIONTAB) { ?> 
      optionPanel.style.display = "none";
      optionTab.style.borderBottomColor = "#000";
    <?php } ?>

    <?php
    // 05 Aug 2003 : GWA : If we are coming to the motepanel, which has
    //               multiple parts that appear based on the users chosen
    //               options, make sure that the correct sub-tab gets
    //               reopened.
    //?>

    if (passedPanel == "motepanel") {
      if (document.bigform.assigntype[0].checked ||
          document.bigform.assigntype[1].checked) {
        passedPanel = "motepanel";
      } else if (document.bigform.assigntype[2].checked) {
        passedPanel = "mp-num";
      } else if (document.bigform.assigntype[3].checked) {
        passedPanel = "mp-individual";
      }
    }

    <?php
    // 05 Aug 2003 : GWA : Now the main panel selection.
    // ?>

    if (passedPanel == "filepanel") {
      filePanel.style.display = "";
      fileTab.style.borderBottomColor = "#FFF";
    } else if (passedPanel == "motepanel") {
      populateMoteProgramOptions();
      
      motePanel.style.display = "";
      moteTab.style.borderBottomColor = "#FFF";
    } else if (passedPanel == "mp-individual") {
      populateMoteProgramOptions();
      
      motePanel.style.display = "";
      moteTab.style.borderBottomColor = "#FFF";

      document.getElementById('mp-individual').style.display = "";
    } else if (passedPanel == "mp-num") {
      populateMoteProgramOptions();
      
      motePanel.style.display = "";
      moteTab.style.borderBottomColor = "#FFF";
      
      document.getElementById('mp-num').style.display = "";
    } else if (passedPanel == "namepanel") {
      namePanel.style.display = "";
      nameTab.style.borderBottomColor = "#FFF";
    } 
    <?php if ($_NEEDOPTIONTAB) { ?>
    else if (passedPanel == "optionpanel") {
      optionPanel.style.display = "";
      optionTab.style.borderBottomColor = "#FFF";
    } <?php } ?>
    activePanel = passedPanel;
  } 

  <?php 
  // 05 Aug 2003 : GWA : Called within the mote selection panel to change
  //               subpanels.
  // ?>
 
  function assignSingleClick() {
    changePanels('motepanel');
    clearAllAssigns();
    assignSingle = true;
    return true;
  }
  
  function assignDistributeClick() {
    changePanels('motepanel');
    clearAllAssigns();
    assignDistribute = true;
    return true;
  }
  
  function assignNumbersClick() {
    changePanels('mp-num');
    clearAllAssigns();
    assignNumbers = true;
    return true;
  }

  function assignSpecificClick() {
    changePanels('mp-individual');
    clearAllAssigns();
    assignSpecific = true;
    return true;
  }
  
  function populateMoteProgramOptions() {
    var addmenu = document.bigform.motefile;
    var addmenu2 = document.bigform.oneprogram;
    var filesSelected = document.bigform.elements['file[]'];
    
    addmenu.options.length = 0;
    for (var i = 0; i < numProgramFiles; i++) {
      addmenu.options[i] = new Option(filesSelected.options[i].text);
      addmenu.options[i].value = filesSelected.options[i].value;
      addmenu2.options[i] = new Option(filesSelected.options[i].text);
      addmenu2.options[i].value = filesSelected.options[i].value;
    }

    var foundInList = false;
    for (var i = 1; i < moteAssignedFiles.length; i++) {
      var found = false;
      for (var j = 0; j < numProgramFiles; j++) {
        if ((document.bigform.selectedmotes.options.length) &&
            (document.bigform.selectedmotes.options[0].value ==
              filesSelected.options[j].value)) {
          foundInList = true;
        }
        if (moteAssignedFiles[i] == filesSelected.options[j].value ||
            moteAssignedFiles[i] == 0) {
          found = true;
          break;
        }
      }
      if (found == false) {
        moteAssignedFiles[i] = 0;
        var into = document.bigform.allmotes;
        into.options[into.options.length] = 
          new Option("Mote " + i, i, false, false);
      }
    }

    if (numProgramFiles) {
      addmenu.options[0].selected = true;
      addmenu2.options[0].selected = true;
    }
    
    if (foundInList == false) {
      changeSelectedProgram();
    }
  }

  function changeProgramName() {

    if (document.bigform.moteprogram.value == "") {
      return true;
    }

    var blah = new Array();
    ourString = new String(document.bigform.moteprogram.value);
    blah = ourString.split("/");
    if (blah.length < 2) {
      blah = ourString.split("\\");
    }
    lastBit = new String(blah[blah.length - 1]);
    veryLastBit = lastBit.split(".");
    firstBit = new String(veryLastBit[0]);
    matchSpace = new RegExp(' ', 'gi');
    var newString = firstBit.replace(matchSpace, '');
    document.bigform.uploadname.value = newString;
    element = document.getElementById('fileUploadNameText');
    element.className = 'formFulfilled';
    return true;
  }

  function moteProgramChange() {
    changeProgramName();
    var element = document.getElementById('fileUploadText');
    if (document.bigform.moteprogram.value != '') {
      element.className = 'formFulfilled';
    } else {
      element.className = 'formRequired';
    }
    return;
  }

  function uploadNameChange() {
    var element = document.getElementById('fileUploadNameText');
    if (document.bigform.uploadname.value != '') {
      element.className = 'formFulfilled';
    } else {
      element.className = 'formRequired';
    }
    return;
  }
  
  function jobNameChange() {
    var element = document.getElementById('jobNameTitle');
    var badChar = checkInput(document.bigform.jobName.value,
                             okJobNameCharacters,
                             true);
    if (badChar != '') {
      alert('Job name may not contain \"' + badChar + '\".');
      document.bigform.jobName.value='';
    }
    if (document.bigform.jobName.value != '') {
      element.className='formFulfilled';
    } else {
      element.className='formRequired';
    }
    validateTab('nametab');
    return;
  }

  var currentFileInfoId = 0;
  var currentClassInfoId = 0;

  function fileChangeCommon(selectedID) {
    var element = document.getElementById('fileInfo' + selectedID);
    if (currentFileInfoId != 0) {
      document.getElementById('fileInfo' + currentFileInfoId).style.display =
      'none';
    }
    document.getElementById('fileInfo' + selectedID).style.display = '';
    currentFileInfoId = selectedID;
    return;
  }
  
  function classChangeCommon(selectedID) {
    var element = document.getElementById('classInfo' + selectedID);
    if (currentClassInfoId != 0) {
      document.getElementById('classInfo' + currentClassInfoId).style.display =
      'none';
    }
    document.getElementById('classInfo' + selectedID).style.display = '';
    currentClassInfoId = selectedID;
    return;
  }

  function notFileChange() {
    var ourList = document.bigform.notfile;
    var fileID = ourList[ourList.selectedIndex].value;
    fileChangeCommon(fileID);
    return;
  }
  
  function fileChange() {
    var ourList = document.bigform.elements['file[]'];
    var fileID = ourList[ourList.selectedIndex].value;
    fileChangeCommon(fileID);
  }
  
  function notClassChange() {
    var ourList = document.bigform.notclass;
    var fileID = ourList[ourList.selectedIndex].value;
    classChangeCommon(fileID);
  }
 
  function classChange() {
    var ourList = document.bigform.elements['class[]'];
    var fileID = ourList[ourList.selectedIndex].value;
    classChangeCommon(fileID);
  }

  function doLoad() {
    changePanels(activePanel);
  }
  
  <?php
  // 04 Nov 2003 : GWA : General input checking function.  charSet is a
  //               statically declared string which declares either a) which
  //               characters are permitted (if inOrOut == true) or b) which
  //               characters are forbidden (if inOrOut == false).
  //
  //               Returns '' if the string passes, or the offending
  //               character if it does not.
  // ?>
  
  var lowerAlpha = "abcdefghijklmnopqrstuvwxyz"
  var upperAlpha = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
  var numeric = "0123456789";
  
  var okJobNameCharacters = lowerAlpha + upperAlpha + numeric + "_";

  function checkInput(input, charSet, inOrOut) {
    for (var i = 0; i < input.length; i++) {
      var letter = input.charAt(i);
      if (inOrOut) {
        if (charSet.indexOf(letter) == -1) {
          return letter;
        }
      } else {
        if (charSet.indexOf(letter) != -1) {
          return letter;
        }
      }
    }
    return '';
  }    
  onload=doLoad;
//-->
</script>

<?php } else { ?>
  <p> You cannot create jobs until you log in.
<?php } ?>

<?php
  include "nav/default_bot.php";
?>
