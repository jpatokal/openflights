<?php
require_once("../php/locale.php");
require_once("../php/db.php");

if(isSet($_GET["new"])) {
  $type = "signup";
} else {
  $type = "settings";
}
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
  <head>
<title>OpenFlights: <?php if($type == "signup") {
  echo _("Create new account");
    } else {
  echo _("Account settings");
    }
?></title>
    <link rel="stylesheet" href="/css/style_reset.css" type="text/css">
    <link rel="stylesheet" href="/css/signup.css" type="text/css">
    <link rel="stylesheet" href="/openflights.css" type="text/css">
    <link rel="gettext" type="application/x-po" href="/locale/<?php echo $locale?>/LC_MESSAGES/messages.po" />
    <script type="text/javascript" src="/js/prototype.js"></script>
    <script type="text/javascript" src="/js/utilities.js"></script>
    <script type="text/javascript" src="/js/greatcircle.js"></script>
    <script type="text/javascript" src="/js/Gettext.js"></script>
    <script type="text/javascript" src="/js/signup.js"></script>
  </head>

  <body>
    <div id="mainContainer">
      <div id="sideBarContentWrapper">
	  
	<div id="contentContainer">
	  <div id="nonmap">

	    <FORM name="signupform" method="POST" action="/">
  <a name="top"><h1>OpenFlights: <?php if($type == "signup") {
  echo _("Create new account");
    } else {
  echo _("Account settings");
    }
?></h1>
      
	      <div id="miniresultbox"></div>

	      <table>
<?php if($type == "signup") { ?>
		  <tr>
	            <td colspan="3"><h2><?php echo _("Basic information") ?></h2></td>
		  </tr>
		  <tr>
	            <td class="key"><?php echo _("Name") ?></td>
		    <td class="value"><INPUT type="text" name="username" size="20" onChange="changeName();"></td>
	            <td class="value"><p><?php echo _("This will be used as the name of your profile.") ?></p>
		      <span id="profileurl"></span></td>
		  </tr><tr>
	            <td class="key"><?php echo _("Password") ?></td>
		    <td class="value"><INPUT type="password" name="pw1" size="20"></td>
	            <td class="value"><?php echo _("Pick something hard to guess, but easy to remember.  Case-sensitive!") ?></td>
		  </tr><tr>
	            <td class="key"><?php echo _("Password again") ?></td>
		    <td class="value"><INPUT type="password" name="pw2" size="20"></td>
		  </tr><tr>
	            <td class="key"><?php echo _("E-mail (optional)") ?>&nbsp;&nbsp;</td>
		    <td class="value"><INPUT type="text" name="email" size="20"></td>
		    <td class="desc">
		      <?php printf(_("If you forget your password, we can mail you a new one to this address.  We will <i>never</i> send you any other mail or share your private information, see <%s>privacy policy</a> for details."), "a href='#' onClick='window.open(\"/help/privacy.html\", \"Help\", \"width=500,height=400,scrollbars=yes\")'") ?>
		    </td>
		  </tr>
<?php } else { ?>
		  <tr>
                    <td class="key"><nobr><?php echo _("Profile address") ?></nobr></td>
		    <td class="value"><INPUT type="text" name="myurl" style="border:none" size="40" READONLY>
		      <input type="text" name="count" value="" style="border: none" READONLY></td>
                      <td class="desc"><?php echo _("The public address of your profile and how often it has been viewed.") ?></td>
		    <td class="value" rowspan=3><span id="eliteicon"></span>
		  </tr><tr>
                    <td class="key"><?php echo _("Facebook link") ?></td>
                    <td class="value"><span id="facebook"><i><?php echo _("Checking...") ?></i></span></td>
		    <td class="desc">
  <?php printf("Install the <%s>OpenFlights Facebook app</a> to update your flights to your <%s>Facebook</a> profile.",
	       "a href='http://apps.facebook.com/openflights'", "a href='http://facebook.com'"); ?></td>
		  </tr><tr>
	       <td class="key"><?php echo _("Banners") ?></td>
	       <td class="value" colspan=2><?php echo _("Blog banner (HTML)") ?><br>
		      <textarea name="banner_html" cols="60" rows="4" readonly></textarea><br>
	       <?php echo _("Bulletin board banner (phpBB)") ?><br>
		      <textarea name="banner_phpbb" cols="60" rows="3" readonly></textarea><br>
		      <span id="banner_img"><i>Loading...</i></span></td>
		  </tr><tr>
	       <td class="key"><?php echo _("E-mail (optional)") ?>&nbsp;&nbsp;</td>
		    <td class="value"><INPUT type="text" name="email" size="20"></td>
	            <td class="desc">
		      <?php printf(_("If you forget your password, we can mail you a new one to this address.  We will <i>never</i> send you any other mail or share your private information, see <%s>privacy policy</a> for details."), "a href='#' onClick='window.open(\"/help/privacy.html\", \"Help\", \"width=500,height=400,scrollbars=yes\")'") ?>
                    </td>
		  </tr>
<?php } ?>
		</tr><tr>
  	          <td colspan="4"><h2><?php echo _("Profile settings") ?></h2>
