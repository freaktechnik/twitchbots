<?php
namespace Mini\Model;

use Auth0\SDK\API\Authentication;

/* CREATE TABLE IF NOT EXISTS authorized_users (
    id int(10) unsigned NOT NULL AUTO_INCREMENT,
    email MEDIUMTEXT CHARACTER SET ascii NOT NULL,
    PRIMARY KEY (id)
) DEFAULT CHARSET=ascii */

class Auth {
    /** @var Authentication $auth0 */
    private $auth0;
    /** @var \Auth0\SDK\API\Oauth2Client $auth0Client */
    private $auth0Client;

    /** @var string $clientId */
    private $clientId;
    /** @var string $redirectUrl */
    private $redirectUrl;
    /** @var string $domain */
    private $domain;

    /** @var PingablePDO $db */
    private $db;

    function __construct(string $clientId, string $clientSecret, string $cbk, string $domain, PingablePDO $db)
    {
        if(!empty($clientId)) {
            $this->auth0 = new Authentication($domain, $clientId);

            $this->auth0Client = $this->auth0->get_oauth_client($clientSecret, $cbk);
        }

        $this->clientId = $clientId;
        $this->redirectUrl = $cbk;
        $this->domain = $domain;
        $this->db = $db;
    }

    private function isAuthorizedUser(string $email): bool
    {
        $q = $this->db->prepare("SELECT email FROM authorized_users WHERE email=?");
        $q->execute(array($email));

        $result = $q->fetch();
        if(!$result) {
            return false;
        }
        else {
            return true;
        }
    }

    public function getConfig(): array
    {
        return [
            'clientId' => $this->clientId,
            'redirectUrl' => $this->redirectUrl,
            'domain' => $this->domain
        ];
    }

    public function isLoggedIn(): bool
    {
        $userInfo = $this->auth0Client->getUser();

        if (!$userInfo) {
            return false;
        } else {
            if($this->isAuthorizedUser($userInfo['email'])) {
                return true;
            }
            else {
                return false;
            }
        }
    }

    public function getIdentifier(): string
    {
        return $this->auth0Client->getUser()['email'];
    }

    public function logout()
    {
        $this->auth0Client->logout();
        session_destroy();
    }
}
