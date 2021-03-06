<?php 
  global $_DISPLAYNEW;
  $_DISPLAYNEW = false;
  global $_DISPLAYMOTD;
  $_DISPLAYMOTD = false;
  $_DISPLAYLOGIN = false;
  global $a;

  include "nav/default_top.php";

  // 01 Aug 2006 : GWA : Need to start the database if the user is not logged
  //               in.

  if (!$a->getAuth()) {
    $DB = startDB();
  }

  /*
   * user-signup.php
   *
   */
?>

<?php
  
$userName = trim($_GET['userName']);
$firstName = trim($_GET['firstName']);
$lastName = trim($_GET['lastName']);
$academicInstitution = trim($_GET['academicInstitution']);
$webpageURL = trim($_GET['webpageURL']);
$isStudent = trim($_GET['isStudent']);
$advisorName = trim($_GET['advisorName']);
$advisorEmail = trim($_GET['advisorEmail']);
$jobDescription = trim($_GET['jobDescription']);
$doSend = trim($_GET['doSend']);
$doBottom = true;

// 07 Oct 2003 : GWA : Try and prevent reloads from doing damage.

if (doReloadProtect()) {
  $doSend = false;
}


if ($doSend) {

  $error = false;

  if ($userName == "") {
    $error = true;
  }

  if ($firstName == "") {
    $error = true;
  }

  if ($lastName == "") {
    $error = true;
  }

  if ($isStudent &&
      ($advisorName == "")) {
    $error = true;
  }

  if ($isStudent &&
      ($advisorEmail == "")) {
    $error = true;
  }

  if ($advisorEmail == $userName) {
    $error = true;
    $advisorEmail = "";
  }
  
  if ($academicInstitution == "") {
    $error = true;
  }

  // 13 Mar 2006 : GWA : Let's do some sanity checking here.  In particular:

  // 13 Mar 2006 : GWA : Does the user already exist?

  if (!$error) {
    $usernameCheckQuery = "select username from " . $_SESSIONTABLENAME .
                          " where username=\"" . $userName . "\"";
    $usernameCheckResult = doDBQuery($usernameCheckQuery);

    if ($usernameCheckResult->numRows() > 0) { ?>
      <p>
      <span style="color:red;">User with email address 
      <?php echo $userName ?> already exists on Rainier!</strong></span><br>
      <?php 
      $error = true;
      $doBottom = false;
    }
  }
  
  // 31 Jul 2006 : GWA : Pending request for this user?

  if (!$error) {
    $usernameCheckQuery = "select username from " . $_PENDINGUSERTABLENAME . 
                          " where username=\"" . $userName . "\"";
    $usernameCheckResult = doDBQuery($usernameCheckQuery);

    if ($usernameCheckResult->numRows() > 0) { ?>
      <p>
      <span style="color:red;">User account request for
      <?php echo $userName ?> already pending. Please be patient!</strong></span><br>
      <?php 
      $error = true;
      $doBottom = false;
    }
  }

  // 13 Mar 2006 : GWA : OK, stick them in the pending database.

  if (!$error) { 
    if ($isStudent) {
      $studentOK = 1;
    } else {
      $studentOK = 0;
    }
    $insertNewUserQuery = "insert into " . $_PENDINGUSERTABLENAME . " " . 
                          "set username=\"" . $userName . "\", " .
                          "firstname=\"" . $firstName . "\", " .
                          "lastname=\"" . $lastName . "\", " .
                          "academicInstitution=\"" . $academicInstitution .  "\", " .
                          "isStudent=\"" . $studentOK . "\", " .
                          "advisorName=\"" . $advisorName . "\", " .
                          "advisorEmail=\"" . $advisorEmail . "\", " .
                          "jobDescription=\"" . $jobDescription . "\"";
    $insertNewQueryResult = doDBQuery($insertNewUserQuery, false);
    if (DB::isError($insertNewQueryResult)) {
      $error = true;
    }
  }

  if (!$error) { 
    if ($isStudent) {
      $emailTO = $userName . ", " . $advisorEmail;
    } else {
      $emailTO = $userName;
    }
    $emailSUBJECT = "Rainier Account Creation Request Received";
    $emailBODY = <<<TEST
We have received a request to create an account for $firstName $lastName with
username:

  $userName
  
The request will be processed at our earliest convenience. Thank you for using Rainier!

The Rainier Team
TEST;
    $emailHEADERS = "From: motelab-admin@vancouver.wsu.edu";
    mail($emailTO, $emailSUBJECT, $emailBODY, $emailHEADERS);
    ?>
    <p>
    <span style="color:green;"><strong>SUCCESS!</strong></span>
    <p>
    You have successfully submitted your request.  You will receive an email
    at <?php echo $userName ?> when you account has been approved and
    created.
    <p>
    Thank you for your interest in <a
    href="http://netlab.encs.harvard.edu">Netlab</a>!
    <p>
    <strong>The Rainier Team</strong>
  <?php 
    exit();
  } else {
  }
}
?>

