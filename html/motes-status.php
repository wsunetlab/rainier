<?php
  $_DISPLAYNEW = false;
  $_DISPLAYLOGIN = false;
  include "nav/default_top.php";

  /*
   * motes-status.php
   *
   * Displays a table of mote status with links to the appropriate map.
   */
?>
<?php

global $a;

global $_DSN;

$DB = DB::connect($_DSN);

if (DB::isError($DB)) {
  die ($DB->GetMessage());
}

$countQuery = "select count(moteid) as okcount from " .  $_MOTESTABLENAME . " where active=1 AND moteexists=1";
$countResult = doDBQuery($countQuery);
$countRow = $countResult->fetchRow(DB_FETCHMODE_ASSOC);
$okcount = $countRow['okcount'];

$countQuery = "select count(moteid) as missingcount from " .  $_MOTESTABLENAME . " where moteexists=0";
$countResult = doDBQuery($countQuery);
$countRow = $countResult->fetchRow(DB_FETCHMODE_ASSOC);
$missingcount = $countRow['missingcount'];

$countQuery = "select count(moteid) as disabledcount from " .  $_MOTESTABLENAME . " where active=0 AND moteexists=1";
$countResult = doDBQuery($countQuery);
$countRow = $countResult->fetchRow(DB_FETCHMODE_ASSOC);
$disabledcount = $countRow['disabledcount'];

$totalcount = $okcount+$disabledcount+$missingcount;

$newOKQuery = "select count(moteid) as newOKCount from " . 
              $_MOTESTABLENAME .  
              " where erase_ok=1 and ping_ok=1 and active=1";
$newOKResult = doDBQuery($newOKQuery);
$newOKRow = $newOKResult->fetchRow(DB_FETCHMODE_ASSOC);
$newOKCount = $newOKRow['newOKCount'];

$noProgramQuery = "select count(moteid) as noProgramCount from " .  
                  $_MOTESTABLENAME . 
                  " where ping_ok=1 and erase_ok=0";
$noProgramResult = doDBQuery($noProgramQuery);
$noProgramRow = $noProgramResult->fetchRow(DB_FETCHMODE_ASSOC);
$noProgramCount = $noProgramRow['noProgramCount'];

$noPingQuery = "select count(moteid) as noPingCount from " .
               $_MOTESTABLENAME . 
               " where ping_ok=0 AND moteexists=1";
$noPingResult = doDBQuery($noPingQuery);
$noPingRow = $noPingResult->fetchRow(DB_FETCHMODE_ASSOC);
$noPingCount = $noPingRow['noPingCount'];

$noOtherQuery = "select count(moteid) as noOtherCount from " .
               $_MOTESTABLENAME . 
               " where erase_ok=1 and ping_ok=1 and active=0";
$noOtherResult = doDBQuery($noOtherQuery);
$noOtherRow = $noOtherResult->fetchRow(DB_FETCHMODE_ASSOC);
$noOtherCount = $noOtherRow['noOtherCount'];

$lastUpdatedQuery = "select UNIX_TIMESTAMP(status_timestamp) as lastStamp" .
               " from " . $_MOTESTABLENAME . 
               " order by status_timestamp desc limit 1";
$lastUpdatedResult = doDBQuery($lastUpdatedQuery);
$lastUpdatedRow = $lastUpdatedResult->fetchRow(DB_FETCHMODE_ASSOC);
$lastUpdated = date("j M Y G:i:s", $lastUpdatedRow['lastStamp']);

$moteStatusQuery = "select moteid, roomlocation, ip_addr, program_port, jacknumber, floor, UNIX_TIMESTAMP(status_timestamp) as status_timestamp, firmware_version, ping_ok, erase_ok, active, moteexists from " . $_MOTESTABLENAME;
$moteStatusResult = doDBQuery($moteStatusQuery);
?>

