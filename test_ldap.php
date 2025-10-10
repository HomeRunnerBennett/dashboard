<?php
echo "<h2>Simple LDAP Authentication Test</h2>";

$ldap_server = '10.3.2.102';
$domain = 'malswitch';

// Test direct user authentication (bypass service account)
$test_users = [
    'nitelerp' => 'Ch1t3t3z0',
    'bmikwala' => 'Ch1t3t3z0Ch@Runner!',
    'administrator' => ''  // Try without password first
];

foreach ($test_users as $username => $password) {
    echo "<h3>Testing: $username</h3>";
    
    $ldap_conn = ldap_connect($ldap_server);
    if (!$ldap_conn) {
        echo "❌ Connection failed<br>";
        continue;
    }
    
    ldap_set_option($ldap_conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap_conn, LDAP_OPT_REFERRALS, 0);
    
    // Try different authentication formats
    $formats = [
        "Simple" => $username,
        "UPN" => "$username@$domain.local", 
        "Domain" => "$domain\\$username",
        "DN" => "CN=$username,CN=Users,DC=$domain,DC=local"
    ];
    
    foreach ($formats as $format_name => $user_dn) {
        $bind = @ldap_bind($ldap_conn, $user_dn, $password);
        echo "Format: $format_name ($user_dn) - " . ($bind ? "✅ SUCCESS" : "❌ FAILED") . "<br>";
        if ($bind) {
            // If successful, get some user info
            $search_result = @ldap_search($ldap_conn, "DC=$domain,DC=local", "(sAMAccountName=$username)");
            if ($search_result) {
                $entries = @ldap_get_entries($ldap_conn, $search_result);
                if ($entries['count'] > 0) {
                    echo "User DN: " . $entries[0]['dn'] . "<br>";
                }
            }
            break;
        }
    }
    
    ldap_close($ldap_conn);
    echo "<hr>";
}
?>