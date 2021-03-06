<?php

namespace Dgild\MultiConnector\Adapter;

use Dgild\MultiConnector\Model\User as UserModel;

interface LdapInterface
{
    /**
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    public function connect($username, $password);

    /**
     * @return bool
     */
    public function isConnected();

    /**
     * @param $username
     *
     * @return UserModel
     */
    public function getUserInfo($username);
}