<p>
<center>
<p>
<b>Rainier status:</b>
<?php 
  print $totalcount . " nodes (" . $okcount . " nodes active, ".
  $disabledcount . " nodes disabled, " . $missingcount . " nodes removed)<br>";
  print "Last updated " . $lastUpdated . "<br>";
?>
</center>
<p>
<b>Explanation:</b>
<ul>
<li><font color="green"><b><?php echo $newOKCount; ?> OK</b></font> - Mote is up and can be reprogrammed.
<li><font color="red"><b><?php echo $noProgramCount; ?> Cannot Program</b></font> - TMote Connect is up, but
mote cannot be reprogrammed.
<li><font color="blue"><b><?php echo $noPingCount; ?> Cannot Ping</b></font> - TMote Connect unit does not
respond to ping. Suggests problem with network connection.
<li><font color="grey"><b><?php echo $noOtherCount; ?> Manually Disabled</b></font> -
TMote connect was manually disabled. Maintenance may be being performed on
this node.
<li><font color="black"><b><?php echo $missingcount; ?> Removed</b></font> -
This node was removed from the lab for some reason, possibly because it broke
or because the jack it was attached to was reassigned.
</ul>
<p>
<center>
<table border=0 hspace=4 cellspacing=2 width="90%" cellpadding=3>
<tr bgcolor="#e0e0e0">
<tr>
<td width=5% bgcolor="#e0e0e0"><b>Mote ID</b></td>
<td width=8% bgcolor="#e0e0e0"><b>Room</b><br>(click for map)</td>
<td width=8% bgcolor="#e0e0e0"><b>Network jack</b></td>
<td width=10% bgcolor="#e0e0e0"><b>IP Addr</b></td>
<td width=8% bgcolor="#e0e0e0"><b>Program Port</b></td>
<td width=8% bgcolor="#e0e0e0"><b>Firmware Version</b></td>
<td width=20% bgcolor="#e0e0e0"><b>Status</b></td>
<td width=20% bgcolor="#e0e0e0"><b>Status last updated</b></td>
</tr>

<?php
    while ($moteinfo = $moteStatusResult->fetchRow(DB_FETCHMODE_ASSOC)) {
      $maplink = "<a href=\"/view-map.php?floor=".$moteinfo['floor']."&motes[]=".$moteinfo['moteid']."\">";

      print "<tr><td>" . $moteinfo['moteid'] . "</td>" .
            "<td>" . $maplink . $moteinfo['roomlocation'] . "</a></td>" .
            "<td>" . $moteinfo['jacknumber'] . "</td>" .
            "<td>" . $moteinfo['ip_addr'] . "</td>" .
            "<td>" . $moteinfo['program_port'] . "</td>" .
            "<td>" . $moteinfo['firmware_version'] . "</td>";
      $pingok = $moteinfo['ping_ok'];
      $eraseok = $moteinfo['erase_ok'];
      $active = $moteinfo['active'];
      $moteexists = $moteinfo['moteexists'];
      print "<td>";
      if (!$moteexists) {
        print " <font color=\"black\"><b>REMOVED</b></font></b>";
      } else if (!$active && !$pingok) {
        print " <font color=\"blue\"><b>DISABLED</b></font></b>";
      } else if (!$active && !$eraseok) {
        print " <font color=\"red\"><b>DISABLED</b></font></b>";
      } else if (!$active) {
        print " <font color=\"grey\"><b>DISABLED</b></font></b>";
      }
      if ($moteexists) {
        if ($pingok && $eraseok && $active) {
          print "<font color=\"green\"><b>OK</b></font>";
        } else if (!$pingok) {
          print " (cannot ping)";
        } else if (!$eraseok) {
          print " (cannot program)";
        }
      }
      print "</td>";
      $datestr = date("j M Y G:i:s", $moteinfo['status_timestamp']);
      print "<td>" . $datestr . "</td>";
      print "</tr>";
    }
?>

</table>

<?php
  include "nav/default_bot.php";
?>
