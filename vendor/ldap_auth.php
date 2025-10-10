<?php
function ldap_authenticate($username, $password) {
    $ldap_server = '10.3.2.102';
    $domain = 'malswitch';
    
    $ldap_conn = ldap_connect($ldap_server);
    if (!$ldap_conn) {
        error_log("LDAP: Connection failed");
        return false;
    }
    
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
    
    // Try domain\username format first (this works for regular users)
    $user_dn = "$domain\\$username";
    $bind = @ldap_bind($ldap_conn, $user_dn, $password);
    
    if (!$bind) {
        // For administrator, try simple username
        $bind = @ldap_bind($ldap_conn, $username, $password);
    }
    
    ldap_close($ldap_conn);
    return $bind;
}
?>