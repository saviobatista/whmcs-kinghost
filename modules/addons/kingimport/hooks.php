<?php
/*
**********************************************

      *** Addon Module Example Hook ***

This is a demo hook file for an addon module.
Addon Modules can utilise all of the WHMCS
hooks in exactly the same way as a normal hook
file would, and can contain multiple hooks.

For more info, please refer to the hooks docs
 @   http://wiki.whmcs.com/Hooks

**********************************************
*/

function addonexample_hook_login($vars) {
    # Hook code goes here
}

function addonexample_hook_logout($vars) {
    # Hook code goes here
}

add_hook("ClientLogin",1,"addonexample_hook_login");
add_hook("ClientLogout",1,"addonexample_hook_logout");

?>