<?php if($type == "signup") echo _("You can easily change these later by clicking on <i>Settings</i>.") ?></td>
		</tr><tr>
	            <td class="key"><?php echo _("Language") ?></td>
		    <td class="value"><?php echo locale_pulldown($db, $locale) ?></td>
		</tr><tr>
		    <td class="key"><?php echo _("Privacy") ?></td>
		    <td class="value"><input type="radio" name="privacy" value="N" onClick="JavaScript:changePrivacy('N')"><?php echo _("Private") ?><br>
		    <input type="radio" name="privacy" value="Y" onClick="JavaScript:changePrivacy('Y')" CHECKED><?php echo _("Public") ?><br>
		    <input type="radio" name="privacy" value="O" onClick="JavaScript:changePrivacy('O')"><?php echo _("Open") ?></td>
		    <td class="desc">
		    <span id="privacyN" style="display: none">
		    <?php printf (_("<b>Private</b> profiles are visible only to you.  <%s>Gold and Platinum</a> users can password-protect their private profiles, so only people who know the password can see them."), 'a href="/donate.html" target="_blank"') ?>
		      </span>
		    <span id="privacyY" style="display: inline"><?php echo _("<b>Public</b> profiles let others see your flight map and general statistics, but flight details like exact dates and class of service are not revealed.") ?></span>
		    <span id="privacyO" style="display: none"><?php echo _("<b>Open</b> profiles let others see, but not edit, your detailed flight data as well.") ?></span></td>
		  </tr><tr>
		    <td class="key"><?php echo _("Flight editor") ?></td>
		    <td class="value"><input type="radio" name="editor" value="B" onClick="JavaScript:changeEditor('B')" CHECKED><?php echo _("Basic") ?></br>
		    <input type="radio" name="editor" value="D" onClick="JavaScript:changeEditor('D')"><?php echo _("Detailed") ?><br>
		    <td class="desc">
		    <span id="basiceditor" style="display: inline"><?php echo _("The <B>Basic</b> editor is quick and easy: from where, to where, the date and optionally the airline, up to four flights at a time.  The fastest way to build up your map!") ?></span>
		    <span id="detaileditor" style="display: none"><?php echo _("The <B>Detailed</b> editor lets you add class of service, seat numbers, plane models and registrations, freeform notes and much more.  Perfect for aviation fans and planespotters.") ?></span></td></tr>
<?php if($type == "settings") { ?>
		  <tr class="gold">
		    <td class="key"><?php echo _("Guest password") ?></td>
		    <td class="value"><INPUT type="password" name="guestpw" size="20" DISABLED></td>
				<td class="desc" colspan=2><a href="/donate.html" target="_blank"><img src="/img/gold-star-mini.png" title="<?php echo _("Gold Elite feature") ?>" height=17 width=17></a> <?php echo _("Password protect your Private profile, so only friends and family can see it.") ?></tr><tr class="gold">
 		    <td class="key"><?php echo _("Default view") ?></td>
		    <td class="value"><input type="radio" name="startpane" value="H" CHECKED DISABLED><?php echo _("Help") ?><br>
		    <input type="radio" name="startpane" value="A" DISABLED><?php echo _("Analyze") ?><br>
		    <input type="radio" name="startpane" value="T" DISABLED><?php echo _("Top 10") ?></td>
				<td class="desc" colspan=2><a href="/donate.html" target="_blank"><img src="/img/gold-star-mini.png" title="<?php echo _("Gold Elite feature") ?>" height=17 width=17></a> <?php echo _("Display a screen of your choice instead of banner ads.") ?>
		  </tr><tr>
 		    <td colspan="4"><h2><?php echo _("Manage flights") ?></h2></td>
		  </tr><tr>
		    <td></td>
		    <td class="value">
		      <INPUT type='button' value='<?php echo _("Backup to CSV") ?>' onClick='javascript:backupFlights()'>
		      <INPUT type='button' value='<?php echo _("Delete all flights") ?>' onClick='javascript:validate("RESET")'></td>
		    <td class="desc" colspan=2>
		      <a href="/help/csv.html">CSV</a> files can be opened and edited with spreadsheets like Excel.
		    </td>
		  </tr><tr>
		    <td colspan="4"><h2><?php echo _("Change password") ?></h2></td>
		  </tr><tr>
		    <td class="key"><?php echo _("Current password") ?></td>
		    <td class="value"><INPUT type="password" name="oldpw" size="20">
		    <INPUT type="hidden" name="username"></td>
		    <td class="desc" colspan=2><?php echo _("You only need to enter this if changing your password.") ?></td>
		  </tr><tr>
		    <td class="key"><?php echo _("New password") ?></td>
		    <td class="value"><INPUT type="password" name="pw1" size="20"></td>
		  </tr><tr>
		    <td class="key"><?php echo _("New password again") ?>&nbsp;</td>
		    <td class="value"><INPUT type="password" name="pw2" size="20"></td>
		  </tr><tr>
		    <td class="key"></td>
		    <td class="value">
		      <INPUT type="button" value="<?php echo _("Save changes") ?>" onClick="validate('EDIT')">
		      <INPUT type="button" value="<?php echo _("Cancel") ?>" onClick="location.href = '/'">
		    </td>
		  </tr>
<?php } else { ?>
		  <tr>
		    <td class="key"></td>
		    <td class="value">
		      <INPUT type="button" value="<?php echo _("Sign me up!") ?>" onClick="validate('NEW')">
		      <small><A href="/"><?php echo _("Cancel") ?></a></small>
		    </td>
		  </tr>
<?php } ?>
	      </table>
	    </FORM>
	  </div>
	</div>
	
	<div id="sideBar">
<?php include '../sidebar.html';
include 'ad-sidebar.html';
?>
	</div>

      </div> <!-- end sidebarwrapper -->
    </div> <!-- end mainContainer -->

  </body>
</html>