<?php 
if ($doBottom) { 
  if ($doSend) { ?>
    <span style="color:red;">
    <p>You have not completed the form. Please include the information provided
    below and submit again.
    </span>
  <?php } ?>

  <p>
  User accounts @Rainier are available for academic use only. Please complete the
  form below to request an account.

  <form name=ourForm method=get action="user-signup.php">
  <table border=0 hspace=4 cellspacing=2 width="90%" cellpadding=3>
  <tr>
  <td align="right">
    <?php if ($doSend && ($firstName == "")) { ?>
      <span style="color:red;font-weight:bold;">
    <?php } else { ?>
      <span>
    <?php } ?>
    First Name :
    </span>
  <td>
    <input type="text" name="firstName"
           style="width:20em;" 
           value="<?php echo $firstName; ?>">
  <tr>
  <td align="right">
    <?php if ($doSend && ($lastName == "")) { ?>
      <span style="color:red;font-weight:bold;">
    <?php } else { ?>
      <span>
    <?php } ?>
    Last Name :
    </span>
  <td>
    <input type="text" name="lastName"
           style="width:20em;"
           value="<?php echo $lastName; ?>">
  <tr>
  <td align="right">
    <?php if ($doSend && ($userName == "")) { ?>
      <span style="color:red;font-weight:bold;">
    <?php } else { ?>
      <span>
    <?php } ?>
    Email Address/User Name :
    </span>
  <td>
    <input type="text" name="userName" 
           style="width:20em;"
           value="<?php echo $userName; ?>">
  <tr>
  <td align="right">
    <?php if ($doSend && ($academicInstitution == "")) { ?>
      <span style="color:red;font-weight:bold;">
    <?php } else { ?>
      <span>
    <?php } ?>
    Academic Institution :
    </span>
  <td>
    <input type="text" name="academicInstitution"
           style="width:20em;"
           value="<?php echo $academicInstitution; ?>">
  <tr>
  <td align="right">
    Web Page URL (Optional) :
  <td>
    <input type="text" name="webpageURL"
           style="width:20em;"
           value="<?php echo $webpageURL; ?>">
  <tr>
  <td align="right">
    Are You a Student? 
  <td>
    <input type="checkbox" name="isStudent" onchange="changeIsStudent();"
    <?php if ($isStudent) { 
      echo "checked";
    }?> >
  <tr>
  <td align="right">
    <?php if ($doSend && $isStudent &&
        ($advisorName == "")) { ?>
      <span style="color:red;font-weight:bold;">
    <?php } else { ?>
      <span>
    <?php } ?>
    Student Advisor Name : 
    </span>
  <td>
    <input type="text" name="advisorName"
           style="width:20em;" 
    <?php if (!$isStudent) {
      echo "disabled";
    } else {
      echo "value=\"" . $advisorName . "\"";
    } ?> >
  <tr>
  <td align="right">
    <?php if ($doSend && $isStudent &&
        ($advisorEmail == "")) { ?>
      <span style="color:red;font-weight:bold;">
    <?php } else { ?>
      <span>
    <?php } ?>
    Student Advisor Email : 
  <td>
  <input type="text" name="advisorEmail"
           style="width:20em;"
    <?php if (!$isStudent) {
      echo "disabled";
    } else {
      echo "value=\"" . $advisorEmail . "\"";
    } ?> >
  <tr>
  <td align="right">
  What will you do with Rainier (Optional) :
  <td>
    <textarea name="jobDescription" cols="50" rows="6"><?php echo $jobDescription; ?> </textarea>
  </table>
    <input type=hidden name=doSend value=0>
    <input type=hidden name=updateID>
    <center>
    <input type=submit name=sendInfo
           value="Get Your Rainier Account"
           onClick="document.ourForm.doSend.value=1;">
    </center>
    <input type=hidden name=ReloadProtect value=<?php echo time(); ?>>
  </form>
<?php 
}
  include "nav/default_bot.php";
?>
<script language="JavaScript">
<!--
  function changeIsStudent() {
    if (document.ourForm.isStudent.checked) {
      document.ourForm.advisorName.disabled = false;
      document.ourForm.advisorEmail.disabled = false;
    } else {
      document.ourForm.advisorName.disabled = true;
      document.ourForm.advisorName.value = "";
      document.ourForm.advisorEmail.disabled = true;
      document.ourForm.advisorEmail.value = "";
    }
  }
//-->
</script>
