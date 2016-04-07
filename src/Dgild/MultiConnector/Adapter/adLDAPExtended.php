<?php

namespace Dgild\MultiConnector\Adapter;


class adLDAPExtended extends \adLDAP\adLDAP
{
    /**
     * Check the connection if the service LDAP is online
     *
     * @param string $domain    Domain Name from service LDAP
     *
     * @param string $port      Port of use from service LDAP
     *
     * @return boolean      true is online otherwise false
     */
    protected function checkConnectionLdap($domain, $port)
    {
        $port = ($port ? (int)$port : 389);
        $fp = @fsockopen($domain, $port, $errno, $errstr, 10);
        return (!$fp ? false : true);
    }

    /**
     * Connects and Binds to the Domain Controller
     *
     * @return bool
     */
    public function connect()
    {
        // Connect to the AD/LDAP server as the username/password
        $domainController = $this->randomController();
        if ($this->useSSL) {
            $this->ldapConnection = ldap_connect("ldaps://" . $domainController, $this->adPort) or die('no connect');
        } else {
            $this->ldapConnection = ldap_connect("ldap://" . $domainController, $this->adPort) or die('no connect');
        }

        $okConnection = $this->checkConnectionLdap($domainController, $this->adPort);
        if (!$okConnection) {
            throw new \adLDAP\adLDAPException('Ldap is down');
        }

        // Set some ldap options for talking to AD
        ldap_set_option($this->ldapConnection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->ldapConnection, LDAP_OPT_REFERRALS, $this->followReferrals);

        if ($this->useTLS) {
            ldap_start_tls($this->ldapConnection);
        }

        // Bind as a domain admin if they've set it up
        if ($this->adminUsername !== NULL && $this->adminPassword !== NULL) {
            $this->ldapBind = @ldap_bind($this->ldapConnection, $this->adminUsername . $this->accountSuffix, $this->adminPassword);
            if (!$this->ldapBind) {
                if ($this->useSSL && !$this->useTLS) {
                    // If you have problems troubleshooting, remove the @ character from the ldapldapBind command above to get the actual error message
                    throw new adLDAPException('Bind to Active Directory failed. Either the LDAPs connection failed or the login credentials are incorrect. AD said: ' . $this->getLastError());
                }
                else {
                    throw new adLDAPException('Bind to Active Directory failed. Check the login credentials and/or server details. AD said: ' . $this->getLastError());
                }
            }
        }
        if ($this->useSSO && $_SERVER['REMOTE_USER'] && $this->adminUsername === null && $_SERVER['KRB5CCNAME']) {
            putenv("KRB5CCNAME=" . $_SERVER['KRB5CCNAME']);
            $this->ldapBind = @ldap_sasl_bind($this->ldapConnection, NULL, NULL, "GSSAPI");
            if (!$this->ldapBind) {
                throw new adLDAPException('Rebind to Active Directory failed. AD said: ' . $this->getLastError());
            }
            else {
                return true;
            }
        }

        if ($this->baseDn == NULL) {
            $this->baseDn = $this->findBaseDn();
        }
        return true;
    }


    /**
     * Validate a user's login credentials
     *
     * @param string $username A user's AD username
     * @param string $password A user's AD password
     * @param bool optional $preventRebind
     * @return bool
     */
    public function authenticate($username, $password, $preventRebind = false) {
        // Prevent null binding
        if ($username === NULL || $password === NULL) { return false; }
        if (empty($username) || empty($password)) { return false; }

        // Allow binding over SSO for Kerberos
        if ($this->useSSO && $_SERVER['REMOTE_USER'] && $_SERVER['REMOTE_USER'] == $username && $this->adminUsername === NULL && $_SERVER['KRB5CCNAME']) {
            putenv("KRB5CCNAME=" . $_SERVER['KRB5CCNAME']);
            $this->ldapBind = @ldap_sasl_bind($this->ldapConnection, NULL, NULL, "GSSAPI");
            if (!$this->ldapBind) {
                throw new adLDAPException('Rebind to Active Directory failed. AD said: ' . $this->getLastError());
            }
            else {
                return true;
            }
        }

        // Bind as the user
        $ret = true;
        $this->ldapBind = @ldap_bind($this->ldapConnection, $username . $this->accountSuffix, $password);
        if (!$this->ldapBind) {
            $ret = false;
        }

        // Cnce we've checked their details, kick back into admin mode if we have it
        if (!empty($this->adminUsername) && !$preventRebind) {
            $this->ldapBind = @ldap_bind($this->ldapConnection, $this->adminUsername . $this->accountSuffix , $this->adminPassword);
            if (!$this->ldapBind) {
                // This should never happen in theory
                throw new adLDAPException('Rebind to Active Directory failed. AD said: ' . $this->getLastError());
            }
        }
        return $ret;
    }


}